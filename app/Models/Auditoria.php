<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'acao', 'entidade', 'entidade_id', 'detalhes', 'ip'];

    protected function casts(): array
    {
        return ['detalhes' => 'array', 'created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Registra uma operação (RNF05 – auditoria). */
    public static function registrar(string $acao, string $entidade, ?int $entidadeId = null, array $detalhes = []): self
    {
        return static::create([
            'user_id' => auth()->id(),
            'acao' => $acao,
            'entidade' => $entidade,
            'entidade_id' => $entidadeId,
            'detalhes' => $detalhes ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
