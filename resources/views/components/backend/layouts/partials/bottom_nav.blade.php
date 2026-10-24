@can('Admin')
    @php
        $bottom = config('navigation.bottom');
        $isActive = fn(array $item) => request()->routeIs($item['match'] ?? $item['route']);
    @endphp
    <nav class="app-bottomnav d-lg-none no-print" aria-label="Quick navigation">
        @foreach (array_slice($bottom, 0, 2) as $item)
            <a href="{{ route($item['route']) }}" class="{{ $isActive($item) ? 'active' : '' }}"
                @if ($isActive($item)) aria-current="page" @endif>
                <i class="bi {{ $item['icon'] }}"></i>{{ $item['label'] }}
            </a>
        @endforeach

        <button type="button" class="app-fab" data-bs-toggle="offcanvas" data-bs-target="#quickAddSheet"
            aria-controls="quickAddSheet" aria-label="Quick add">
            <i class="bi bi-plus-lg"></i>
        </button>

        @foreach (array_slice($bottom, 2) as $item)
            <a href="{{ route($item['route']) }}" class="{{ $isActive($item) ? 'active' : '' }}"
                @if ($isActive($item)) aria-current="page" @endif>
                <i class="bi {{ $item['icon'] }}"></i>{{ $item['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="offcanvas offcanvas-bottom quick-add-sheet d-lg-none" tabindex="-1" id="quickAddSheet"
        aria-labelledby="quickAddSheetLabel">
        <div class="offcanvas-header pb-0">
            <h5 class="offcanvas-title" id="quickAddSheetLabel">Quick add</h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="offcanvas-body pt-2">
            <div class="list-group">
                @foreach (config('navigation.quick_add') as $item)
                    <a href="{{ route($item['route'], $item['params'] ?? []) }}" class="list-group-item list-group-item-action">
                        <i class="bi {{ $item['icon'] }}"></i> {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endcan
