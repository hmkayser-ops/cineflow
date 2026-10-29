<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class TabelaPreco extends Model
{
    protected $table = 'tabelas_preco';

    protected $fillable = ['formato', 'valor_inteira', 'vigente_desde'];

    protected function casts(): array
    {
        return ['valor_inteira' => 'decimal:2', 'vigente_desde' => 'date'];
    }

    /** Tabela vigente para o formato na data da sessão (a mais recente já em vigor). */
    public static function vigente(string $formato, CarbonInterface $data): ?self
    {
        return static::where('formato', $formato)
            ->whereDate('vigente_desde', '<=', $data->toDateString())
            ->orderByDesc('vigente_desde')
            ->first();
    }
}
