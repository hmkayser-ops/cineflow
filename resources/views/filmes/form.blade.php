@extends('layouts.app')
@section('titulo', $filme->exists ? 'Editar filme' : 'Cadastrar filme')
@section('conteudo')
<div class="page-head">
    <div>
        <div class="crumb"><a href="{{ route('filmes.index') }}">Filmes</a> / {{ $filme->exists ? 'Editar' : 'Novo' }}</div>
        <h1>{{ $filme->exists ? $filme->titulo : 'Cadastrar filme' }}</h1>
    </div>
</div>

<form class="card card-pad" method="POST" action="{{ $filme->exists ? route('filmes.update', $filme) : route('filmes.store') }}" novalidate>
    @csrf
    @if ($filme->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="col-3">
            <label for="titulo">Título *</label>
            <input id="titulo" type="text" name="titulo" value="{{ old('titulo', $filme->titulo) }}" class="@error('titulo') is-invalid @enderror" required>
            @include('partials.erro', ['campo' => 'titulo'])
        </div>
        <div class="col-3">
            <label for="titulo_original">Título original</label>
            <input id="titulo_original" type="text" name="titulo_original" value="{{ old('titulo_original', $filme->titulo_original) }}">
        </div>
        <div class="col-2">
            <label for="genero">Gênero *</label>
            <select id="genero" name="genero" class="@error('genero') is-invalid @enderror">
                <option value="">Selecione</option>
                @foreach (\App\Models\Filme::GENEROS as $g)
                    <option @selected(old('genero', $filme->genero) === $g)>{{ $g }}</option>
                @endforeach
            </select>
            @include('partials.erro', ['campo' => 'genero'])
        </div>
        <div class="col-2">
            <label for="duracao_min">Duração (minutos) *</label>
            <input id="duracao_min" type="number" min="1" max="400" name="duracao_min" value="{{ old('duracao_min', $filme->duracao_min) }}" class="@error('duracao_min') is-invalid @enderror">
            <div class="hint">Usada no cálculo do término das sessões.</div>
            @include('partials.erro', ['campo' => 'duracao_min'])
        </div>
        <div class="col-2">
            <label for="classificacao">Classificação indicativa *</label>
            <select id="classificacao" name="classificacao" class="@error('classificacao') is-invalid @enderror">
                <option value="">Selecione</option>
                @foreach (\App\Models\Filme::CLASSIFICACOES as $c)
                    <option value="{{ $c }}" @selected(old('classificacao', $filme->classificacao) === $c)>{{ $c === 'L' ? 'Livre' : $c.' anos' }}</option>
                @endforeach
            </select>
            @include('partials.erro', ['campo' => 'classificacao'])
        </div>
        <div class="col-6">
            <label for="sinopse">Sinopse</label>
            <textarea id="sinopse" name="sinopse">{{ old('sinopse', $filme->sinopse) }}</textarea>
        </div>
        <div class="col-2">
            <label for="distribuidora">Distribuidora</label>
            <input id="distribuidora" type="text" name="distribuidora" value="{{ old('distribuidora', $filme->distribuidora) }}">
        </div>
        <div class="col-2">
            <label for="licenca_inicio">Licença de exibição — início *</label>
            <input id="licenca_inicio" type="date" name="licenca_inicio" value="{{ old('licenca_inicio', $filme->licenca_inicio?->format('Y-m-d')) }}" class="@error('licenca_inicio') is-invalid @enderror">
            @include('partials.erro', ['campo' => 'licenca_inicio'])
        </div>
        <div class="col-2">
            <label for="licenca_fim">Licença de exibição — fim *</label>
            <input id="licenca_fim" type="date" name="licenca_fim" value="{{ old('licenca_fim', $filme->licenca_fim?->format('Y-m-d')) }}" class="@error('licenca_fim') is-invalid @enderror">
            <div class="hint">Sessões só podem ser programadas dentro deste período.</div>
            @include('partials.erro', ['campo' => 'licenca_fim'])
        </div>
        <div class="col-6">
            <input type="hidden" name="ativo" value="0">
            <label class="check"><input type="checkbox" name="ativo" value="1" @checked(old('ativo', $filme->ativo))> Filme ativo (disponível para programação)</label>
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ route('filmes.index') }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">@include('partials.icone', ['n' => 'check']) Salvar filme</button>
    </div>
</form>
@endsection
