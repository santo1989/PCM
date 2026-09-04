@props(['insights' => [], 'title' => 'Instant Analysis'])

@if (!empty($insights))
    <div class="insights-panel mb-4">
        @if ($title)
            <h6 class="text-muted text-uppercase small fw-bold mb-2">
                <i class="bi bi-lightbulb me-1"></i>{{ $title }}
            </h6>
        @endif
        <div class="row g-2">
            @foreach ($insights as $insight)
                @php
                    $type = $insight['type'] ?? 'info';
                    $icon = [
                        'success' => 'bi-check-circle-fill',
                        'warning' => 'bi-exclamation-triangle-fill',
                        'danger' => 'bi-exclamation-octagon-fill',
                        'info' => 'bi-info-circle-fill',
                    ][$type] ?? 'bi-info-circle-fill';
                @endphp
                <div class="col-md-6">
                    <div class="alert alert-{{ $type }} insight-card insight-{{ $type }} mb-0 d-flex align-items-start py-2">
                        <i class="bi {{ $icon }} me-2 mt-1"></i>
                        <div class="small">{{ $insight['message'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
