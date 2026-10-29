@if ($sessoes->isEmpty())
    <div class="empty">{{ $vazio }}</div>
@else
<div class="sessao-lista">
    @foreach ($sessoes as $s)
    <div class="sessao-item {{ $s->status === 'cancelada' ? 'cancelada' : '' }}">
        <div class="hora">{{ $s->inicio->format('H:i') }}<small class="muted">até {{ $s->fim->format('H:i') }}</small></div>
        <div>
            <div class="titulo">{{ $s->filme->titulo }}</div>
            <div class="meta">
                <span class="classif classif-{{ $s->filme->classificacao }}">{{ $s->filme->classificacao }}</span>
                <span>Sala {{ $s->sala->nome }}</span>
                <span>{{ $s->descricao }}</span>
                <span>R$ {{ number_format($s->valor_inteira, 2, ',', '.') }}</span>
                @if ($s->status === 'cancelada')<span class="badge badge-danger">Cancelada</span>@endif
            </div>
        </div>
        <div class="acoes"><a class="btn btn-sm" href="{{ route('sessoes.show', $s) }}">Detalhes</a></div>
    </div>
    @endforeach
</div>
@endif
