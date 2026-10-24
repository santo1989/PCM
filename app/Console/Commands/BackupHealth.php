<?php

namespace App\Console\Commands;

use App\Support\BackupVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupHealth extends Command
{
    protected $signature = 'backup:health';
    protected $description = 'Fail (and log critical) when the newest backup is missing or older than backup.stale_after_hours';

    public function handle()
    {
        $c = config('database.connections.' . config('database.default'));
        if ($c['driver'] === 'mysql') {
            $files = BackupVault::files($c['database']);
        } else {
            // sqlsrv: only checkable when the SQL Server backup folder is reachable from this host.
            $dir = config('backup.mssql_dir');
            if (!$dir || !is_dir($dir)) {
                $this->line('Backup folder is not reachable from this host; nothing to check.');
                return 0;
            }
            $files = [];
            foreach (glob(rtrim($dir, '\\/') . '/' . $c['database'] . '_*.bak') ?: [] as $path) {
                $files[] = ['path' => $path, 'time' => filemtime($path)];
            }
            usort($files, function ($a, $b) {
                return $b['time'] <=> $a['time'];
            });
        }

        $hours = $files ? (time() - $files[0]['time']) / 3600 : null;
        if ($hours === null || $hours > config('backup.stale_after_hours')) {
            $msg = $hours === null ? 'No backups found.' : sprintf('Newest backup is %.1f h old (limit %d h).', $hours, config('backup.stale_after_hours'));
            Log::critical('Backup health: ' . $msg);
            $this->error($msg);
            return 1;
        }

        if ($c['driver'] === 'mysql' && substr($files[0]['path'], -4) !== '.enc') {
            Log::warning('Backup health: newest backup is not encrypted (set BACKUP_KEYS / BACKUP_ACTIVE_KEY).');
            $this->warn('Newest backup is NOT encrypted.');
        }
        $this->info(sprintf('OK: newest backup %.1f h old.', $hours));

        return 0;
    }
}
