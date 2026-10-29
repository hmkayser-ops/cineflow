@extends('layouts.app')
@section('titulo', 'Sessões')
@section('conteudo')
<div class="page-head">
    <div><h1>Programação</h1><p>{{ ucfirst($dia->translatedFormat('l, d \d\e F \d\e Y')) }}</p></div>
    @if (auth()->user()->isGerente())
        <a class="btn btn-primary" href="{{ route('sessoes.create') }}">@include('partials.icone', ['n' => 'mais']) Programar sessão</a>
    @endif
</div>

<form class="toolbar" method="GET">
    <div class="dia-nav">
        <a class="btn btn-sm" href="{{ route('sessoes.index', ['dia' => $dia->copy()->subDay()->toDateString(), 'sala' => $salaId]) }}" title="Dia anterior">@include('partials.icone', ['n' => 'voltar'])</a>
        <input type="date" name="dia" value="{{ $dia->toDateString() }}" onchange="this.form.submit()" style="min-width:auto">
        <a class="btn btn-sm" href="{{ route('sessoes.index', ['dia' => $dia->copy()->addDay()->toDateString(), 'sala' => $salaId]) }}" title="Próximo dia">@include('partials.icone', ['n' => 'avancar'])</a>
        <a class="btn btn-sm btn-ghost" href="{{ route('sessoes.index', ['sala' => $salaId]) }}">Hoje</a>
    </div>
    <select name="sala" onchange="this.form.submit()">
        <option value="">Todas as salas</option>
        @foreach ($salas as $s)
            <option value="{{ $s->id }}" @selected($salaId == $s->id)>Sala {{ $s->nome }}</option>
        @endforeach
    </select>
</form>

<div class="card">
    @include('sessoes._lista', ['sessoes' => $sessoes, 'vazio' => 'Nenhuma sessão nesta data.'])
</div>
@endsection
