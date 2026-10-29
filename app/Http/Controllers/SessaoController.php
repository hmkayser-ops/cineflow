<?php

namespace App\Http\Controllers;

use App\Models\Filme;
use App\Models\Sala;
use App\Models\Sessao;
use App\Services\ProgramacaoException;
use App\Services\ProgramacaoSessaoService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** RF04 – Programar sessão (caso de uso "Programar sessão"). */
class SessaoController extends Controller
{
    public function __construct(private ProgramacaoSessaoService $servico)
    {
    }

    /** Grade de programação por dia, agrupada por sala. */
    public function index(Request $request)
    {
        $dia = $request->filled('dia') ? Carbon::parse($request->query('dia')) : today();
        $salaId = $request->query('sala');

        $sessoes = Sessao::whereDate('inicio', $dia)
            ->when($salaId, fn ($q) => $q->where('sala_id', $salaId))
            ->with(['filme', 'sala'])
            ->orderBy('inicio')
            ->get();

        return view('sessoes.index', [
            'dia' => $dia,
            'sessoes' => $sessoes,
            'salas' => Sala::orderBy('nome')->get(),
            'salaId' => $salaId,
        ]);
    }

    /** Passo 2: formulário com filmes cadastrados e salas ativas. */
    public function create()
    {
        return view('sessoes.form', [
            'filmes' => Filme::where('ativo', true)->orderBy('titulo')->get(),
            'salas' => Sala::where('status', '!=', 'inativa')->orderBy('nome')->get(),
            'config' => config('cineflow'),
        ]);
    }

    /**
     * Passos 5 a 7 sem gravar nada: calcula término, verifica disponibilidade
     * e retorna o preço padrão. Usado pelo botão "Verificar disponibilidade".
     */
    public function verificar(Request $request)
    {
        $dados = $request->validate([
            'filme_id' => ['required', 'exists:filmes,id'],
            'sala_id' => ['required', 'exists:salas,id'],
            'data' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'formato' => ['required', Rule::in(Sessao::FORMATOS)],
        ]);

        $filme = Filme::find($dados['filme_id']);
        $sala = Sala::find($dados['sala_id']);
        $inicio = Carbon::parse($dados['data'].' '.$dados['hora']);

        try {
            $fim = $this->servico->validar($filme, $sala, $inicio, $dados['formato']);
            $preco = $this->servico->precoPadrao($dados['formato'], $inicio);

            return response()->json([
                'disponivel' => true,
                'termino' => $fim->format('H:i'),
                'termino_data' => $fim->format('d/m/Y'),
                'mensagem' => "Sala livre. A sessão ocupará a sala das {$inicio->format('H:i')} às {$fim->format('H:i')}.",
                'valor_inteira' => number_format($preco['valor_inteira'], 2, '.', ''),
                'valor_meia' => number_format($preco['valor_meia'], 2, '.', ''),
            ]);
        } catch (ProgramacaoException $e) {
            return response()->json([
                'disponivel' => false,
                'codigo' => $e->codigo,
                'mensagem' => $e->getMessage(),
                'sugestao' => $e->contexto['sugestao'] ?? null,
            ]);
        }
    }

    /** Passos 9 a 12. */
    public function store(Request $request)
    {
        $max = config('cineflow.max_dias_replicacao');
        $dados = $request->validate([
            'filme_id' => ['required', Rule::exists('filmes', 'id')->where('ativo', true)],
            'sala_id' => ['required', 'exists:salas,id'],
            'data' => ['required', 'date_format:Y-m-d'],
            'hora' => ['required', 'date_format:H:i'],
            'formato' => ['required', Rule::in(Sessao::FORMATOS)],
            'idioma' => ['required', Rule::in(array_keys(Sessao::IDIOMAS))],
            'valor_inteira' => ['required', 'numeric', 'min:0.01', 'max:999'],
            'valor_meia' => ['required', 'numeric', 'min:0', 'lte:valor_inteira'],
            'replicar_dias' => ['nullable', 'integer', 'min:0', "max:{$max}"],
        ], [
            'valor_meia.lte' => 'O valor da meia-entrada não pode ser maior que o da inteira.',
        ], [
            'filme_id' => 'filme',
            'sala_id' => 'sala',
            'valor_inteira' => 'valor da inteira',
            'valor_meia' => 'valor da meia-entrada',
            'replicar_dias' => 'dias de replicação',
        ]);

        $dados['inicio'] = $dados['data'].' '.$dados['hora'].':00';

        try {
            $resultado = $this->servico->programarEmLote($dados, (int) ($dados['replicar_dias'] ?? 0), $request->user());
        } catch (ProgramacaoException $e) {
            // FA1 a FA4: retorna ao formulário preservando o que foi digitado
            return back()->withInput()->with('erro_regra', ['codigo' => $e->codigo, 'mensagem' => $e->getMessage()]);
        }

        $principal = $resultado['criadas'][0];
        $msg = sprintf(
            'Sessão programada: %s, sala %s, %s das %s às %s. Venda liberada.',
            $principal->filme->titulo,
            $principal->sala->nome,
            $principal->inicio->format('d/m/Y'),
            $principal->inicio->format('H:i'),
            $principal->fim->format('H:i')
        );
        if (count($resultado['criadas']) > 1) {
            $msg .= ' Replicada para mais '.(count($resultado['criadas']) - 1).' dia(s).';
        }

        return redirect()->route('sessoes.show', $principal)
            ->with('sucesso', $msg)
            ->with('recusadas', $resultado['recusadas']);
    }

    public function show(Sessao $sessao)
    {
        $sessao->load(['filme', 'sala', 'responsavel']);
        $status = $sessao->assentos()->pluck('status', 'assento_id');

        return view('sessoes.show', [
            'sessao' => $sessao,
            'grade' => $sessao->sala->grade(),
            'statusAssentos' => $status,
        ]);
    }

    public function cancelar(Request $request, Sessao $sessao)
    {
        $dados = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:255']]);

        if ($sessao->status !== 'programada') {
            return back()->with('aviso', 'Esta sessão já está cancelada.');
        }

        try {
            $this->servico->cancelar($sessao, $dados['motivo']);
        } catch (ProgramacaoException $e) {
            return back()->with('erro_regra', ['codigo' => $e->codigo, 'mensagem' => $e->getMessage()]);
        }

        return redirect()->route('sessoes.index', ['dia' => $sessao->inicio->toDateString()])
            ->with('sucesso', 'Sessão cancelada e retirada da programação.');
    }
}
