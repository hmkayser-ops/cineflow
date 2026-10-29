<?php

namespace Database\Seeders;

use App\Models\Assento;
use App\Models\Filme;
use App\Models\Sala;
use App\Models\TabelaPreco;
use App\Models\User;
use App\Services\ProgramacaoSessaoService;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração para a apresentação da Sprint 1.
 * Senha de todos os usuários: cineflow123
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $gerente = User::create(['name' => 'Gerente Demonstração', 'email' => 'gerente@cineflow.test', 'password' => 'cineflow123', 'perfil' => 'gerente']);
        User::create(['name' => 'Atendente Demonstração', 'email' => 'atendente@cineflow.test', 'password' => 'cineflow123', 'perfil' => 'atendente']);
        User::create(['name' => 'Portaria Demonstração', 'email' => 'portaria@cineflow.test', 'password' => 'cineflow123', 'perfil' => 'portaria']);

        TabelaPreco::create(['formato' => '2D', 'valor_inteira' => 32.00, 'vigente_desde' => '2026-01-01']);
        TabelaPreco::create(['formato' => '3D', 'valor_inteira' => 40.00, 'vigente_desde' => '2026-01-01']);

        // Salas
        $sala1 = Sala::create(['nome' => '1', 'fileiras' => 8, 'assentos_por_fileira' => 12, 'suporta_3d' => false]);
        $sala2 = Sala::create(['nome' => '2', 'fileiras' => 10, 'assentos_por_fileira' => 14, 'suporta_3d' => true]);
        $sala3 = Sala::create(['nome' => '3', 'fileiras' => 6, 'assentos_por_fileira' => 10, 'suporta_3d' => false,
            'status' => 'manutencao', 'manutencao_ate' => now()->addDays(5)->toDateString()]);

        foreach ([$sala1, $sala2, $sala3] as $sala) {
            $sala->gerarPlanta();
            $meio = intdiv($sala->assentos_por_fileira, 2);
            // Corredor central e assentos acessíveis na primeira fileira
            $sala->assentos()->where('numero', $meio)->update(['tipo' => Assento::TIPO_INEXISTENTE]);
            $sala->assentos()->where('fileira', 'A')->whereIn('numero', [1, 2, $sala->assentos_por_fileira])
                ->update(['tipo' => Assento::TIPO_ACESSIVEL]);
        }

        // Filmes fictícios
        $hoje = today();
        $filmes = [
            ['titulo' => 'A Última Sessão', 'genero' => 'Drama', 'duracao_min' => 118, 'classificacao' => '12',
             'sinopse' => 'O projecionista de um cinema de rua prepara a exibição final antes do fechamento da sala.'],
            ['titulo' => 'Órbita Zero', 'genero' => 'Ficção científica', 'duracao_min' => 142, 'classificacao' => '14',
             'sinopse' => 'Uma tripulação precisa decidir entre voltar para casa ou seguir um sinal desconhecido.'],
            ['titulo' => 'O Pequeno Faroleiro', 'genero' => 'Animação', 'duracao_min' => 95, 'classificacao' => 'L',
             'sinopse' => 'Um menino assume o farol da ilha quando o avô adoece.'],
            ['titulo' => 'Sinal Fechado', 'genero' => 'Suspense', 'duracao_min' => 107, 'classificacao' => '16',
             'sinopse' => 'Um motorista de aplicativo percebe que está sendo seguido durante uma madrugada em Porto Alegre.'],
        ];
        $criados = [];
        foreach ($filmes as $f) {
            $criados[] = Filme::create($f + [
                'distribuidora' => 'Distribuidora Exemplo',
                'licenca_inicio' => $hoje->copy()->subDays(10),
                'licenca_fim' => $hoje->copy()->addDays(40),
            ]);
        }
        // Filme com licença futura (para demonstrar o FA2)
        Filme::create(['titulo' => 'Maré Alta', 'genero' => 'Aventura', 'duracao_min' => 124, 'classificacao' => '10',
            'distribuidora' => 'Distribuidora Exemplo',
            'licenca_inicio' => $hoje->copy()->addDays(20), 'licenca_fim' => $hoje->copy()->addDays(60),
            'sinopse' => 'Pré-estreia: uma família de pescadores enfrenta a maior cheia em décadas.']);

        // Grade de hoje (horários futuros), amanhã e depois (usa a mesma regra de negócio do sistema)
        auth()->login($gerente);
        $servico = app(ProgramacaoSessaoService::class);
        $grade = [
            [$criados[0], $sala1, '14:00', '2D', 'legendado'],
            [$criados[0], $sala1, '19:30', '2D', 'legendado'],
            [$criados[1], $sala2, '15:00', '3D', 'dublado'],
            [$criados[1], $sala2, '20:00', '3D', 'legendado'],
            [$criados[2], $sala1, '10:00', '2D', 'dublado'],
            [$criados[3], $sala2, '23:00', '2D', 'legendado'],
        ];
        foreach ([0, 1, 2] as $dias) {
            foreach ($grade as [$filme, $sala, $hora, $formato, $idioma]) {
                $inicio = $hoje->copy()->addDays($dias)->format('Y-m-d').' '.$hora.':00';
                if (\Carbon\Carbon::parse($inicio)->lte(now()->addMinutes(5))) {
                    continue; // hoje: só os horários que ainda não passaram
                }
                $preco = $servico->precoPadrao($formato, $hoje->copy()->addDays($dias));
                $servico->programar([
                    'filme_id' => $filme->id, 'sala_id' => $sala->id, 'inicio' => $inicio,
                    'formato' => $formato, 'idioma' => $idioma,
                ] + $preco, $gerente);
            }
        }
        auth()->logout();
    }
}
