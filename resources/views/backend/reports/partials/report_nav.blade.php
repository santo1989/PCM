@php
    $reportLinks = [
        ['route' => 'Budge_Projection', 'label' => 'Budget Projection', 'icon' => 'bi-graph-up-arrow'],
        ['route' => 'Yearly_report', 'label' => 'Yearly Report', 'icon' => 'bi-calendar3'],
        ['route' => 'Monthly_report', 'label' => 'Monthly Report', 'icon' => 'bi-calendar-month'],
        ['route' => 'Monthly_invest', 'label' => 'Monthly Investment', 'icon' => 'bi-piggy-bank'],
        ['route' => 'interactive.dashboard', 'label' => 'Interactive Dashboard', 'icon' => 'bi-speedometer2'],
        ['route' => 'cost_optimisation', 'label' => 'Cost Optimisation', 'icon' => 'bi-scissors'],
        ['route' => 'predictive_budget', 'label' => 'Predictive Budget', 'icon' => 'bi-magic'],
        ['route' => 'power_bi_report', 'label' => 'BI Report', 'icon' => 'bi-bar-chart-line'],
    ];
@endphp
<div class="d-flex flex-wrap justify-content-center gap-2 report-nav mb-4 no-print">
    @foreach ($reportLinks as $link)
        <a href="{{ route($link['route']) }}"
            class="btn btn-sm {{ request()->routeIs($link['route']) ? 'btn-danger' : 'btn-outline-danger' }}">
            <i class="bi {{ $link['icon'] }}"></i> {{ $link['label'] }}
        </a>
    @endforeach
</div>
