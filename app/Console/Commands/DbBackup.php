<?php

namespace App\Console\Commands;

use App\Support\BackupVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class DbBackup extends Command
{
    protected $signature = 'db:backup {--keep= : Newest backups to always keep (default: config backup.keep_last)}';
    protected $description = 'Back up the default database (mysql: encrypted dump in storage/app/backups, pruned by retention; sqlsrv: BACKUP DATABASE on the server)';

    public function handle()
    {
        $c = config('database.connections.' . config('database.default'));

        switch ($c['driver']) {
            case 'mysql':
                return $this->mysql($c);
            case 'sqlsrv':
                return $this->sqlsrv($c);
        }
        $this->error('db:backup does not support driver ' . $c['driver']);

        return 1;
    }

    private function mysql(array $c)
    {
        $dir = BackupVault::dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        $file = $dir . '/' . $c['database'] . '_' . date('Y-m-d_His') . '.sql';

        // Password goes through the environment, not the command line.
        $p = new Process([
            config('backup.mysqldump'), '-h', $c['host'], '-P', (string) $c['port'], '-u', $c['username'],
            '--single-transaction', '--routines', '--triggers', '--result-file=' . $file, $c['database'],
        ], null, ['MYSQL_PWD' => (string) $c['password']]);
        $p->setTimeout(600)->run();

        if (!$p->isSuccessful()) {
            @unlink($file);
            $this->error($p->getErrorOutput());
            return 1;
        }

        if (BackupVault::active()) {
            $file = $this->seal($file);
            if (!$file) {
                return 1;
            }
        } else {
            // shortcut: plaintext fallback so a missing key never stops backups; backup:health keeps warning until BACKUP_KEYS is set.
            $this->warn('BACKUP_KEYS / BACKUP_ACTIVE_KEY not set: dump left UNENCRYPTED. Run `php artisan backup:keygen`.');
        }

        $this->info('Backup written: ' . $file);
        $keep = $this->option('keep');

        return $this->call('backup:prune', $keep === null ? [] : ['--keep' => $keep]);
    }

    /** Encrypts the plaintext dump next to itself and removes the plaintext. @return string|null the .enc path */
    private function seal($plain)
    {
        $enc = $plain . '.enc';
        $in = fopen($plain, 'rb');
        $out = fopen($enc, 'wb');
        try {
            BackupVault::encrypt($in, $out);
        } catch (\Throwable $e) {
            fclose($in);
            fclose($out);
            @unlink($plain);
            @unlink($enc);
            $this->error($e->getMessage());
            return null;
        }
        fclose($in);
        fclose($out);
        unlink($plain);

        return $enc;
    }

    // shortcut: the .bak is written on the SQL Server host; it is not encrypted or pruned from here, clean it with a server-side job.
    private function sqlsrv(array $c)
    {
        $dir = config('backup.mssql_dir');
        if (!$dir) {
            $this->error('Set MSSQL_BACKUP_DIR in .env to a folder on the SQL Server host that the SQL Server service account can write.');
            return 1;
        }
        $name = str_replace(']', ']]', $c['database']);
        $file = rtrim($dir, '\\/') . '\\' . $c['database'] . '_' . date('Y-m-d_His') . '.bak';
        $file = str_replace("'", "''", $file);

        // COPY_ONLY so the app backup never disturbs the DBA's own backup chain.
        DB::connection()->unprepared("BACKUP DATABASE [$name] TO DISK = N'$file' WITH COPY_ONLY, INIT, CHECKSUM");
        $this->info('Backup written on the SQL Server host: ' . $file);

        return 0;
    }
}
