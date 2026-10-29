@extends('layouts.app')
@section('titulo', 'Auditoria')
@section('conteudo')
<div class="page-head">
    <div><h1>Auditoria</h1><p>Registro das operações realizadas no sistema (quem, o quê e quando).</p></div>
</div>
<form class="toolbar" method="GET">
    <select name="entidade" onchange="this.form.submit()">
        <option value="">Todas as entidades</option>
        @foreach (['sessao' => 'Sessões', 'filme' => 'Filmes', 'sala' => 'Salas', 'usuario' => 'Usuários'] as $v => $r)
            <option value="{{ $v }}" @selected($entidade === $v)>{{ $r }}</option>
        @endforeach
    </select>
</form>
<div class="card">
    <table class="table">
        <thead><tr><th>Data/hora</th><th>Usuário</th><th>Ação</th><th>Entidade</th><th>Detalhes</th></tr></thead>
        <tbody>
        @forelse ($registros as $r)
            <tr>
                <td style="white-space:nowrap">{{ $r->created_at->format('d/m/Y H:i:s') }}</td>
                <td>{{ $r->usuario?->name ?? '—' }}</td>
                <td><span class="badge">{{ str_replace('_', ' ', $r->acao) }}</span></td>
                <td>{{ $r->entidade }} @if ($r->entidade_id)<small>#{{ $r->entidade_id }}</small>@endif</td>
                <td><small>@foreach ($r->detalhes ?? [] as $k => $v){{ $k }}: {{ is_array($v) ? implode(', ', $v) : $v }}@if (! $loop->last) · @endif @endforeach</small></td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">Nenhum registro.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $registros->links('partials.paginacao') }}
</div>
@endsection
