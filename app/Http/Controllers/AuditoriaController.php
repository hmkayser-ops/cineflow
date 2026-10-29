<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use Illuminate\Http\Request;

/** RNF05 – Consulta ao registro de auditoria (somente gerência). */
class AuditoriaController extends Controller
{
    public function __invoke(Request $request)
    {
        $entidade = $request->query('entidade');

        $registros = Auditoria::with('usuario')
            ->when($entidade, fn ($q) => $q->where('entidade', $entidade))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('auditoria.index', compact('registros', 'entidade'));
    }
}
