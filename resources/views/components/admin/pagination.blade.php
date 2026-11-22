@if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $paginator->hasPages())
    {{ $paginator->links('pagination::bootstrap-4') }}
@elseif ($paginator instanceof \Illuminate\Contracts\Pagination\Paginator && $paginator->hasPages())
    {{ $paginator->links('pagination::simple-bootstrap-4') }}
@endif
