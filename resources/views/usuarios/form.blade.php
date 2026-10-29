@extends('layouts.app')
@section('titulo', $usuario->exists ? 'Editar usuário' : 'Novo usuário')
@section('conteudo')
<div class="page-head">
    <div>
        <div class="crumb"><a href="{{ route('usuarios.index') }}">Usuários</a> / {{ $usuario->exists ? 'Editar' : 'Novo' }}</div>
        <h1>{{ $usuario->exists ? $usuario->name : 'Novo usuário' }}</h1>
    </div>
</div>
<form class="card card-pad" method="POST" action="{{ $usuario->exists ? route('usuarios.update', $usuario) : route('usuarios.store') }}" novalidate>
    @csrf
    @if ($usuario->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="col-3"><label for="name">Nome *</label>
            <input id="name" type="text" name="name" value="{{ old('name', $usuario->name) }}" class="@error('name') is-invalid @enderror">
            @include('partials.erro', ['campo' => 'name'])</div>
        <div class="col-3"><label for="email">E-mail *</label>
            <input id="email" type="email" name="email" value="{{ old('email', $usuario->email) }}" class="@error('email') is-invalid @enderror">
            @include('partials.erro', ['campo' => 'email'])</div>
        <div class="col-2"><label for="perfil">Perfil *</label>
            <select id="perfil" name="perfil">
                @foreach (\App\Models\User::PERFIS as $v => $r)<option value="{{ $v }}" @selected(old('perfil', $usuario->perfil) === $v)>{{ $r }}</option>@endforeach
            </select>
            @include('partials.erro', ['campo' => 'perfil'])</div>
        <div class="col-2"><label for="password">Senha {{ $usuario->exists ? '' : '*' }}</label>
            <input id="password" type="password" name="password" autocomplete="new-password" class="@error('password') is-invalid @enderror">
            <div class="hint">{{ $usuario->exists ? 'Deixe em branco para manter a atual. ' : '' }}Mínimo 8 caracteres, com letras e números.</div>
            @include('partials.erro', ['campo' => 'password'])</div>
        <div class="col-2"><label for="password_confirmation">Confirmar senha</label>
            <input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"></div>
        <div class="col-6">
            <input type="hidden" name="ativo" value="0">
            <label class="check"><input type="checkbox" name="ativo" value="1" @checked(old('ativo', $usuario->ativo))> Usuário ativo</label>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ route('usuarios.index') }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">@include('partials.icone', ['n' => 'check']) Salvar usuário</button>
    </div>
</form>
@endsection
