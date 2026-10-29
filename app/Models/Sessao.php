<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sessao extends Model
{
    protected $table = 'sessoes';

    protected $attributes = ['status' => 'programada'];

    public const FORMATOS = ['2D', '3D'];

    public const IDIOMAS = ['dublado' => 'Dublado', 'legendado' => 'Legendado'];

    protected $fillable = [
        'filme_id', 'sala_id', 'inicio', 'fim', 'formato', 'idioma',
        'valor_inteira', 'valor_meia', 'status', 'programada_por',
    ];

    protected function casts(): array
    {
        return [
            'inicio' => 'datetime',
            'fim' => 'datetime',
            'valor_inteira' => 'decimal:2',
            'valor_meia' => 'decimal:2',
        ];
    }

    public function filme(): BelongsTo
    {
        return $this->belongsTo(Filme::class);
    }

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'programada_por');
    }

    public function assentos(): HasMany
    {
        return $this->hasMany(SessaoAssento::class);
    }

    public function scopeProgramadas(Builder $query): Builder
    {
        return $query->where('status', 'programada');
    }

    /** Sessões da mesma sala que se sobrepõem ao intervalo [inicio, fim). */
    public function scopeSobrepostas(Builder $query, int $salaId, $inicio, $fim): Builder
    {
        return $query->programadas()
            ->where('sala_id', $salaId)
            ->where('inicio', '<', $fim)
            ->where('fim', '>', $inicio);
    }

    public function getDescricaoAttribute(): string
    {
        return $this->formato.' · '.(self::IDIOMAS[$this->idioma] ?? $this->idioma);
    }
}
