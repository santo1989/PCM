<?php

namespace App\Console\Commands;

use App\Services\StockPortfolio;
use Illuminate\Console\Command;

/** Prints the lot-wise portfolio as JSON, for the StockAnalysis bot's live BUY/SELL/HOLD advice. Read-only. */
class StockPortfolioJson extends Command
{
    protected $signature = 'stocks:json {--target=0.03 : Net profit wanted per lot (0.03 = 3%)}';

    protected $description = 'Print holdings, open lots and totals as JSON';

    public function handle()
    {
        $p = StockPortfolio::load(null, (float) $this->option('target'));
        $this->line(json_encode([
            'today' => $p['today'], 'target' => $p['lot_summary']['target'], 'commission' => StockPortfolio::COMMISSION,
            'holdings' => $p['holdings'], 'lots' => $p['lots'], 'totals' => $p['totals'], 'oversold' => $p['oversold'],
        ]));

        return self::SUCCESS;
    }
}
