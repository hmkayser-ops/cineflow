<?php

namespace App\Http\Controllers;

use App\Models\Assento;
use App\Models\Auditoria;
use App\Models\Sala;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** RF03 – Cadastrar sala com planta de assentos (caso de uso "Cadastrar sala"). */
class SalaController extends Controller
{
    public function index()
    {
        $salas = Sala::withCount([
            'assentos as capacidade' => fn ($q) => $q->where('tipo', '!=', Assento::TIPO_INEXISTENTE),
            'assentos as acessiveis' => fn ($q) => $q->where('tipo', Assento::TIPO_ACESSIVEL),
        ])->orderBy('nome')->get();

        return view('salas.index', compact('salas'));
    }

    public function create()
    {
        return view('salas.form', ['sala' => new Sala(['fileiras' => 8, 'assentos_por_fileira' => 12, 'status' => 'ativa'])]);
    }

    public function store(Request $request)
    {
        $sala = DB::transaction(function () use ($request) {
            $sala = Sala::create($this->validar($request));
            $sala->gerarPlanta();

            return $sala;
        });

        Auditoria::registrar('criar', 'sala', $sala->id, ['nome' => $sala->nome, 'grade' => "{$sala->fileiras}x{$sala->assentos_por_fileira}"]);

        return redirect()->route('salas.planta', $sala)
            ->with('sucesso', "Sala {$sala->nome} criada com {$sala->capacidade} assentos. Ajuste a planta se necessário.");
    }

    public function edit(Sala $sala)
    {
        return view('salas.form', compact('sala'));
    }

    public function update(Request $request, Sala $sala)
    {
        $dados = $this->validar($request, $sala);
        $mudouGrade = $dados['fileiras'] != $sala->fileiras || $dados['assentos_por_fileira'] != $sala->assentos_por_fileira;

        if ($mudouGrade && $sala->possuiSessoesFuturas()) {
            return back()->withInput()->withErrors([
                'fileiras' => 'A grade não pode ser alterada enquanto houver sessões futuras programadas nesta sala.',
            ]);
        }

        DB::transaction(function () use ($sala, $dados, $mudouGrade) {
            $sala->update($dados);
            if ($mudouGrade) {
                $sala->gerarPlanta();
            }
        });

        Auditoria::registrar('alterar', 'sala', $sala->id, ['nome' => $sala->nome, 'campos' => array_keys($sala->getChanges())]);

        return redirect()->route('salas.index')->with('sucesso', "Sala {$sala->nome} atualizada.");
    }

    public function destroy(Sala $sala)
    {
        if ($sala->sessoes()->exists()) {
            $sala->update(['status' => 'inativa']);
            Auditoria::registrar('desativar', 'sala', $sala->id, ['nome' => $sala->nome]);

            return back()->with('aviso', "A sala {$sala->nome} possui sessões registradas e foi marcada como inativa.");
        }

        Auditoria::registrar('excluir', 'sala', $sala->id, ['nome' => $sala->nome]);
        $sala->delete();

        return back()->with('sucesso', 'Sala excluída.');
    }

    /** Editor visual da planta de assentos. */
    public function planta(Sala $sala)
    {
        return view('salas.planta', ['sala' => $sala, 'grade' => $sala->grade()]);
    }

    public function salvarPlanta(Request $request, Sala $sala)
    {
        if ($sala->possuiSessoesFuturas()) {
            return back()->withErrors(['planta' => 'A planta não pode ser alterada enquanto houver sessões futuras programadas nesta sala.']);
        }

        $tipos = $request->validate([
            'tipos' => ['required', 'array'],
            'tipos.*' => [Rule::in(array_keys(Assento::TIPOS))],
        ])['tipos'];

        $assentos = $sala->assentos()->get();
        $restantes = $assentos->filter(fn ($a) => ($tipos[$a->id] ?? $a->tipo) !== Assento::TIPO_INEXISTENTE)->count();
        if ($restantes === 0) {
            return back()->withErrors(['planta' => 'A sala precisa ter pelo menos um assento.']);
        }

        DB::transaction(function () use ($assentos, $tipos) {
            foreach ($assentos as $assento) {
                $novo = $tipos[$assento->id] ?? $assento->tipo;
                if ($novo !== $assento->tipo) {
                    $assento->update(['tipo' => $novo]);
                }
            }
        });

        Auditoria::registrar('alterar_planta', 'sala', $sala->id, ['nome' => $sala->nome, 'capacidade' => $sala->capacidade]);

        return redirect()->route('salas.index')->with('sucesso', "Planta da sala {$sala->nome} salva: {$sala->capacidade} assentos.");
    }

    private function validar(Request $request, ?Sala $sala = null): array
    {
        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:60', Rule::unique('salas', 'nome')->ignore($sala?->id)],
            'fileiras' => ['required', 'integer', 'min:1', 'max:26'],
            'assentos_por_fileira' => ['required', 'integer', 'min:1', 'max:40'],
            'suporta_3d' => ['sometimes', 'boolean'],
            'status' => ['required', Rule::in(array_keys(Sala::STATUS))],
            'manutencao_ate' => ['nullable', 'date'],
        ], [], [
            'assentos_por_fileira' => 'assentos por fileira',
            'manutencao_ate' => 'previsão de liberação',
        ]);
        $dados['suporta_3d'] = $request->boolean('suporta_3d');
        if ($dados['status'] !== 'manutencao') {
            $dados['manutencao_ate'] = null;
        }

        return $dados;
    }
}
