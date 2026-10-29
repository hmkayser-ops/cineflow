@if ($paginator->hasPages())
<nav class="paginacao">
    <span class="muted">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} de {{ $paginator->total() }}</span>
    @if ($paginator->onFirstPage())
        <span class="btn btn-sm" aria-disabled="true">Anterior</span>
    @else
        <a class="btn btn-sm" href="{{ $paginator->previousPageUrl() }}">Anterior</a>
    @endif
    <span class="atual">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if ($paginator->hasMorePages())
        <a class="btn btn-sm" href="{{ $paginator->nextPageUrl() }}">Próxima</a>
    @else
        <span class="btn btn-sm" aria-disabled="true">Próxima</span>
    @endif
</nav>
@endif
