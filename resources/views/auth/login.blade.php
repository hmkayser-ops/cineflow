<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · CineFlow</title>
    <link rel="stylesheet" href="{{ asset('css/cineflow.css') }}">
</head>
<body>
<div class="login-wrap">
    <section class="login-art">
        <div class="brand"><span class="brand-mark">@include('partials.icone', ['n' => 'logo'])</span> CineFlow</div>
        <div>
            <h1>Da programação à poltrona.</h1>
            <p>Gestão de sessões, salas e ingressos para redes de cinema.</p>
        </div>
        <small style="color:#6f6a74">Laboratório de Engenharia de Software · Unilasalle</small>
        <div class="film"><div></div><div></div><div></div><div></div></div>
    </section>
    <section class="login-form">
        <form method="POST" action="{{ route('login.entrar') }}" novalidate>
            @csrf
            <h2>Entrar</h2>
            <p class="muted" style="margin:0 0 22px">Acesso restrito a funcionários do cinema.</p>

            @if ($errors->any())
                <div class="alert alert-erro">{{ $errors->first() }}</div>
            @endif

            <div style="margin-bottom:14px">
                <label for="email">E-mail</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </div>
            <div style="margin-bottom:14px">
                <label for="password">Senha</label>
                <input id="password" type="password" name="password" autocomplete="current-password" required>
            </div>
            <label class="check" style="margin-bottom:18px"><input type="checkbox" name="lembrar" value="1"> Manter conectado</label>
            <button class="btn btn-primary" type="submit" style="width:100%;justify-content:center;padding:11px">Entrar</button>

            @if (app()->environment('local'))
            <div class="demo">
                <strong>Usuários de demonstração</strong> (senha <code>cineflow123</code>)<br>
                <code>gerente@cineflow.test</code> · <code>atendente@cineflow.test</code> · <code>portaria@cineflow.test</code>
            </div>
            @endif
        </form>
    </section>
</div>
</body>
</html>
