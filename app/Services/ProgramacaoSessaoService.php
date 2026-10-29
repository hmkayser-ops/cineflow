<?php

namespace App\Services;

use App\Models\Assento;
use App\Models\Auditoria;
use App\Models\Filme;
use App\Models\Sala;
use App\Models\Sessao;
use App\Models\TabelaPreco;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Caso de uso "Programar sessão" (com o caso incluído
 * "Verificar disponibilidade da sala").
 *
 * Os passos do fluxo básico estão indicados nos comentários.
 */
class ProgramacaoSessaoService
{
    /** Passo 5: término = início + duração do filme + trailers + limpeza. */
    public function calcularTermino(Filme $filme, CarbonInterface $inicio): Carbon
    {
        return Carbon::instance($inicio)->copy()->addMinutes(
            $filme->duracao_min
            + config('cineflow.minutos_trailers')
            + config('cineflow.minutos_limpeza')
        );
    }

    /** Passo 7: valores padrão da tabela de preços vigente para o formato. */
    public function precoPadrao(string $formato, CarbonInterface $data): array
    {
        $tabela = TabelaPreco::vigente($formato, $data);

        if (! $tabela) {
            throw new ProgramacaoException(
                "Não existe tabela de preços vigente para o formato {$formato}.",
                ProgramacaoException::SEM_TABELA_PRECO
            );
        }

        $inteira = (float) $tabela->valor_inteira;

        return [
            'valor_inteira' => $inteira,
            'valor_meia' => round($inteira * config('cineflow.percentual_meia'), 2),
        ];
    }

    /**
     * Caso de uso incluído "Verificar disponibilidade da sala".
     * Retorna as sessões que conflitam com o intervalo.
     */
    public function verificarDisponibilidade(Sala $sala, CarbonInterface $inicio, CarbonInterface $fim, ?int $ignorarSessaoId = null)
    {
        return Sessao::sobrepostas($sala->id, $inicio, $fim)
            ->when($ignorarSessaoId, fn ($q) => $q->where('id', '!=', $ignorarSessaoId))
            ->with('filme')
            ->orderBy('inicio')
            ->get();
    }

    /** FA1: primeiro horário livre na sala, a partir do horário pedido, no mesmo dia. */
    public function sugerirHorarioLivre(Sala $sala, Filme $filme, CarbonInterface $aPartirDe): ?Carbon
    {
        $candidato = Carbon::instance($aPartirDe)->copy();
        $limite = $candidato->copy()->endOfDay();

        for ($i = 0; $i < 50 && $candidato->lte($limite); $i++) {
            $conflitos = $this->verificarDisponibilidade($sala, $candidato, $this->calcularTermino($filme, $candidato));
            if ($conflitos->isEmpty()) {
                return $candidato;
            }
            // Tenta logo após o término da última sessão conflitante (arredondado a 5 min)
            $proximo = Carbon::instance($conflitos->max('fim'));
            $minutos = (int) $proximo->format('i');
            $candidato = $proximo->copy()->addMinutes((5 - $minutos % 5) % 5)->second(0);
        }

        return null;
    }

    /**
     * Valida todas as regras de negócio para uma sessão.
     * Lança ProgramacaoException com o código do fluxo alternativo violado.
     */
    public function validar(Filme $filme, Sala $sala, CarbonInterface $inicio, string $formato): Carbon
    {
        // FA4 – data/hora no passado
        if ($inicio->lte(now())) {
            throw new ProgramacaoException(
                'Não é possível programar sessão em data e hora já passadas.',
                ProgramacaoException::DADOS_INVALIDOS
            );
        }

        // FA3 – sala em manutenção ou inativa
        if ($sala->status === 'inativa') {
            throw new ProgramacaoException("A sala {$sala->nome} está inativa.", ProgramacaoException::SALA_MANUTENCAO);
        }
        if ($sala->emManutencaoEm($inicio)) {
            $previsao = $sala->manutencao_ate ? ' Previsão de liberação: '.$sala->manutencao_ate->format('d/m/Y').'.' : ' Sem previsão de liberação.';
            throw new ProgramacaoException(
                "A sala {$sala->nome} está em manutenção.{$previsao}",
                ProgramacaoException::SALA_MANUTENCAO,
                ['previsao' => $sala->manutencao_ate?->toDateString()]
            );
        }

        // Regra: sessão 3D somente em sala com suporte a 3D
        if ($formato === '3D' && ! $sala->suporta_3d) {
            throw new ProgramacaoException(
                "A sala {$sala->nome} não possui equipamento para exibição em 3D.",
                ProgramacaoException::SALA_SEM_3D
            );
        }

        // FA2 – contrato de exibição não cobre a data
        if (! $filme->licencaCobre($inicio)) {
            throw new ProgramacaoException(
                sprintf(
                    'O filme "%s" não tem licença de exibição nessa data. Período contratado: %s a %s.',
                    $filme->titulo,
                    $filme->licenca_inicio->format('d/m/Y'),
                    $filme->licenca_fim->format('d/m/Y')
                ),
                ProgramacaoException::SEM_LICENCA
            );
        }

        // Passo 5 e passo 6 (<<include>> Verificar disponibilidade da sala)
        $fim = $this->calcularTermino($filme, $inicio);
        $conflitos = $this->verificarDisponibilidade($sala, $inicio, $fim);

        if ($conflitos->isNotEmpty()) {
            $c = $conflitos->first();
            $sugestao = $this->sugerirHorarioLivre($sala, $filme, $inicio);
            throw new ProgramacaoException(
                sprintf(
                    'Conflito de horário na sala %s: "%s" ocupa a sala das %s às %s (incluindo trailers e limpeza).%s',
                    $sala->nome,
                    $c->filme->titulo,
                    $c->inicio->format('H:i'),
                    $c->fim->format('H:i'),
                    $sugestao ? ' Primeiro horário livre: '.$sugestao->format('H:i').'.' : ' Não há horário livre no restante do dia.'
                ),
                ProgramacaoException::CONFLITO_HORARIO,
                ['sessao_conflitante' => $c->id, 'sugestao' => $sugestao?->format('H:i')]
            );
        }

        return $fim;
    }

    /**
     * Passos 9 a 11: registra a sessão, gera o mapa de assentos e libera para venda.
     * A transação com bloqueio da sala evita que duas programações simultâneas
     * ocupem o mesmo horário (controle de concorrência).
     */
    public function programar(array $dados, User $gerente): Sessao
    {
        return DB::transaction(function () use ($dados, $gerente) {
            $sala = Sala::whereKey($dados['sala_id'])->lockForUpdate()->firstOrFail();
            $filme = Filme::findOrFail($dados['filme_id']);
            $inicio = Carbon::parse($dados['inicio']);

            $fim = $this->validar($filme, $sala, $inicio, $dados['formato']);

            $sessao = Sessao::create([
                'filme_id' => $filme->id,
                'sala_id' => $sala->id,
                'inicio' => $inicio,
                'fim' => $fim,
                'formato' => $dados['formato'],
                'idioma' => $dados['idioma'],
                'valor_inteira' => $dados['valor_inteira'],
                'valor_meia' => $dados['valor_meia'],
                'status' => 'programada',
                'programada_por' => $gerente->id,
            ]);

            // Passo 10: mapa de assentos da sessão a partir da planta da sala
            $linhas = $sala->assentos()
                ->where('tipo', '!=', Assento::TIPO_INEXISTENTE)
                ->pluck('id')
                ->map(fn ($id) => ['sessao_id' => $sessao->id, 'assento_id' => $id, 'status' => 'disponivel'])
                ->all();
            foreach (array_chunk($linhas, 200) as $lote) {
                DB::table('sessao_assentos')->insert($lote);
            }

            Auditoria::registrar('programar', 'sessao', $sessao->id, [
                'filme' => $filme->titulo,
                'sala' => $sala->nome,
                'inicio' => $inicio->format('d/m/Y H:i'),
                'fim' => $fim->format('d/m/Y H:i'),
                'formato' => $dados['formato'].' '.$dados['idioma'],
            ]);

            return $sessao;
        });
    }

    /**
     * Programação em lote (requisito especial): replica a sessão para os
     * próximos N dias, no mesmo horário. Cada dia é validado separadamente;
     * os dias com impedimento são informados sem interromper os demais.
     *
     * @return array{criadas: array<Sessao>, recusadas: array<string, string>}
     */
    public function programarEmLote(array $dados, int $diasAdicionais, User $gerente): array
    {
        $criadas = [];
        $recusadas = [];
        $base = Carbon::parse($dados['inicio']);

        for ($d = 0; $d <= $diasAdicionais; $d++) {
            $dia = $base->copy()->addDays($d);
            try {
                $criadas[] = $this->programar(array_merge($dados, ['inicio' => $dia->format('Y-m-d H:i:s')]), $gerente);
            } catch (ProgramacaoException $e) {
                if ($d === 0) {
                    throw $e; // a sessão principal precisa ser válida
                }
                $recusadas[$dia->format('d/m/Y')] = $e->getMessage();
            }
        }

        return ['criadas' => $criadas, 'recusadas' => $recusadas];
    }

    /** Cancelamento de sessão (gera auditoria). Bloqueado se já houver ingresso vendido. */
    public function cancelar(Sessao $sessao, string $motivo): void
    {
        if ($sessao->assentos()->where('status', 'ocupado')->exists()) {
            throw new ProgramacaoException(
                'A sessão já possui ingressos vendidos e não pode ser cancelada por aqui.',
                'RN-CANCELAMENTO'
            );
        }

        $sessao->update(['status' => 'cancelada']);

        Auditoria::registrar('cancelar', 'sessao', $sessao->id, [
            'filme' => $sessao->filme->titulo,
            'inicio' => $sessao->inicio->format('d/m/Y H:i'),
            'motivo' => $motivo,
        ]);
    }
}
