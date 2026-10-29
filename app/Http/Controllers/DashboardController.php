<?php

namespace App\Http\Controllers;

use App\Models\Filme;
use App\Models\Sala;
use App\Models\Sessao;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $hoje = Sessao::programadas()
            ->whereDate('inicio', today())
            ->with(['filme', 'sala'])
            ->orderBy('inicio')
            ->get();

        return view('dashboard', [
            'sessoesHoje' => $hoje,
            'totais' => [
                'filmes' => Filme::where('ativo', true)->count(),
                'salas' => Sala::where('status', 'ativa')->count(),
                'sessoesSemana' => Sessao::programadas()->whereBetween('inicio', [now(), now()->addDays(7)])->count(),
            ],
        ]);
    }
}
