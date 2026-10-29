@extends('layouts.app')
@section('titulo', $sala->exists ? 'Editar sala' : 'Cadastrar sala')
@section('conteudo')
<div class="page-head">
    <div>
        <div class="crumb"><a href="{{ route('salas.index') }}">Salas</a> / {{ $sala->exists ? 'Editar' : 'Nova' }}</div>
        <h1>{{ $sala->exists ? 'Sala '.$sala->nome : 'Cadastrar sala' }}</h1>
    </div>
</div>
<form class="card card-pad" method="POST" action="{{ $sala->exists ? route('salas.update', $sala) : route('salas.store') }}" novalidate>
    @csrf
    @if ($sala->exists) @method('PUT') @endif
    <div class="form-grid">
        <div class="col-2">
            <label for="nome">Nome / número da sala *</label>
            <input id="nome" type="text" name="nome" value="{{ old('nome', $sala->nome) }}" class="@error('nome') is-invalid @enderror" placeholder="Ex.: 1, VIP, IMAX">
            @include('partials.erro', ['campo' => 'nome'])
        </div>
        <div class="col-2">
            <label for="fileiras">Fileiras *</label>
            <input id="fileiras" type="number" min="1" max="26" name="fileiras" value="{{ old('fileiras', $sala->fileiras) }}" class="@error('fileiras') is-invalid @enderror">
            <div class="hint">Identificadas por letras (A a Z).</div>
            @include('partials.erro', ['campo' => 'fileiras'])
        </div>
        <div class="col-2">
            <label for="assentos_por_fileira">Assentos por fileira *</label>
            <input id="assentos_por_fileira" type="number" min="1" max="40" name="assentos_por_fileira" value="{{ old('assentos_por_fileira', $sala->assentos_por_fileira) }}" class="@error('assentos_por_fileira') is-invalid @enderror">
            @include('partials.erro', ['campo' => 'assentos_por_fileira'])
        </div>
        <div class="col-2">
            <label for="status">Situação *</label>
            <select id="status" name="status">
                @foreach (\App\Models\Sala::STATUS as $v => $rotulo)
                    <option value="{{ $v }}" @selected(old('status', $sala->status) === $v)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-2" id="campo-manutencao">
            <label for="manutencao_ate">Previsão de liberação</label>
            <input id="manutencao_ate" type="date" name="manutencao_ate" value="{{ old('manutencao_ate', $sala->manutencao_ate?->format('Y-m-d')) }}">
            <div class="hint">Só para salas em manutenção.</div>
        </div>
        <div class="col-2" style="align-self:center">
            <input type="hidden" name="suporta_3d" value="0">
            <label class="check"><input type="checkbox" name="suporta_3d" value="1" @checked(old('suporta_3d', $sala->suporta_3d))> Sala equipada para 3D</label>
        </div>
    </div>
    @unless ($sala->exists)
        <p class="hint" style="margin-top:16px">Depois de salvar, você poderá marcar na planta os corredores e os assentos de acessibilidade.</p>
    @endunless
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ route('salas.index') }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">@include('partials.icone', ['n' => 'check']) {{ $sala->exists ? 'Salvar sala' : 'Salvar e editar planta' }}</button>
    </div>
</form>
@push('scripts')
<script>
    const st = document.getElementById('status'), cm = document.getElementById('campo-manutencao');
    const sync = () => cm.style.visibility = st.value === 'manutencao' ? 'visible' : 'hidden';
    st.addEventListener('change', sync); sync();
</script>
@endpush
@endsection
