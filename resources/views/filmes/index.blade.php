@extends('layouts.app')
@section('titulo', 'Filmes')
@section('conteudo')
<div class="page-head">
    <div><h1>Filmes</h1><p>Catálogo de filmes e período de licença de exibição.</p></div>
    <a class="btn btn-primary" href="{{ route('filmes.create') }}">@include('partials.icone', ['n' => 'mais']) Cadastrar filme</a>
</div>

<form class="toolbar" method="GET">
    <input type="search" name="q" value="{{ $busca }}" placeholder="Buscar por título ou gênero">
    <button class="btn">Buscar</button>
    @if ($busca)<a class="btn btn-ghost" href="{{ route('filmes.index') }}">Limpar</a>@endif
</form>

<div class="card">
    <table class="table">
        <thead><tr><th>Título</th><th>Gênero</th><th>Duração</th><th>Class.</th><th>Licença</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        @forelse ($filmes as $f)
            @php($vigente = $f->licencaCobre(now()))
            <tr class="{{ $f->ativo ? '' : 'inativo' }}">
                <td><div class="titulo">{{ $f->titulo }}</div>@if ($f->titulo_original)<small>{{ $f->titulo_original }}</small>@endif</td>
                <td>{{ $f->genero }}</td>
                <td>{{ $f->duracao_formatada }}</td>
                <td><span class="classif classif-{{ $f->classificacao }}">{{ $f->classificacao }}</span></td>
                <td>{{ $f->licenca_inicio->format('d/m/Y') }} – {{ $f->licenca_fim->format('d/m/Y') }}</td>
                <td>
                    @if (! $f->ativo)<span class="badge">Inativo</span>
                    @elseif ($vigente)<span class="badge badge-ok">Em cartaz</span>
                    @elseif ($f->licenca_inicio->isFuture())<span class="badge badge-info">Em breve</span>
                    @else<span class="badge badge-warn">Licença expirada</span>@endif
                    @if ($f->sessoes_futuras)<small> · {{ $f->sessoes_futuras }} sessões</small>@endif
                </td>
                <td class="acoes">
                    <a class="btn btn-sm" href="{{ route('filmes.edit', $f) }}">Editar</a>
                    <form method="POST" action="{{ route('filmes.destroy', $f) }}" onsubmit="return confirm('Excluir o filme {{ addslashes($f->titulo) }}?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-ghost btn-danger">Excluir</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Nenhum filme encontrado.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $filmes->links('partials.paginacao') }}
</div>
@endsection
