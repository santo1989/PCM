<?php

namespace Tests\Unit;

use App\Http\Controllers\IncomeExpenseSheetController;
use App\Services\AI\ReportExplainer;
use App\Services\StockPortfolio;
use App\Services\VentureLedger;
use PHPUnit\Framework\TestCase;

/** DB-free: the page recommendations quote only their own figures, and private names never reach the AI. */
class AiAdviceTest extends TestCase
{
    private function assertOnlyKnownNumbers(array $a)
    {
        $text = implode(' ', array_merge($a['template']['insights'], $a['template']['actions']));
        $this->assertSame([], ReportExplainer::unknownNumbers($text, $a['metrics']), $text);
        $this->assertGreaterThanOrEqual(1, count($a['template']['actions']));
    }

    public function test_stock_advice()
    {
        $trade = fn($d, $s, $q, $r) => ['date' => $d, 'side' => 'Buy', 'symbol' => $s, 'market' => 'Public', 'qty' => $q, 'rate' => $r, 'commission' => $q * $r * 0.004];
        $p = StockPortfolio::compute([$trade('2026-01-04', 'AAA', 1000, 20), $trade('2026-01-05', 'BBB', 100, 50)],
            ['AAA' => ['category' => 'A', 'price' => 15, 'prev_close' => 15], 'BBB' => ['category' => 'A', 'price' => 60, 'prev_close' => 59]],
            [['type' => 'Deposit', 'cash_in' => 100000, 'cash_out' => 0]], [], '2026-02-01');
        $a = StockPortfolio::advice($p);

        $this->assertOnlyKnownNumbers($a);
        $this->assertStringContainsString('AAA', implode(' ', $a['template']['insights'])); // largest and deepest below cost
    }

    public function test_venture_advice_hides_client_names()
    {
        $summary = VentureLedger::summarize([
            ['date' => '2026-05-01', 'kind' => 'income', 'amount' => 30000, 'client' => 'SECRET CO'],
            ['date' => '2026-05-02', 'kind' => 'cost', 'amount' => 9000, 'client' => 'SECRET CO'],
            ['date' => '2026-06-01', 'kind' => 'income', 'amount' => 10000, 'client' => 'OTHER LTD'],
        ]);
        $a = VentureLedger::advice('Freelance', $summary, ['SECRET CO' => ['due' => 5000, 'overdue' => 5000, 'invoices' => 1]], [['OTHER LTD', '2026-12-01', 12500]], '2026');

        $this->assertOnlyKnownNumbers($a);
        $sent = json_encode($a);
        $this->assertStringNotContainsString('SECRET', json_encode($a['metrics']) . json_encode($a['template']));
        $this->assertSame(['Client 1' => 'SECRET CO', 'Client 2' => 'OTHER LTD'], $a['aliases']);
        $this->assertStringContainsString('Chase the 5,000 overdue', $sent);
    }

    public function test_sheet_advice()
    {
        $item = fn($name, $m) => ['name' => $name, 'months' => $m];
        $groups = [['label' => 'Main', 'income' => [$item('SALARY', [1 => 50000, 2 => 50000])], 'expense' => [$item('FOOD', [1 => 20000, 2 => 35000]), $item('RENT', [1 => 15000, 2 => 15000])], 'months' => []]];
        $a = IncomeExpenseSheetController::advice($groups, [['name' => 'DPS', 'months' => [1 => 5000, 2 => 5000]]], [1 => 'Jan', 2 => 'Feb'], '2026');

        $this->assertOnlyKnownNumbers($a);
        $this->assertEquals(['income' => 100000, 'expense' => 85000, 'difference' => 15000],array_intersect_key($a['metrics'], array_flip(['income', 'expense', 'difference'])));
        $this->assertStringContainsString('Best period: Jan', implode(' ', $a['template']['insights']));
    }
}
