<?php

namespace Tests\Unit;

use App\Services\VentureLedger;
use PHPUnit\Framework\TestCase;

/** DB-free: freelance/business totals per client and per year. */
class VentureLedgerTest extends TestCase
{
    public function test_totals_by_client_and_year()
    {
        $s = VentureLedger::summarize([
            ['date' => '2025-05-14', 'kind' => 'income', 'amount' => 10000, 'client' => 'CLIENT A'],
            ['date' => '2025-05-16', 'kind' => 'cost', 'amount' => 3250, 'client' => 'CLIENT A'],
            ['date' => '2026-05-21', 'kind' => 'income', 'amount' => 12500, 'client' => 'CLIENT A'],
            ['date' => '2026-06-17', 'kind' => 'cost', 'amount' => 2000, 'client' => null],
        ]);

        $this->assertEquals(['income' => 22500, 'cost' => 5250, 'profit' => 17250], array_intersect_key($s['total'], array_flip(['income', 'cost', 'profit'])));
        $a = $s['clients']['CLIENT A'];
        $this->assertSame([19250.0, 3, '2025-05-14', '2026-05-21'], [$a['profit'], $a['entries'], $a['first'], $a['last']]);
        $this->assertEqualsWithDelta(19250 / 22500, $a['margin'], 1e-9);
        $this->assertNull($s['clients']['Not tagged']['margin']); // cost only: no margin
        $this->assertSame(['2026', '2025'], array_map('strval', array_keys($s['years'])));
        $this->assertEquals(10500, $s['years']['2026']['profit']);
    }
}
