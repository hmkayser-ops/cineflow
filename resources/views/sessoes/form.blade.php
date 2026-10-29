@extends('layouts.app')
@section('titulo', 'Programar sessão')
@section('conteudo')
<div class="page-head">
    <div>
        <div class="crumb"><a href="{{ route('sessoes.index') }}">Sessões</a> / Programar</div>
        <h1>Programar sessão</h1>
        <p>O término é calculado com {{ $config['minutos_trailers'] }} min de trailers e {{ $config['minutos_limpeza'] }} min de limpeza da sala.</p>
    </div>
</div>

<form class="card card-pad" method="POST" action="{{ route('sessoes.store') }}" id="form-sessao" novalidate>
    @csrf
    <div class="form-grid">
        <div class="col-3">
            <label for="filme_id">Filme *</label>
            <select id="filme_id" name="filme_id" class="@error('filme_id') is-invalid @enderror">
                <option value="">Selecione o filme</option>
                @foreach ($filmes as $f)
                    <option value="{{ $f->id }}" data-duracao="{{ $f->duracao_min }}" @selected(old('filme_id') == $f->id)>{{ $f->titulo }} ({{ $f->duracao_formatada }})</option>
                @endforeach
            </select>
            @include('partials.erro', ['campo' => 'filme_id'])
        </div>
        <div class="col-3">
            <label for="sala_id">Sala *</label>
            <select id="sala_id" name="sala_id" class="@error('sala_id') is-invalid @enderror">
                <option value="">Selecione a sala</option>
                @foreach ($salas as $s)
                    <option value="{{ $s->id }}" @selected(old('sala_id') == $s->id)>Sala {{ $s->nome }}{{ $s->suporta_3d ? ' · 3D' : '' }}{{ $s->status === 'manutencao' ? ' · em manutenção' : '' }}</option>
                @endforeach
            </select>
            @include('partials.erro', ['campo' => 'sala_id'])
        </div>
        <div class="col-2">
            <label for="data">Data *</label>
            <input id="data" type="date" name="data" min="{{ today()->toDateString() }}" value="{{ old('data', today()->addDay()->toDateString()) }}" class="@error('data') is-invalid @enderror">
            @include('partials.erro', ['campo' => 'data'])
        </div>
        <div class="col-2">
            <label for="hora">Horário de início *</label>
            <input id="hora" type="time" name="hora" step="300" value="{{ old('hora', '19:00') }}" class="@error('hora') is-invalid @enderror">
            @include('partials.erro', ['campo' => 'hora'])
        </div>
        <div class="col-2">
            <label>Formato de exibição *</label>
            <div class="row" style="height:42px">
                <select name="formato" id="formato" style="width:auto">
                    @foreach (\App\Models\Sessao::FORMATOS as $fm)<option @selected(old('formato', '2D') === $fm)>{{ $fm }}</option>@endforeach
                </select>
                <select name="idioma" id="idioma" style="width:auto">
                    @foreach (\App\Models\Sessao::IDIOMAS as $v => $r)<option value="{{ $v }}" @selected(old('idioma', 'dublado') === $v)>{{ $r }}</option>@endforeach
                </select>
            </div>
        </div>

        <div class="col-6 checker">
            <div class="row" style="justify-content:space-between">
                <div><h3>Verificar disponibilidade da sala</h3><small>Confere manutenção, suporte a 3D, licença do filme e conflito de horário antes de salvar.</small></div>
                <button type="button" class="btn" id="btn-verificar">Verificar</button>
            </div>
            <div class="res" id="resultado" aria-live="polite"></div>
            <div class="timeline" id="timeline" hidden></div>
        </div>

        <div class="col-2">
            <label for="valor_inteira">Valor da inteira *</label>
            <div class="prefix"><span>R$</span><input id="valor_inteira" type="number" step="0.01" min="0.01" name="valor_inteira" value="{{ old('valor_inteira') }}" class="@error('valor_inteira') is-invalid @enderror"></div>
            <div class="hint">Preenchido pela tabela de preços vigente; pode ser ajustado.</div>
            @include('partials.erro', ['campo' => 'valor_inteira'])
        </div>
        <div class="col-2">
            <label for="valor_meia">Valor da meia-entrada *</label>
            <div class="prefix"><span>R$</span><input id="valor_meia" type="number" step="0.01" min="0" name="valor_meia" value="{{ old('valor_meia') }}" class="@error('valor_meia') is-invalid @enderror"></div>
            @include('partials.erro', ['campo' => 'valor_meia'])
        </div>
        <div class="col-2">
            <label for="replicar_dias">Repetir nos dias seguintes</label>
            <input id="replicar_dias" type="number" min="0" max="{{ $config['max_dias_replicacao'] }}" name="replicar_dias" value="{{ old('replicar_dias', 0) }}">
            <div class="hint">Programação em lote: mesmo horário por mais N dias (0 = só esta data).</div>
            @include('partials.erro', ['campo' => 'replicar_dias'])
        </div>
    </div>
    <div class="form-actions">
        <a class="btn btn-ghost" href="{{ route('sessoes.index') }}">Cancelar</a>
        <button class="btn btn-primary" type="submit">@include('partials.icone', ['n' => 'check']) Confirmar programação</button>
    </div>
</form>

@push('scripts')
<script>
(() => {
    const f = document.getElementById('form-sessao');
    const res = document.getElementById('resultado'), tl = document.getElementById('timeline');
    const inteira = document.getElementById('valor_inteira'), meia = document.getElementById('valor_meia');
    const T = {{ (int) $config['minutos_trailers'] }}, L = {{ (int) $config['minutos_limpeza'] }};
    let precoManual = !!inteira.value;
    inteira.addEventListener('input', () => precoManual = true);
    meia.addEventListener('input', () => precoManual = true);

    async function verificar() {
        const dados = new FormData(f);
        if (!dados.get('filme_id') || !dados.get('sala_id')) {
            res.className = 'res nok'; res.textContent = 'Selecione o filme e a sala.'; tl.hidden = true; return;
        }
        res.className = 'res'; res.textContent = 'Verificando…';
        const r = await fetch('{{ route('sessoes.verificar') }}', {
            method: 'POST', body: dados,
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
        });
        if (r.status === 422) {
            const j = await r.json();
            res.className = 'res nok'; res.textContent = Object.values(j.errors).flat().join(' '); tl.hidden = true; return;
        }
        const j = await r.json();
        if (j.disponivel) {
            res.className = 'res ok'; res.textContent = '✔ ' + j.mensagem;
            if (!precoManual) { inteira.value = j.valor_inteira; meia.value = j.valor_meia; }
            const dur = +f.filme_id.selectedOptions[0].dataset.duracao, tot = T + dur + L;
            tl.innerHTML = `<div class="tl-trailer" style="width:${T/tot*100}%">trailers</div><div class="tl-filme" style="width:${dur/tot*100}%">filme · ${dur} min</div><div class="tl-limpeza" style="width:${L/tot*100}%">limpeza</div>`;
            tl.hidden = false;
        } else {
            res.className = 'res nok';
            res.innerHTML = `<span class="fa-tag">${j.codigo}</span>${j.mensagem}` + (j.sugestao ? ` <button type="button" class="btn btn-sm" id="usar-sugestao">Usar ${j.sugestao}</button>` : '');
            tl.hidden = true;
            const b = document.getElementById('usar-sugestao');
            if (b) b.onclick = () => { f.hora.value = j.sugestao; verificar(); };
        }
    }
    document.getElementById('btn-verificar').addEventListener('click', verificar);
})();
</script>
@endpush
@endsection
