@extends('layouts.app')
@section('titulo', 'Salas')
@section('conteudo')
<div class="page-head">
    <div><h1>Salas</h1><p>Salas do complexo e suas plantas de assentos.</p></div>
    <a class="btn btn-primary" href="{{ route('salas.create') }}">@include('partials.icone', ['n' => 'mais']) Cadastrar sala</a>
</div>
<div class="card">
    <table class="table">
        <thead><tr><th>Sala</th><th>Grade</th><th>Capacidade</th><th>Acessíveis</th><th>3D</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        @forelse ($salas as $s)
            <tr class="{{ $s->status === 'inativa' ? 'inativo' : '' }}">
                <td class="titulo">{{ $s->nome }}</td>
                <td>{{ $s->fileiras }} fileiras × {{ $s->assentos_por_fileira }}</td>
                <td>{{ $s->capacidade }} lugares</td>
                <td>{{ $s->acessiveis }}</td>
                <td>{!! $s->suporta_3d ? '<span class="badge badge-info">3D</span>' : '<span class="muted">—</span>' !!}</td>
                <td>
                    @if ($s->status === 'ativa')<span class="badge badge-ok">Ativa</span>
                    @elseif ($s->status === 'manutencao')<span class="badge badge-warn">Manutenção{{ $s->manutencao_ate ? ' até '.$s->manutencao_ate->format('d/m') : '' }}</span>
                    @else<span class="badge">Inativa</span>@endif
                </td>
                <td class="acoes">
                    <a class="btn btn-sm" href="{{ route('salas.planta', $s) }}">Planta</a>
                    <a class="btn btn-sm" href="{{ route('salas.edit', $s) }}">Editar</a>
                    <form method="POST" action="{{ route('salas.destroy', $s) }}" onsubmit="return confirm('Excluir a sala {{ addslashes($s->nome) }}?')">
                        @csrf @method('DELETE')
                        <button class="btn btn-sm btn-ghost btn-danger">Excluir</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Nenhuma sala cadastrada.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
