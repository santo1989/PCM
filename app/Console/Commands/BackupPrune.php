<?php

namespace App\Console\Commands;

use App\Support\BackupVault;
use Illuminate\Console\Command;

class BackupPrune extends Command
{
    protected $signature = 'backup:prune {--keep= : Newest backups to always keep (default: config backup.keep_last)} {--dry-run}';
    protected $description = 'Delete old dumps: keep the newest N, one per day for X days, one per month for Y months';

    public function handle()
    {
        $c = config('database.connections.' . config('database.default'));
        if ($c['driver'] !== 'mysql') {
            $this->line('backup:prune only manages mysql dumps; sqlsrv .bak files live on the SQL Server host.');
            return 0;
        }

        $files = BackupVault::files($c['database']);
        $keepLast = $this->option('keep') === null ? config('backup.keep_last') : (int) $this->option('keep');
        $keep = BackupVault::keep($files, $keepLast, config('backup.keep_daily_days'), config('backup.keep_monthly'), time());

        $n = 0;
        foreach ($files as $f) {
            if (!in_array($f['path'], $keep, true)) {
                $this->line(($this->option('dry-run') ? 'would delete ' : 'deleted ') . basename($f['path']));
                $this->option('dry-run') || unlink($f['path']);
                $n++;
            }
        }
        $this->info(count($keep) . " kept, {$n} pruned.");

        return 0;
    }
}
