<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class DbBackup extends Command
{
    protected $signature = 'db:backup {--keep=14 : Number of newest backups to keep}';
    protected $description = 'Dump the MySQL database to storage/app/backups and prune old dumps';

    public function handle()
    {
        $c = config('database.connections.' . config('database.default'));
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $file = $dir . '/' . $c['database'] . '_' . date('Y-m-d_His') . '.sql';

        // Password goes through the environment, not the command line.
        $p = new Process([
            env('MYSQLDUMP_PATH', 'mysqldump'), '-h', $c['host'], '-P', (string) $c['port'], '-u', $c['username'],
            '--single-transaction', '--routines', '--triggers', '--result-file=' . $file, $c['database'],
        ], null, ['MYSQL_PWD' => (string) $c['password']]);
        $p->setTimeout(600)->run();

        if (!$p->isSuccessful()) {
            @unlink($file);
            $this->error($p->getErrorOutput());
            return 1;
        }

        $old = array_slice(array_reverse(glob($dir . '/' . $c['database'] . '_*.sql')), (int) $this->option('keep'));
        array_map('unlink', $old);
        $this->info('Backup written: ' . $file);

        return 0;
    }
}
