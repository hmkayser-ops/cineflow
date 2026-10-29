<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;
use App\Models\Filme;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** RF02 – Cadastrar filme (caso de uso "Cadastrar filme"). */
class FilmeController extends Controller
{
    public function index(Request $request)
    {
        $busca = trim((string) $request->query('q'));

        $filmes = Filme::query()
            ->when($busca !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('titulo', 'like', "%{$busca}%")
                ->orWhere('titulo_original', 'like', "%{$busca}%")
                ->orWhere('genero', 'like', "%{$busca}%")))
            ->withCount(['sessoes as sessoes_futuras' => fn ($q) => $q->programadas()->where('inicio', '>', now())])
            ->orderByDesc('ativo')
            ->orderBy('titulo')
            ->paginate(10)
            ->withQueryString();

        return view('filmes.index', compact('filmes', 'busca'));
    }

    public function create()
    {
        return view('filmes.form', ['filme' => new Filme(['ativo' => true])]);
    }

    public function store(Request $request)
    {
        $filme = Filme::create($this->validar($request));
        Auditoria::registrar('criar', 'filme', $filme->id, ['titulo' => $filme->titulo]);

        return redirect()->route('filmes.index')->with('sucesso', "Filme \"{$filme->titulo}\" cadastrado.");
    }

    public function edit(Filme $filme)
    {
        return view('filmes.form', compact('filme'));
    }

    public function update(Request $request, Filme $filme)
    {
        $filme->update($this->validar($request, $filme));
        Auditoria::registrar('alterar', 'filme', $filme->id, ['titulo' => $filme->titulo, 'campos' => array_keys($filme->getChanges())]);

        return redirect()->route('filmes.index')->with('sucesso', "Filme \"{$filme->titulo}\" atualizado.");
    }

    public function destroy(Filme $filme)
    {
        // Filme com sessões não é excluído: é desativado, preservando o histórico
        if ($filme->sessoes()->exists()) {
            $filme->update(['ativo' => false]);
            Auditoria::registrar('desativar', 'filme', $filme->id, ['titulo' => $filme->titulo]);

            return back()->with('aviso', "O filme \"{$filme->titulo}\" possui sessões registradas e foi desativado em vez de excluído.");
        }

        Auditoria::registrar('excluir', 'filme', $filme->id, ['titulo' => $filme->titulo]);
        $filme->delete();

        return back()->with('sucesso', 'Filme excluído.');
    }

    private function validar(Request $request, ?Filme $filme = null): array
    {
        $dados = $request->validate([
            'titulo' => ['required', 'string', 'max:150',
                Rule::unique('filmes', 'titulo')->ignore($filme?->id)],
            'titulo_original' => ['nullable', 'string', 'max:150'],
            'sinopse' => ['nullable', 'string', 'max:2000'],
            'genero' => ['required', Rule::in(Filme::GENEROS)],
            'duracao_min' => ['required', 'integer', 'min:1', 'max:400'],
            'classificacao' => ['required', Rule::in(Filme::CLASSIFICACOES)],
            'distribuidora' => ['nullable', 'string', 'max:100'],
            'licenca_inicio' => ['required', 'date'],
            'licenca_fim' => ['required', 'date', 'after_or_equal:licenca_inicio'],
            'ativo' => ['sometimes', 'boolean'],
        ], [], [
            'duracao_min' => 'duração',
            'licenca_inicio' => 'início da licença',
            'licenca_fim' => 'fim da licença',
            'classificacao' => 'classificação indicativa',
            'genero' => 'gênero',
            'titulo' => 'título',
            'titulo_original' => 'título original',
        ]);
        $dados['ativo'] = $request->boolean('ativo');

        return $dados;
    }
}
