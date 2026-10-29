<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Filme extends Model
{
    protected $table = 'filmes';

    protected $attributes = ['ativo' => true];

    public const CLASSIFICACOES = ['L', '10', '12', '14', '16', '18'];

    public const GENEROS = [
        'Ação', 'Animação', 'Aventura', 'Comédia', 'Documentário', 'Drama',
        'Fantasia', 'Ficção científica', 'Musical', 'Romance', 'Suspense', 'Terror',
    ];

    protected $fillable = [
        'titulo', 'titulo_original', 'sinopse', 'genero', 'duracao_min',
        'classificacao', 'distribuidora', 'licenca_inicio', 'licenca_fim', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'licenca_inicio' => 'date',
            'licenca_fim' => 'date',
            'ativo' => 'boolean',
            'duracao_min' => 'integer',
        ];
    }

    public function sessoes(): HasMany
    {
        return $this->hasMany(Sessao::class);
    }

    /** O contrato de exibição cobre a data informada? */
    public function licencaCobre(CarbonInterface $data): bool
    {
        return $data->betweenIncluded(
            $this->licenca_inicio->copy()->startOfDay(),
            $this->licenca_fim->copy()->endOfDay()
        );
    }

    public function getDuracaoFormatadaAttribute(): string
    {
        return intdiv($this->duracao_min, 60).'h'.str_pad((string) ($this->duracao_min % 60), 2, '0', STR_PAD_LEFT);
    }
}
