<?php

namespace Tests\Unit;

use App\Services\VentureBilling;
use PHPUnit\Framework\TestCase;

/** DB-free: invoice totals, status, numbering and when a bill reminder shows. */
class VentureBillingTest extends TestCase
{
    public function test_total_with_a_discount_line()
    {
        // 2,500 + 2,500 + 7,400 − 2,400 discount = 10,000.
        $items = [['qty' => 1, 'rate' => 2500], ['qty' => 1, 'rate' => 2500], ['qty' => 1, 'rate' => 7400], ['qty' => 1, 'rate' => -2400]];
        $this->assertSame(10000.0, VentureBilling::itemsTotal($items));
    }

    public function test_status()
    {
        $this->assertSame('Paid', VentureBilling::status(12500, 12500, '2026-05-01', '2026-11-07'));
        $this->assertSame('Overdue', VentureBilling::status(12500, 5000, '2026-05-01', '2026-11-07'));
        $this->assertSame('Partly paid', VentureBilling::status(12500, 5000, '2026-12-01', '2026-11-07'));
        $this->assertSame('Due', VentureBilling::status(12500, 0, null, '2026-11-07'));
    }

    public function test_next_number_continues_the_years_sequence()
    {
        $this->assertSame('2026-11-003', VentureBilling::nextNumber('2026-11-07', ['2026-05-001', '2026-06-002', '2025-06-002']));
        $this->assertSame('2027-01-001', VentureBilling::nextNumber('2027-01-03', ['2026-05-001']));
    }

    public function test_reminder_opens_remind_days_before_the_due_date()
    {
        $s = ['is_active' => true, 'next_due' => '2026-11-25', 'remind_days' => 15]; // window opens 10 Nov
        $this->assertSame([], VentureBilling::dueSchedules([$s], '2026-11-09'));
        $this->assertCount(1, VentureBilling::dueSchedules([$s], '2026-11-10'));
        $this->assertSame([], VentureBilling::dueSchedules([['is_active' => false] + $s], '2026-12-01'));
    }
}
