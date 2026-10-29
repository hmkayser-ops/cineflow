<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sala extends Model
{
    protected $table = 'salas';

    protected $attributes = ['status' => 'ativa', 'suporta_3d' => false];

    public const STATUS = [
        'ativa' => 'Ativa',
        'manutencao' => 'Em manutenção',
        'inativa' => 'Inativa',
    ];

    protected $fillable = ['nome', 'fileiras', 'assentos_por_fileira', 'suporta_3d', 'status', 'manutencao_ate'];

    protected function casts(): array
    {
        return [
            'suporta_3d' => 'boolean',
            'manutencao_ate' => 'date',
            'fileiras' => 'integer',
            'assentos_por_fileira' => 'integer',
        ];
    }

    public function assentos(): HasMany
    {
        return $this->hasMany(Assento::class)->orderBy('fileira')->orderBy('numero');
    }

    public function sessoes(): HasMany
    {
        return $this->hasMany(Sessao::class);
    }

    /** Letra da fileira a partir do índice (0 => A, 1 => B ...). */
    public static function letraFileira(int $indice): string
    {
        return chr(ord('A') + $indice);
    }

    /** Capacidade real: posições que são assentos (corredores não contam). */
    public function getCapacidadeAttribute(): int
    {
        return $this->assentos()->where('tipo', '!=', Assento::TIPO_INEXISTENTE)->count();
    }

    /** A sala está em manutenção na data informada? */
    public function emManutencaoEm(CarbonInterface $data): bool
    {
        if ($this->status !== 'manutencao') {
            return false;
        }

        // Sem previsão de liberação: indisponível até alguém reativar a sala
        return $this->manutencao_ate === null || $data->lte($this->manutencao_ate->copy()->endOfDay());
    }

    /**
     * (Re)gera a planta de assentos com base em fileiras x assentos.
     * Preserva o tipo das posições que continuam existindo.
     */
    public function gerarPlanta(): void
    {
        $existentes = $this->assentos()->get()->keyBy(fn ($a) => $a->fileira.'-'.$a->numero);
        $manter = [];

        for ($f = 0; $f < $this->fileiras; $f++) {
            $letra = self::letraFileira($f);
            for ($n = 1; $n <= $this->assentos_por_fileira; $n++) {
                $chave = $letra.'-'.$n;
                $manter[] = $chave;
                if (! $existentes->has($chave)) {
                    $this->assentos()->create(['fileira' => $letra, 'numero' => $n, 'tipo' => Assento::TIPO_PADRAO]);
                }
            }
        }

        $existentes->reject(fn ($a, $chave) => in_array($chave, $manter, true))
            ->each(fn ($a) => $a->delete());
    }

    /** Grade da planta: [fileira => [numero => Assento]] */
    public function grade(): array
    {
        $grade = [];
        foreach ($this->assentos()->get() as $assento) {
            $grade[$assento->fileira][$assento->numero] = $assento;
        }
        ksort($grade);

        return $grade;
    }

    public function possuiSessoesFuturas(): bool
    {
        return $this->sessoes()->where('status', 'programada')->where('fim', '>', now())->exists();
    }
}
