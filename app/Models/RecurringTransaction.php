<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A monthly income/expense template (salary, rent, bills…) that the
 * RecurringTransactionService turns into ExpenseCalculation rows when due.
 */
class RecurringTransaction extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'next_run_on' => 'date',
        'last_run_on' => 'date',
        'is_active' => 'boolean',
        'amount' => 'float',
        'day_of_month' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function setNameAttribute($value)
    {
        $this->attributes['name'] = $value === null ? null : strtoupper($value);
    }

    /**
     * First occurrence of $day on or after $from, with $day clamped to the
     * month's length (31 -> 30 Apr, 28/29 Feb).
     */
    public static function occurrenceOnOrAfter(Carbon $from, int $day): Carbon
    {
        $from = $from->copy()->startOfDay();
        $candidate = $from->copy()->day(min($day, $from->daysInMonth));
        if ($candidate->lt($from)) {
            $next = $from->copy()->startOfMonth()->addMonthNoOverflow();
            $candidate = $next->day(min($day, $next->daysInMonth));
        }

        return $candidate;
    }

    /** The occurrence in the month after $current. */
    public static function occurrenceAfter(Carbon $current, int $day): Carbon
    {
        $next = $current->copy()->startOfMonth()->addMonthNoOverflow();

        return $next->day(min($day, $next->daysInMonth));
    }

    /** Recompute next_run_on from the schedule, never repeating an already-generated date. */
    public function rescheduleNextRun(): void
    {
        $from = $this->start_date->copy();
        if ($this->last_run_on && $this->last_run_on->copy()->addDay()->gt($from)) {
            $from = $this->last_run_on->copy()->addDay();
        }

        $this->next_run_on = self::occurrenceOnOrAfter($from, $this->day_of_month);
    }

    public function scheduleLabel(): string
    {
        $d = $this->day_of_month;
        $suffix = in_array($d % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$d % 10] ?? 'th');

        return "Monthly on the {$d}{$suffix}" . ($d > 28 ? ' (or last day)' : '');
    }
}
