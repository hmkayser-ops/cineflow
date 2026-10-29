@extends('layouts.app')
@section('titulo', $sessao->filme->titulo)
@section('conteudo')
<div class="page-head">
    <div>
        <div class="crumb"><a href="{{ route('sessoes.index', ['dia' => $sessao->inicio->toDateString()]) }}">Sessões</a> / Detalhes</div>
        <h1>{{ $sessao->filme->titulo }}</h1>
        <p>{{ ucfirst($sessao->inicio->translatedFormat('l, d/m/Y')) }} · {{ $sessao->inicio->format('H:i') }} · Sala {{ $sessao->sala->nome }}</p>
    </div>
    @if ($sessao->status === 'programada')<span class="badge badge-ok">Programada · venda liberada</span>@else<span class="badge badge-danger">Cancelada</span>@endif
</div>

@if (session('recusadas'))
    <div class="alert alert-warn"><strong>Alguns dias da programação em lote não foram criados:</strong>
        <ul>@foreach (session('recusadas') as $dia => $motivo)<li>{{ $dia }} — {{ $motivo }}</li>@endforeach</ul>
    </div>
@endif

<div class="grid-2" style="grid-template-columns: 1fr 1.4fr; align-items:start">
    <div class="stack">
        <div class="card card-pad">
            <dl class="dl">
                <dt>Filme</dt><dd>{{ $sessao->filme->titulo }} <span class="classif classif-{{ $sessao->filme->classificacao }}">{{ $sessao->filme->classificacao }}</span></dd>
                <dt>Duração</dt><dd>{{ $sessao->filme->duracao_formatada }}</dd>
                <dt>Formato</dt><dd>{{ $sessao->descricao }}</dd>
                <dt>Sala</dt><dd>{{ $sessao->sala->nome }}</dd>
                <dt>Início</dt><dd>{{ $sessao->inicio->format('d/m/Y H:i') }}</dd>
                <dt>Sala ocupada até</dt><dd>{{ $sessao->fim->format('H:i') }} <small>(trailers + filme + limpeza)</small></dd>
                <dt>Inteira</dt><dd>R$ {{ number_format($sessao->valor_inteira, 2, ',', '.') }}</dd>
                <dt>Meia-entrada</dt><dd>R$ {{ number_format($sessao->valor_meia, 2, ',', '.') }}</dd>
                <dt>Programada por</dt><dd>{{ $sessao->responsavel->name }} em {{ $sessao->created_at->format('d/m/Y H:i') }}</dd>
            </dl>
        </div>
        @if (auth()->user()->isGerente() && $sessao->status === 'programada')
        <form class="card card-pad" method="POST" action="{{ route('sessoes.cancelar', $sessao) }}" onsubmit="return confirm('Cancelar esta sessão?')">
            @csrf
            <h3 style="margin-bottom:10px">Cancelar sessão</h3>
            <label for="motivo">Motivo (fica registrado na auditoria) *</label>
            <input id="motivo" type="text" name="motivo" value="{{ old('motivo') }}" placeholder="Ex.: falha no projetor">
            @include('partials.erro', ['campo' => 'motivo'])
            <div style="margin-top:12px"><button class="btn btn-danger">Cancelar sessão</button></div>
        </form>
        @endif
    </div>

    <div class="card card-pad">
        <div class="row" style="justify-content:space-between;margin-bottom:16px">
            <h2>Mapa de assentos da sessão</h2>
            <small>{{ $statusAssentos->filter(fn ($s) => $s === 'disponivel')->count() }} disponíveis de {{ $statusAssentos->count() }}</small>
        </div>
        <div class="tela">TELA</div>
        <div class="planta">
            @foreach ($grade as $letra => $assentos)
                <div class="fileira">
                    <span class="letra">{{ $letra }}</span>
                    @foreach ($assentos as $a)
                        @php($st = $statusAssentos[$a->id] ?? null)
                        <span class="seat seat-{{ $a->tipo }} {{ $st && $st !== 'disponivel' ? 'seat-'.$st : '' }}" title="{{ $a->codigo }}">{{ $a->tipo === 'inexistente' ? '' : $a->numero }}</span>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="legenda">
            <span><i class="seat"></i> Disponível</span>
            <span><i class="seat seat-acessivel"></i> Acessibilidade</span>
            <span><i class="seat seat-ocupado"></i> Ocupado</span>
        </div>
    </div>
</div>
@endsection
