{{--
    Shared AI insights card for the classic report pages. Pass in:
      $aiInsights   (array) the shape returned by App\Services\AI\MLPipeline::run()
      $title        (string, optional) card header text
      $icon         (string, optional) bootstrap-icons class, e.g. 'bi-robot'
      $headerClass  (string, optional) header background/text classes
      $showAnomalies (bool, optional) set false to hide the anomalies column (e.g. when the
                      page already has its own dedicated anomaly section elsewhere)
--}}
@php
    $title = $title ?? 'AI-Powered Insights';
    $icon = $icon ?? 'bi-robot';
    $headerClass = $headerClass ?? 'bg-info text-white';
    $showAnomalies = $showAnomalies ?? true;
    $severityBadge = fn($severity) => $severity === 'critical' ? 'danger' : ($severity === 'high' ? 'warning' : 'info');
    $priorityBadge = fn($priority) => $priority === 'critical' ? 'danger' : ($priority === 'high' ? 'warning' : 'secondary');
@endphp
<div class="card mt-4">
    <div class="card-header {{ $headerClass }} d-flex justify-content-between align-items-center">
        <span><i class="bi {{ $icon }}"></i> {{ $title }}</span>
        <span class="badge bg-light text-dark">Rule-based, built-in statistics</span>
    </div>
    <div class="card-body">
        @if (!empty($aiInsights['summary']))
            <div class="mb-3">
                @foreach ($aiInsights['summary'] as $insight)
                    <div class="alert alert-{{ $insight['type'] }} py-2 mb-2">{{ $insight['message'] }}</div>
                @endforeach
            </div>
        @endif

        <div class="row">
            @if ($showAnomalies)
                <div class="col-md-6">
                    <h6><i class="bi bi-exclamation-triangle"></i> Anomalies ({{ count($aiInsights['anomalies'] ?? []) }})</h6>
                    @forelse ($aiInsights['anomalies'] ?? [] as $a)
                        <div class="alert alert-{{ $severityBadge($a['severity']) }} py-2 mb-2">
                            <div class="d-flex justify-content-between">
                                <strong>{{ $a['category'] }}</strong>
                                <span class="badge bg-{{ $severityBadge($a['severity']) }}">{{ $a['severity'] }}</span>
                            </div>
                            <div class="small">{{ $a['suggestion'] }}</div>
                        </div>
                    @empty
                        <p class="text-muted small">No unusual spending detected for this period.</p>
                    @endforelse
                </div>
            @endif
            <div class="{{ $showAnomalies ? 'col-md-6' : 'col-12' }}">
                <h6><i class="bi bi-lightbulb"></i> Recommendations</h6>
                @forelse ($aiInsights['recommendations'] ?? [] as $rec)
                    <div class="mb-2">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $rec['title'] }}</strong>
                            <span class="badge bg-{{ $priorityBadge($rec['priority']) }}">{{ $rec['priority'] }}</span>
                        </div>
                        <div class="small text-muted">{{ $rec['message'] }}</div>
                    </div>
                @empty
                    <p class="text-muted small">No recommendations right now.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
