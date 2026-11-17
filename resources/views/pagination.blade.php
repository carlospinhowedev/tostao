@if ($paginator->hasPages())
    <nav class="mt-4 flex items-center justify-between text-sm" role="navigation" aria-label="{{ __('Pagination') }}">
        <p class="muted">{{ __('Showing :from to :to of :total', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}</p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-sm opacity-50" aria-disabled="true">{{ __('Previous') }}</span>
            @else
                <a class="btn btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">{{ __('Previous') }}</a>
            @endif
            @if ($paginator->hasMorePages())
                <a class="btn btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('Next') }}</a>
            @else
                <span class="btn btn-sm opacity-50" aria-disabled="true">{{ __('Next') }}</span>
            @endif
        </div>
    </nav>
@endif
