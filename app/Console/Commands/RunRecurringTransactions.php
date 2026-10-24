<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionService;
use Illuminate\Console\Command;

class RunRecurringTransactions extends Command
{
    protected $signature = 'recurring:run';

    protected $description = 'Create income/expense entries for recurring transactions that are due';

    public function handle(RecurringTransactionService $service)
    {
        $count = $service->runDue();
        $this->info("Created {$count} recurring " . ($count === 1 ? 'entry' : 'entries') . '.');

        return self::SUCCESS;
    }
}
