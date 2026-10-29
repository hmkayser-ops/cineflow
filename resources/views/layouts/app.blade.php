<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Painel') · CineFlow</title>
    <link rel="stylesheet" href="{{ asset('css/cineflow.css') }}">
</head>
<body>
@php($u = auth()->user())
<div class="app">
    <aside class="side">
        <div class="brand"><span class="brand-mark">@include('partials.icone', ['n' => 'logo'])</span> CineFlow</div>
        <nav class="nav">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'ativo' : '' }}">@include('partials.icone', ['n' => 'painel']) Painel</a>
            <a href="{{ route('sessoes.index') }}" class="{{ request()->routeIs('sessoes.*') ? 'ativo' : '' }}">@include('partials.icone', ['n' => 'sessoes']) Sessões</a>
            @if ($u->isGerente())
                <div class="nav-label">Cadastros</div>
                <a href="{{ route('filmes.index') }}" class="{{ request()->routeIs('filmes.*') ? 'ativo' : '' }}">@include('partials.icone', ['n' => 'filmes']) Filmes</a>
                <a href="{{ route('salas.index') }}" class="{{ request()->routeIs('salas.*') ? 'ativo' : '' }}">@include('partials.icone', ['n' => 'salas']) Salas</a>
                <div class="nav-label">Administração</div>
                <a href="{{ route('usuarios.index') }}" class="{{ request()->routeIs('usuarios.*') ? 'ativo' : '' }}">@include('partials.icone', ['n' => 'usuarios']) Usuários</a>
                <a href="{{ route('auditoria') }}" class="{{ request()->routeIs('auditoria') ? 'ativo' : '' }}">@include('partials.icone', ['n' => 'auditoria']) Auditoria</a>
            @endif
        </nav>
        <div class="side-foot">
            <strong>{{ $u->name }}</strong>
            <span class="perfil">{{ $u->perfil_nome }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf <button type="submit">Sair</button></form>
        </div>
    </aside>

    <main class="main">
        @if (session('sucesso'))
            <div class="alert alert-ok">{{ session('sucesso') }}</div>
        @endif
        @if (session('aviso'))
            <div class="alert alert-warn">{{ session('aviso') }}</div>
        @endif
        @if (session('erro_regra'))
            <div class="alert alert-erro"><span class="fa-tag">{{ session('erro_regra')['codigo'] }}</span>{{ session('erro_regra')['mensagem'] }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-erro"><strong>Corrija os campos destacados:</strong>
                <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @yield('conteudo')
    </main>
</div>
@stack('scripts')
</body>
</html>
