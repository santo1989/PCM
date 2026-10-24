{{--
    Shared date-range filter for report pages: Start/End dates, one-tap presets,
    Apply and Reset. Submits a plain GET to the current page, so the range stays
    in the URL (reload/share keeps it). Phone-first: dates side by side, presets
    swipe sideways, buttons wrap.

    Props:
      start, end   current Y-m-d values (what the page actually used)
      min          earliest selectable date (first record)
      startId/endId  input ids, for pages whose scripts read the inputs
      note         optional help text under the filter
    Slot: extra buttons (Excel, Print...).
--}}
@props(['start' => null, 'end' => null, 'min' => null, 'startId' => 'start_date', 'endId' => 'end_date', 'note' => null])

@php
    $today = now();
    $presets = [
        'This month' => [$today->copy()->startOfMonth(), $today],
        'Last month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
        'Last 3 months' => [$today->copy()->subMonthsNoOverflow(2)->startOfMonth(), $today],
        'This year' => [$today->copy()->startOfYear(), $today],
        'Last 12 months' => [$today->copy()->subMonthsNoOverflow(11)->startOfMonth(), $today],
        'All time' => [\Carbon\Carbon::parse($min ?: $today->copy()->subYears(5)), $today],
    ];
    $reset = url()->current();
@endphp

<div {{ $attributes->class(['card mb-4 no-print date-range-filter']) }}>
    <div class="card-body">
        <form method="GET" action="{{ $reset }}" class="row g-2 align-items-end">
            <div class="col-6 col-md-3">
                <label for="{{ $startId }}" class="form-label small mb-1">Start date</label>
                <input type="date" name="start_date" id="{{ $startId }}" class="form-control" value="{{ $start }}"
                    min="{{ $min }}" max="{{ $today->toDateString() }}">
            </div>
            <div class="col-6 col-md-3">
                <label for="{{ $endId }}" class="form-label small mb-1">End date</label>
                <input type="date" name="end_date" id="{{ $endId }}" class="form-control" value="{{ $end }}"
                    min="{{ $min }}" max="{{ $today->toDateString() }}">
            </div>
            <div class="col-12 col-md-6 d-flex flex-wrap gap-2 justify-content-md-end">
                <button type="submit" class="btn btn-primary flex-fill flex-md-grow-0">
                    <i class="bi bi-funnel"></i> Apply
                </button>
                <a href="{{ $reset }}" class="btn btn-outline-danger flex-fill flex-md-grow-0">
                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                </a>
                {{ $slot }}
            </div>
            <div class="col-12">
                <div class="d-flex gap-2 report-nav mb-0 pb-1" role="group" aria-label="Quick date ranges">
                    @foreach ($presets as $label => [$from, $to])
                        @php
                            $active = (string) $start === $from->toDateString() && (string) $end === $to->toDateString();
                        @endphp
                        <a href="{{ $reset }}?start_date={{ $from->toDateString() }}&end_date={{ $to->toDateString() }}"
                            class="btn btn-sm rounded-pill {{ $active ? 'btn-primary' : 'btn-outline-secondary' }}"
                            @if ($active) aria-current="true" @endif>{{ $label }}</a>
                    @endforeach
                </div>
            </div>
            @if ($note)
                <div class="col-12">
                    <div class="small text-muted">{{ $note }}</div>
                </div>
            @endif
        </form>
    </div>
</div>
