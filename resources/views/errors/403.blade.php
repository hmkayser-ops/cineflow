<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acesso não permitido · CineFlow</title><link rel="stylesheet" href="{{ asset('css/cineflow.css') }}"></head>
<body><div style="min-height:100vh;display:grid;place-items:center;padding:24px"><div class="card card-pad" style="max-width:440px;text-align:center">
<div class="muted" style="font-size:3rem;font-weight:700">403</div><h1 style="margin:6px 0 10px">Acesso não permitido</h1><p class="muted">{{ $exception->getMessage() ?: 'Seu perfil não tem permissão para acessar esta página.' }}</p>
<a class="btn btn-primary" href="{{ url('/') }}">Ir para o painel</a></div></div></body></html>
