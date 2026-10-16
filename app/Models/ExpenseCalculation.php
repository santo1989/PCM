<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ExpenseCalculation extends Model
{
    use HasFactory;
    protected $guarded = []; // Allow mass assignment

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeFilterByMonthYear($query, $month, $year)
    {
        return $query->whereMonth('date', $month)->whereYear('date', $year);
    }

    /**
     * Ensure 'types' is always stored uppercase.
     */
    public function setTypesAttribute($value)
    {
        $this->attributes['types'] = is_null($value) ? null : strtoupper($value);
    }

    /**
     * Ensure 'rules' is always stored uppercase.
     */
    public function setRulesAttribute($value)
    {
        $this->attributes['rules'] = is_null($value) ? null : strtoupper($value);
    }

    /**
     * Forget the dashboard/report caches covering the given dates (Y-m-d strings).
     * Never throws: a cache failure must not block saving a transaction.
     */
    public static function flushReportCaches(iterable $dates): void
    {
        try {
            Cache::forget('dashboard:monthly_trend:last12');
            \App\Services\FinancialAnalyticsService::forgetAll();
            \App\Services\FinancialAnalysisService::forgetAll();

            $seen = [];
            foreach ($dates as $date) {
                if (empty($date) || isset($seen[$date])) {
                    continue;
                }
                $seen[$date] = true;
                $y = date('Y', strtotime($date));
                $m = date('m', strtotime($date));
                Cache::forget("dashboard:category_breakdown:{$y}:all");
                Cache::forget("dashboard:top_categories:{$y}");
                Cache::forget("dashboard:summary:{$y}:{$m}");
                Cache::forget("dashboard:category_breakdown:{$y}:{$m}");
            }
        } catch (\Exception $e) {
            // don't block workflow if cache clearing fails
        }
    }
}
