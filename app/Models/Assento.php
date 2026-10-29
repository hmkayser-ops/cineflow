<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Assento extends Model
{
    protected $table = 'assentos';

    public $timestamps = false;

    public const TIPO_PADRAO = 'padrao';
    public const TIPO_ACESSIVEL = 'acessivel';
    public const TIPO_INEXISTENTE = 'inexistente';

    public const TIPOS = [
        self::TIPO_PADRAO => 'Padrão',
        self::TIPO_ACESSIVEL => 'Acessibilidade',
        self::TIPO_INEXISTENTE => 'Corredor / sem assento',
    ];

    protected $fillable = ['sala_id', 'fileira', 'numero', 'tipo'];

    public function sala(): BelongsTo
    {
        return $this->belongsTo(Sala::class);
    }

    public function getCodigoAttribute(): string
    {
        return $this->fileira.$this->numero;
    }
}
