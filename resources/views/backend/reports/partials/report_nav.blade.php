{{-- Report tabs, from the "Reports" group in config/navigation.php. Swipeable on phones. --}}
@php($reportLinks = collect(config('navigation.groups'))->firstWhere('label', 'Reports')['items'] ?? [])
<nav class="d-flex gap-2 report-nav mb-4 no-print" aria-label="Reports">
    @foreach ($reportLinks as $link)
        @php($active = request()->routeIs($link['match'] ?? $link['route']))
        <a href="{{ route($link['route']) }}" class="btn btn-sm {{ $active ? 'btn-primary' : 'btn-outline-secondary' }}"
            @if ($active) aria-current="page" @endif>
            <i class="bi {{ $link['icon'] }}"></i> {{ $link['label'] }}
        </a>
    @endforeach
</nav>
