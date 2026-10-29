@extends('layouts.app')
@section('titulo', 'Usuários')
@section('conteudo')
<div class="page-head">
    <div><h1>Usuários</h1><p>Funcionários com acesso ao sistema e seus perfis.</p></div>
    <a class="btn btn-primary" href="{{ route('usuarios.create') }}">@include('partials.icone', ['n' => 'mais']) Novo usuário</a>
</div>
<div class="card">
    <table class="table">
        <thead><tr><th>Nome</th><th>E-mail</th><th>Perfil</th><th>Situação</th><th></th></tr></thead>
        <tbody>
        @foreach ($usuarios as $usr)
            <tr class="{{ $usr->ativo ? '' : 'inativo' }}">
                <td class="titulo">{{ $usr->name }}</td>
                <td>{{ $usr->email }}</td>
                <td><span class="badge {{ $usr->isGerente() ? 'badge-danger' : 'badge-info' }}">{{ $usr->perfil_nome }}</span></td>
                <td>{!! $usr->ativo ? '<span class="badge badge-ok">Ativo</span>' : '<span class="badge">Desativado</span>' !!}</td>
                <td class="acoes"><a class="btn btn-sm" href="{{ route('usuarios.edit', $usr) }}">Editar</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endsection
