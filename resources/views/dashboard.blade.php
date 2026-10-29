@extends('layouts.app')
@section('titulo', 'Painel')
@section('conteudo')
<div class="page-head">
    <div>
        <h1>Olá, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
        <p>{{ ucfirst(now()->translatedFormat('l, d \d\e F \d\e Y')) }}</p>
    </div>
    @if (auth()->user()->isGerente())
        <a class="btn btn-primary" href="{{ route('sessoes.create') }}">@include('partials.icone', ['n' => 'mais']) Programar sessão</a>
    @endif
</div>

<div class="grid-3" style="margin-bottom:22px">
    <div class="card stat"><div class="num">{{ $totais['filmes'] }}</div><div class="lbl">filmes em cartaz</div></div>
    <div class="card stat"><div class="num">{{ $totais['salas'] }}</div><div class="lbl">salas ativas</div></div>
    <div class="card stat"><div class="num">{{ $totais['sessoesSemana'] }}</div><div class="lbl">sessões nos próximos 7 dias</div></div>
</div>

<div class="card">
    <div class="card-head"><h2>Sessões de hoje</h2><a href="{{ route('sessoes.index') }}">Ver programação completa</a></div>
    @include('sessoes._lista', ['sessoes' => $sessoesHoje, 'vazio' => 'Nenhuma sessão programada para hoje.'])
</div>
@endsection
