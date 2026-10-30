<?php

namespace Tests\Unit;

use App\Services\Analytics\CashFlow;
use App\Services\Analytics\Engine;
use PHPUnit\Framework\TestCase;

class AnalyticsEngineTest extends TestCase
{
    private const SEASON = [10, 12, 30, 11, 10, 9, 10, 40, 12, 11, 10, 25];

    private function zeros(int $n): array
    {
        return array_fill(0, $n, [0, 0, 0]);
    }

    /** Deterministic pseudo-noise in [-1, 1]. */
    private function noise(int $i): float
    {
        return sin($i * 12.9898) * 43758.5453 - floor(sin($i * 12.9898) * 43758.5453) - 0.5;
    }

    public function test_seasonal_series_is_forecast_from_its_season()
    {
        $y = [];
        for ($i = 0; $i < 48; $i++) {
            $y[] = self::SEASON[$i % 12] * 1000.0;
        }
        $r = Engine::runSeries('s', $y, $this->zeros(48), $this->zeros(3), true);
        $this->assertNotNull($r['chosen']);
        $this->assertNull($r['note']);
        foreach ($r['forecast'] as $h => $f) {
            $this->assertEqualsWithDelta(self::SEASON[$h] * 1000, $f['mean'], 500);
            $this->assertTrue($f['lo95'] <= $f['mean'] && $f['mean'] <= $f['hi95']);
        }
        $this->assertSame([], $r['changepoints'], 'a repeating pattern is not a level shift');
    }

    public function test_level_shift_and_intermittent_models()
    {
        $y = [];
        for ($i = 0; $i < 40; $i++) {
            $y[] = ($i < 20 ? 10000 : 25000) + 600 * $this->noise($i);
        }
        $r = Engine::runSeries('s', $y, $this->zeros(40), $this->zeros(3), true);
        $this->assertSame([20], array_column($r['changepoints'], 'idx'));

        $sparse = [];
        for ($i = 0; $i < 4; $i++) {
            $sparse = array_merge($sparse, [0, 0, 500, 0, 0, 0, 800, 0, 0, 600, 0, 0]);
        }
        $r = Engine::runSeries('c', $sparse, $this->zeros(48), $this->zeros(3), false);
        $this->assertTrue($r['intermittent']);
        $this->assertEmpty(array_diff(array_column($r['models'], 'model'), ['Seasonal Naive', 'Croston', 'SBA', 'TSB', 'ADIDA', 'Ensemble']));
    }

    public function test_guards()
    {
        $r = Engine::runSeries('short', array_fill(0, 20, 100.0), $this->zeros(20), $this->zeros(3), false);
        $this->assertSame([], $r['forecast']);
        $this->assertStringContainsString('not enough history', $r['note']);

        $r = Engine::runSeries('dead', array_merge(array_fill(0, 30, 100.0), array_fill(0, 12, 0.0)), $this->zeros(42), $this->zeros(3), false);
        $this->assertStringContainsString('no spend', $r['note']);

        $r = Engine::runSeries('late', array_merge(array_fill(0, 30, 0.0), array_fill(0, 12, 100.0)), $this->zeros(42), $this->zeros(3), false);
        $this->assertSame([30, 12], [$r['offset'], $r['n']], 'leading zeros are trimmed, not treated as real zeros');
    }

    public function test_month_anomalies()
    {
        $y = [];
        for ($i = 0; $i < 48; $i++) {
            $y[] = self::SEASON[$i % 12] * 1000.0 + 800 * $this->noise($i);
        }
        $y[30] += 60000;
        $this->assertSame([30], array_column(Engine::monthAnomalies($y), 'idx'), 'only the new spike; yearly peaks are not flagged');

        $bill = array_fill(0, 44, 1500.0);
        $bill[30] = 4000;
        $a = Engine::monthAnomalies($bill);
        $this->assertSame([30], array_column($a, 'idx'));
        $this->assertEqualsWithDelta(16.7, $a[0]['z'], 0.1, 'a fixed bill gets a sane score, not infinity');
    }

    public function test_least_squares_recovers_an_eid_effect()
    {
        $X = $this->zeros(36);
        $y = [];
        for ($t = 0; $t < 36; $t++) {
            $X[$t][2] = $t % 12 === 5 ? 1 : 0; // Eid-ul-Adha in month 6
            $y[] = 1000 + 10 * $t + 5000 * $X[$t][2];
        }
        $f = Engine::linearEvents($y, 3, $X, [[0, 0, 0], [0, 0, 1], [0, 0, 0]]);
        $this->assertEqualsWithDelta(1360, $f[0], 1);
        $this->assertEqualsWithDelta(6370, $f[1], 1);
    }

    public function test_recurring_payments()
    {
        $rows = [];
        for ($m = 3; $m <= 10; $m++) {
            $rows[] = ['src' => 'ledger', 'group' => 6, 'name' => "INTERNET BILL $m", 'date' => sprintf('2026-%02d-05', $m), 'amount' => 1500];
        }
        for ($d = 0; $d < 200; $d++) {
            $rows[] = ['src' => 'ledger', 'group' => 4, 'name' => 'AUTO-RICKSHAW', 'date' => date('Y-m-d', strtotime("2026-03-01 +$d days")), 'amount' => 100];
        }
        for ($m = 1; $m <= 7; $m++) {
            $rows[] = ['src' => 'ledger', 'group' => 6, 'name' => 'OLD GYM', 'date' => sprintf('2025-%02d-10', $m), 'amount' => 2000];
        }
        $months = ['', 'JAN', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER'];
        foreach (['1783010022664' => 5000, '1783110000405' => 10000] as $acct => $amt) {
            for ($m = 3; $m <= 10; $m++) {
                $rows[] = ['src' => 'account', 'group' => 'SAVE|ISLAMIC_DPS', 'name' => "$acct DPS {$m}TH MONTH {$months[$m]}", 'date' => sprintf('2026-%02d-20', $m), 'amount' => $amt];
            }
        }
        $found = array_column(CashFlow::recurring($rows, '2026-10-28'), null, 'name');
        ksort($found);
        $this->assertSame(['1783010022664 DPS MONTH', '1783110000405 DPS MONTH', 'INTERNET BILL'], array_keys($found), 'daily and stopped items are left out');
        $this->assertEquals(10000, $found['1783110000405 DPS MONTH']['amount'], 'the two DPS schemes stay apart');
        $this->assertSame('monthly', $found['INTERNET BILL']['cadence']);
        $this->assertGreaterThanOrEqual('2026-10-28', $found['INTERNET BILL']['next_date']);
        $this->assertCount(12, $found['INTERNET BILL']['calendar']);
    }

    public function test_hand_cash_runway()
    {
        $r = CashFlow::handCash(array_fill(0, 200, 100.0), 1000.0, [['2026-09-01', 5000], ['2026-09-15', 5000]]);
        $this->assertSame(['p10' => 10, 'p50' => 10, 'p90' => 10], $r['days_until_empty'], '100/day from 1,000 lasts exactly 10 days');
        $this->assertEquals([7 => 0, 14 => 1, 30 => 1], $r['prob_empty_within']);
        $this->assertEquals(['7' => 0, '30' => 2000], $r['suggested_withdrawal'], 'a week needs 700 (have 1,000); 30 days need 3,000');
        $this->assertEquals(14, $r['history']['median_days_between']);
    }

    public function test_savings_and_events()
    {
        $s = CashFlow::savings(array_fill(0, 24, 10000.0), array_fill(0, 24, 30000.0), 90000.0);
        $this->assertEquals(120000, $s['cumulative_net'][11][0], '12 months x 10,000, no spread');
        $this->assertSame(3, $s['emergency_months']['p50']);
        $this->assertEquals(3, $s['emergency_months_simple']);

        $months = array_map(fn($m) => sprintf('2025-%02d', $m), range(1, 12));
        $flags = array_fill(0, 12, [0, 0, 0]);
        $flags[5] = [0, 0, 1];
        $spend = array_fill(0, 12, 20000.0);
        $spend[5] = 50000.0;
        $ahead = [['2026-11', [0, 0, 0]], ['2026-12', [0, 0, 0]], ['2027-01', [0, 0, 1]]];
        $adha = array_column(CashFlow::events($months, $spend, $flags, $ahead), null, 'event')['Eid-ul-Adha'];
        $this->assertEquals([30000, 3, 10000], [$adha['median_extra'], $adha['months_left'], $adha['monthly_saving']]);
    }
}
