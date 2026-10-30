<?php

namespace App\View\Components;

use App\Services\Analytics\PeriodContext;
use Illuminate\View\Component;

/** <x-analytics-context :start="$startDate" :end="$endDate" />: comparisons, forecast and insights for a report period. */
class AnalyticsContext extends Component
{
    public array $ctx;

    public function __construct(string $start, string $end)
    {
        $this->ctx = PeriodContext::build($start, $end);
    }

    public function render()
    {
        return view('components.analytics-context');
    }
}
