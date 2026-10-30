<?php

namespace Tests\Unit;

use App\Services\Analytics\LedgerSeries;
use PHPUnit\Framework\TestCase;

class LedgerSeriesTest extends TestCase
{
    public function test_hijri_event_flags()
    {
        // 1446 AH: Ramadan began ~1 Mar 2025, Eid-ul-Fitr ~30 Mar, Eid-ul-Adha ~6 Jun.
        $mar = LedgerSeries::monthFlags('2025-03');
        $this->assertGreaterThanOrEqual(28, $mar['ramadan_days']);
        $this->assertTrue($mar['eid_fitr']);
        $this->assertFalse($mar['eid_adha']);
        $this->assertTrue(LedgerSeries::monthFlags('2025-06')['eid_adha']);
        // The events move ~11 days earlier each year: Ramadan 2026 starts mid-February.
        $this->assertGreaterThan(10, LedgerSeries::monthFlags('2026-02')['ramadan_days']);
        $this->assertSame(['ramadan_days' => 0, 'eid_fitr' => false, 'eid_adha' => false], LedgerSeries::monthFlags('2025-09'));
    }

    public function test_one_off_detection()
    {
        $rows = [];
        $id = 1;
        foreach (['2025-01', '2025-02', '2025-03', '2025-04', '2025-05', '2025-06'] as $ym) {
            $rows[] = ['id' => $id++, 'cat' => 'shop', 'ym' => $ym, 'amount' => 1000 + $id];
            $rows[] = ['id' => $id++, 'cat' => 'rent', 'ym' => $ym, 'amount' => 20000];
        }
        $rows[] = ['id' => 100, 'cat' => 'shop', 'ym' => '2025-04', 'amount' => 60000]; // big purchase
        $rows[] = ['id' => 101, 'cat' => 'shop', 'ym' => '2025-05', 'amount' => 1500];  // small, normal

        $this->assertSame([100], LedgerSeries::detectOneOffs($rows));

        // The same large amount in 3 months is a recurring bill, not a one-off.
        $rows[] = ['id' => 102, 'cat' => 'shop', 'ym' => '2025-05', 'amount' => 61000];
        $rows[] = ['id' => 103, 'cat' => 'shop', 'ym' => '2025-06', 'amount' => 59000];
        $this->assertSame([], LedgerSeries::detectOneOffs($rows));
        $this->assertSame([], LedgerSeries::detectOneOffs([]));
    }

    public function test_inflation_factors()
    {
        // 12% a year for a year: money from 12 months ago is worth 1.12x in today's taka.
        $f = \App\Services\Analytics\Inflation::factors(['2025-01' => 12.0], '2026-01');
        $this->assertEqualsWithDelta(1.12, $f['2025-01'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $f['2026-01'], 1e-9, 'carried forward with the last rate; today is 1');
        $this->assertGreaterThan($f['2025-07'], $f['2025-06']);
        $this->assertSame([], \App\Services\Analytics\Inflation::factors([], '2026-01'));
    }
}
