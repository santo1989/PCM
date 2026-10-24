<?php

namespace Tests\Unit;

use App\Models\RecurringTransaction;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RecurringScheduleTest extends TestCase
{
    private function on(string $from, int $day): string
    {
        return RecurringTransaction::occurrenceOnOrAfter(Carbon::parse($from), $day)->toDateString();
    }

    private function after(string $current, int $day): string
    {
        return RecurringTransaction::occurrenceAfter(Carbon::parse($current), $day)->toDateString();
    }

    public function test_first_occurrence_is_same_month_when_day_not_passed()
    {
        $this->assertSame('2026-10-20', $this->on('2026-10-16', 20));
        $this->assertSame('2026-10-16', $this->on('2026-10-16', 16));
    }

    public function test_first_occurrence_rolls_to_next_month_when_day_passed()
    {
        $this->assertSame('2026-11-05', $this->on('2026-10-16', 5));
        $this->assertSame('2027-01-05', $this->on('2026-12-16', 5));
    }

    public function test_day_is_clamped_to_short_months()
    {
        $this->assertSame('2026-02-28', $this->on('2026-02-01', 31));
        $this->assertSame('2028-02-29', $this->on('2028-02-01', 30)); // leap year
        $this->assertSame('2026-04-30', $this->after('2026-03-31', 31));
    }

    public function test_clamped_day_recovers_in_long_months()
    {
        // Feb 28 (clamped from 31) must go to Mar 31, not Mar 28.
        $this->assertSame('2026-03-31', $this->after('2026-02-28', 31));
        // Jan 31 + 1 month must not overflow into March.
        $this->assertSame('2026-02-28', $this->after('2026-01-31', 31));
    }

    public function test_after_moves_exactly_one_month()
    {
        $this->assertSame('2026-11-05', $this->after('2026-10-05', 5));
        $this->assertSame('2027-01-15', $this->after('2026-12-15', 15));
    }
}
