<?php

namespace App\Models;

use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ExpenseCalculation extends Model
{
    use HasFactory, BelongsToUser;
    protected $guarded = []; // Allow mass assignment

    protected static function booted()
    {
        // Keep the cash entry entered alongside this one in step (query update: no event loop).
        static::updated(function (ExpenseCalculation $expense) {
            $changes = array_intersect_key($expense->getChanges(), array_flip(HandCash::SHARED_FIELDS));
            if ($changes) {
                HandCash::where('expense_calculation_id', $expense->id)->update($changes);
            }
        });
    }

    public function handCashes()
    {
        return $this->hasMany(HandCash::class);
    }

    public function recurringTransaction()
    {
        return $this->belongsTo(RecurringTransaction::class);
    }

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

    /**
     * amount is a text column; a typed "5,315" once broke PHP totals (and MySQL SUM
     * reads it as 5). Store digits only, and always read it back as a number.
     */
    public function setAmountAttribute($value)
    {
        $this->attributes['amount'] = is_string($value) ? str_replace([',', ' '], '', trim($value)) : $value;
    }

    public function getAmountAttribute($value)
    {
        return $value === null || $value === '' ? $value : (float) str_replace([',', ' '], '', $value);
    }
}
