@props(['paginator'])
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3 no-print">
    <div class="small text-muted">
        @if ($paginator->total() > 0)
            Showing <strong>{{ $paginator->firstItem() }}</strong>–<strong>{{ $paginator->lastItem() }}</strong>
            of <strong>{{ number_format($paginator->total()) }}</strong> records
        @else
            No records
        @endif
    </div>
    <div>{{ $paginator->links() }}</div>
</div>
