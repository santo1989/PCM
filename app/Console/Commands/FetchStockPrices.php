<?php

namespace App\Console\Commands;

use App\Services\StockPortfolio;
use Illuminate\Console\Command;

/** Updates the market price of every held DSE share from dse.com.bd (scheduled after market close). */
class FetchStockPrices extends Command
{
    protected $signature = 'stocks:prices {symbols?* : Only these symbols (default: everything you hold)}';

    protected $description = 'Fetch latest DSE prices for the stock portfolio';

    public function handle()
    {
        [$updated, $failed] = StockPortfolio::fetchDsePrices($this->argument('symbols') ?: null);
        foreach ($updated as $symbol => $q) {
            $this->line(sprintf('%-12s %10.2f  (yesterday %s)', $symbol, $q['price'], $q['prev_close'] ?? '–'));
        }
        if ($failed) {
            $this->warn('Could not read a price for: ' . implode(', ', $failed));
        }

        return $failed && !$updated ? self::FAILURE : self::SUCCESS;
    }
}
