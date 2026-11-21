<?php

namespace Tests\Unit;

use App\Services\StockAnalysis;
use PHPUnit\Framework\TestCase;

class StockAnalysisTest extends TestCase
{
    private function bar(string $d, float $o, float $h, float $l, float $c, int $v = 1000): array
    {
        return ['date' => $d, 'open' => $o, 'high' => $h, 'low' => $l, 'close' => $c, 'volume' => $v];
    }

    public function test_hammer_after_a_fall_and_spinning_top_are_found(): void
    {
        $bars = [$this->bar('d1', 12, 12, 11, 11.5), $this->bar('d2', 11.5, 11.5, 10.5, 11), $this->bar('d3', 11, 11, 10, 10.5),
            $this->bar('d4', 10.2, 10.3, 9.0, 10.1), $this->bar('d5', 10, 10.6, 9.4, 10.25)];
        $this->assertContains('Hammer (bullish)', StockAnalysis::patterns($bars, 3));
        $this->assertContains('Spinning top', StockAnalysis::patterns($bars, 4));
    }

    public function test_rising_series_gives_buy_signals_and_zero_loss_price(): void
    {
        $bars = [];
        for ($i = 0; $i < 40; $i++) {
            $p = 100 + $i * 0.5;
            $bars[] = $this->bar("2026-10-" . sprintf('%02d', $i + 1), $p - 0.3, $p + 0.4, $p - 0.5, $p, 1000 + $i * 50);
        }
        $a = StockAnalysis::analyze($bars, ['category' => 'A', 'pe' => 8, 'nav' => 60, 'sponsor' => 50], ['qty' => 100, 'avg_cost' => 120]);
        $this->assertTrue($a['ok']);
        $this->assertTrue($a['signals']['MA14 buy']);
        $this->assertTrue($a['signals']['Escalating']);
        $this->assertEqualsWithDelta(120 / 0.996 * 1.01, $a['zero']['zero_loss_sell'], 0.001);
        $this->assertGreaterThan(0, $a['zero']['rise_needed']);
        $this->assertLessThan($a['plan']['buy_low'], $a['plan']['stop']);
        $this->assertGreaterThan($a['plan']['buy_high'], $a['plan']['target']);
    }

    public function test_candle_calls_use_only_that_days_prices_and_block_buying_weak_companies(): void
    {
        $bars = [];
        for ($i = 0; $i < 40; $i++) {
            $p = 100 + $i * 0.2 + ($i % 3 ? 0.3 : -0.3);
            $bars[] = $this->bar('2026-10-' . sprintf('%02d', $i + 1), $p - 0.3, $p + 0.4, $p - 0.5, $p, 1000 + $i * 50);
        }
        $calls = StockAnalysis::candleCalls($bars);
        $this->assertCount(15, $calls);
        $this->assertContainsOnly('string', array_column($calls, 'meaning'));
        $this->assertEmpty(array_diff(array_column($calls, 'call'), ['Buy', 'Sell', 'Hold']));

        // A later crash must not change what an earlier day said.
        $crash = $bars;
        $crash[39] = $this->bar('2026-10-40', 90, 91, 70, 71, 9000);
        $this->assertSame($calls['2026-10-' . 39], StockAnalysis::candleCalls($crash)['2026-10-' . 39]);

        $this->assertNotContains('Buy', array_column(StockAnalysis::candleCalls($bars, ['category' => 'Z']), 'call'));
    }

    public function test_z_category_is_avoid(): void
    {
        $bars = [];
        for ($i = 0; $i < 30; $i++) $bars[] = $this->bar("d$i", 3.4, 3.5, 3.3, 3.4);
        $this->assertSame('AVOID', StockAnalysis::analyze($bars, ['category' => 'Z', 'nav' => -136.84])['verdict']);
    }
}
