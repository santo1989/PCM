<?php

namespace Tests\Unit;

use App\Services\StockPortfolio;
use PHPUnit\Framework\TestCase;

/** DB-free: the workbook / LBSL rules on a tiny hand-checked portfolio. */
class StockPortfolioTest extends TestCase
{
    private function trade(string $date, string $side, string $symbol, int $qty, float $rate, string $market = 'Public'): array
    {
        return compact('date', 'side', 'symbol', 'market', 'qty', 'rate') + ['commission' => $qty * $rate * StockPortfolio::COMMISSION];
    }

    public function test_average_cost_realized_and_unrealized()
    {
        $trades = [
            $this->trade('2026-01-04', 'Buy', 'GP', 100, 200),  // cost 20,080
            $this->trade('2026-01-05', 'Buy', 'GP', 100, 220),  // cost 22,088 → 200 @ 210.84
            $this->trade('2026-01-11', 'Sell', 'GP', 50, 250),  // net 12,450 − 10,542 = 1,908
        ];
        $p = StockPortfolio::compute($trades, ['GP' => ['company' => 'Grameenphone', 'category' => 'A', 'price' => 230, 'prev_close' => 225]],
            [['type' => 'Deposit', 'cash_in' => 50000, 'cash_out' => 0]], [], '2026-02-01');

        $this->assertEqualsWithDelta(1908.0, $p['totals']['realized'], 0.001);
        $h = $p['holdings'][0];
        $this->assertSame(150, $h['qty']);
        $this->assertEqualsWithDelta(31626.0, $h['cost'], 0.001);          // 42,168 − 10,542
        $this->assertEqualsWithDelta(34362.0, $h['net_if_sold'], 0.001);   // 34,500 − 0.4%
        $this->assertEqualsWithDelta(2736.0, $h['unrealized'], 0.001);
        // Cash: 50,000 − 42,168 bought + 12,450 sold.
        $this->assertEqualsWithDelta(20282.0, $p['totals']['cash_balance'], 0.001);
        $this->assertEqualsWithDelta(20282.0 + 34500, $p['totals']['equity'], 0.001);
    }

    public function test_maturity_skips_friday_and_saturday_and_z_is_t_plus_3()
    {
        $this->assertSame('2025-12-08', StockPortfolio::addWorkingDays('2025-12-04', 2)); // Thu + 2 → Mon
        $trades = [$this->trade('2026-11-04', 'Buy', 'BIFC', 1000, 4), $this->trade('2026-11-04', 'Buy', 'GP', 10, 230, 'Spot')];
        $p = StockPortfolio::compute($trades, ['BIFC' => ['category' => 'Z'], 'GP' => ['category' => 'A']], [], [], '2026-11-05');

        [$bifc, $gp] = $p['holdings'];
        $this->assertSame([0, 1000, '2026-11-09'], [$bifc['sellable'], $bifc['immature'], $bifc['next_mature']]); // Wed + 3 (Thu, Sun, Mon)
        $this->assertSame(10, $gp['sellable']); // Spot buy matures T+1 (Thu)
    }

    public function test_reads_price_and_yesterdays_close_from_a_dse_company_page()
    {
        $html = '<div>Market Depth</div><span>BDT 1,230.20</span><span>▲</span> 1.20 (+0.52%) today<div>Closing BDT 1,230.20</div><div>Yesterday&#x27;s close BDT 1,229.00</div>';

        $this->assertSame(['price' => 1230.2, 'prev_close' => 1229.0], StockPortfolio::parseDsePrice($html));
        $this->assertNull(StockPortfolio::parseDsePrice('<p>Page not found</p>'));
    }

    public function test_a_sell_dated_before_its_buy_is_flagged()
    {
        $ok = StockPortfolio::compute([$this->trade('2026-10-01', 'Buy', 'GP', 100, 230), $this->trade('2026-10-05', 'Sell', 'GP', 100, 240)], ['GP' => ['category' => 'A']], [], [], '2026-11-01');
        $typo = StockPortfolio::compute([$this->trade('2026-09-05', 'Sell', 'GP', 100, 240), $this->trade('2026-10-01', 'Buy', 'GP', 100, 230)], ['GP' => ['category' => 'A']], [], [], '2026-11-01');

        $this->assertSame([], $ok['oversold']);
        $this->assertSame([['date' => '2026-09-05', 'symbol' => 'GP', 'qty' => 100, 'held' => 0]], $typo['oversold']);
    }

    public function test_selling_consumes_the_oldest_lots_first()
    {
        $trades = [$this->trade('2026-10-01', 'Buy', 'GP', 100, 230), $this->trade('2026-11-04', 'Buy', 'GP', 100, 230), $this->trade('2026-11-04', 'Sell', 'GP', 120, 231)];
        $h = StockPortfolio::compute($trades, ['GP' => ['category' => 'A']], [], [], '2026-11-05')['holdings'][0];

        $this->assertSame([80, 0, 80], [$h['qty'], $h['sellable'], $h['immature']]);
    }

    public function test_a_sell_tagged_with_a_lot_leaves_the_other_lots_and_each_lot_has_its_own_profit()
    {
        // Made-up lots: 10 @ 250 and 100 @ 200, price 205, 3% target.
        $trades = [
            ['id' => 1] + $this->trade('2026-07-19', 'Buy', 'GP', 10, 250),
            ['id' => 2] + $this->trade('2026-11-10', 'Buy', 'GP', 100, 200),
            ['id' => 3] + $this->trade('2026-11-10', 'Buy', 'GP', 50, 200),
            ['id' => 4, 'lot_id' => 3] + $this->trade('2026-11-15', 'Sell', 'GP', 50, 210),
        ];
        $p = StockPortfolio::compute($trades, ['GP' => ['category' => 'A', 'price' => 205]], [], [], '2026-11-18');

        $lots = array_column($p['lots'], null, 'id');
        $this->assertSame([1, 2], array_keys($lots)); // lot 3 sold out, the old lot 1 untouched
        $this->assertEqualsWithDelta(-468.20, $lots[1]['pl'], 0.01);   // 2,041.80 net − 2,510 paid
        $this->assertEqualsWithDelta(338.00, $lots[2]['pl'], 0.01);    // 20,418 net − 20,080 paid
        $this->assertEqualsWithDelta(207.66, $lots[2]['target_price'], 0.01); // 200.80 × 1.03 / 0.996
        $this->assertSame('In profit – can sell this lot', $lots[2]['decision']);
        $this->assertSame('HOLD – needs 26.6% more', $lots[1]['decision']);
        $this->assertSame(['in_profit' => 1, 'count' => 2], array_intersect_key($p['lot_summary'], array_flip(['in_profit', 'count'])));
    }
}
