<?php

namespace Tests\Feature;

use App\Services\StockAnalysis;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** How the app behaves when DSE fails. Fake replies only, array cache, no database. */
class StockAnalysisDseTest extends TestCase
{
    public function test_a_maintenance_page_is_not_saved_as_company_data()
    {
        Http::fake(['*' => Http::response('<html>Under maintenance</html>', 200)]);

        $fund = StockAnalysis::warm(['AAA'], false)['AAA']['fund'];

        $this->assertNull($fund['category']);
        $this->assertFalse(Cache::has('dse_fund_AAA'));
    }

    public function test_it_stops_asking_once_a_whole_group_fails()
    {
        Http::fake(['*' => Http::response('down', 503)]);

        StockAnalysis::warm(['AAA', 'BBB', 'CCC', 'DDD', 'EEE', 'FFF'], false);

        Http::assertSentCount(4); // the first group of 4, not the remaining 2
    }

    public function test_a_failed_scan_page_saves_nothing()
    {
        $row = ['tradingCode' => 'AAA', 'date' => '2026-11-01', 'volume' => 10, 'high' => 11, 'low' => 9, 'closep' => 10, 'ltp' => 10, 'openp' => 10, 'ycp' => 10];
        Http::fakeSequence()->push(['rows' => [$row], 'total' => 2, 'pageSize' => 1])->whenEmpty(Http::response('down', 500));

        try {
            StockAnalysis::allHistory(70);
            $this->fail('A missing page must not count as a finished scan.');
        } catch (RequestException $e) {
            $this->assertSame([], StockAnalysis::scanBars('AAA'));
        }
    }

    public function test_buy_needs_known_company_data()
    {
        // Search a wavy rising series for one that gives a Buy on a sound company.
        foreach (range(3, 25) as $w) {
            foreach (range(3, 30) as $amp) {
                $bars = [];
                for ($i = 0; $i < 45; $i++) {
                    $p = 100 + $i * 0.12 + $amp / 10 * sin($i * $w / 10);
                    $bars[] = ['date' => date('Y-m-d', strtotime('2026-09-01') + 86400 * $i), 'open' => $p - 0.25, 'high' => $p + 0.2, 'low' => $p - 0.45, 'close' => $p, 'volume' => 1000 + $i * 30];
                }
                if (in_array('Buy', array_column(StockAnalysis::candleCalls($bars, ['category' => 'A']), 'call'), true)) {
                    $this->assertNotContains('Buy', array_column(StockAnalysis::candleCalls($bars, ['symbol' => 'AAA']), 'call'));
                    $this->assertNotContains('Buy', array_column(StockAnalysis::candleCalls($bars, ['category' => 'Z']), 'call'));
                    return;
                }
            }
        }
        $this->fail('No test series produced a Buy.');
    }
}
