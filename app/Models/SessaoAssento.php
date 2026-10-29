<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessaoAssento extends Model
{
    protected $table = 'sessao_assentos';

    public $timestamps = false;

    protected $fillable = ['sessao_id', 'assento_id', 'status'];

    public function assento(): BelongsTo
    {
        return $this->belongsTo(Assento::class);
    }

    public function sessao(): BelongsTo
    {
        return $this->belongsTo(Sessao::class);
    }
}
