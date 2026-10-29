@extends('layouts.app')
@section('titulo', 'Planta da sala '.$sala->nome)
@section('conteudo')
<div class="page-head">
    <div>
        <div class="crumb"><a href="{{ route('salas.index') }}">Salas</a> / Planta</div>
        <h1>Planta da sala {{ $sala->nome }}</h1>
        <p>Clique em uma posição para alternar entre <strong>assento padrão</strong>, <strong>acessibilidade</strong> e <strong>corredor</strong>.</p>
    </div>
    <div class="card stat" style="padding:10px 18px"><div class="num" id="cap" style="font-size:1.5rem">{{ $sala->capacidade }}</div><div class="lbl">lugares</div></div>
</div>
@error('planta')<div class="alert alert-erro">{{ $message }}</div>@enderror

<form class="card card-pad" method="POST" action="{{ route('salas.planta.salvar', $sala) }}">
    @csrf @method('PUT')
    <div class="tela">TELA</div>
    <div class="planta">
        @foreach ($grade as $letra => $assentos)
            <div class="fileira">
                <span class="letra">{{ $letra }}</span>
                @foreach ($assentos as $a)
                    <button type="button" class="seat seat-{{ $a->tipo }}" data-id="{{ $a->id }}" title="{{ $a->codigo }}">{{ $a->numero }}</button>
                    <input type="hidden" name="tipos[{{ $a->id }}]" value="{{ $a->tipo }}" id="t{{ $a->id }}">
                @endforeach
                <span class="letra">{{ $letra }}</span>
            </div>
        @endforeach
    </div>
    <div class="legenda">
        <span><i class="seat"></i> Padrão</span>
        <span><i class="seat seat-acessivel"></i> Acessibilidade</span>
        <span><i class="seat seat-inexistente"></i> Corredor</span>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ route('salas.index') }}">Voltar</a>
        <button class="btn btn-primary" type="submit">@include('partials.icone', ['n' => 'check']) Salvar planta</button>
    </div>
</form>
@push('scripts')
<script>
    const ciclo = ['padrao', 'acessivel', 'inexistente'];
    const cap = document.getElementById('cap');
    document.querySelectorAll('button.seat').forEach(b => b.addEventListener('click', () => {
        const campo = document.getElementById('t' + b.dataset.id);
        const novo = ciclo[(ciclo.indexOf(campo.value) + 1) % ciclo.length];
        b.classList.remove('seat-' + campo.value); b.classList.add('seat-' + novo);
        campo.value = novo;
        cap.textContent = [...document.querySelectorAll('input[name^="tipos"]')].filter(i => i.value !== 'inexistente').length;
    }));
</script>
@endpush
@endsection
