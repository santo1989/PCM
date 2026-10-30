<?php

namespace App\Console\Commands;

use App\Support\BackupVault;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupRestoreTest extends Command
{
    protected $signature = 'backup:restore-test';
    protected $description = 'Prove the latest dump restores: load it into BACKUP_VERIFY_DATABASE (a scratch DB), count tables, drop it';

    public function handle()
    {
        $c = config('database.connections.' . config('database.default'));
        $scratch = (string) config('backup.verify_database');
        if ($c['driver'] !== 'mysql' || $scratch === '') {
            $this->line('Skipped: needs a mysql connection and BACKUP_VERIFY_DATABASE (a scratch database name).');
            return 0;
        }
        // Never touch the live database.
        if (!preg_match('/^[A-Za-z0-9_]+$/', $scratch) || strcasecmp($scratch, $c['database']) === 0) {
            $this->error('BACKUP_VERIFY_DATABASE must be a different, plain-named scratch database.');
            return 1;
        }
        $files = BackupVault::files($c['database']);
        if (!$files) {
            return $this->fail('No backups to test.');
        }

        $sql = tempnam(sys_get_temp_dir(), 'rt');
        try {
            $src = fopen($files[0]['path'], 'rb');
            $dst = fopen($sql, 'wb');
            if (substr($files[0]['path'], -4) === '.enc') {
                BackupVault::decrypt($src, function ($plain) use ($dst) {
                    fwrite($dst, $plain);
                });
            } else {
                stream_copy_to_stream($src, $dst);
            }
            fclose($src);
            fclose($dst);

            $recreate = BackupVault::mysql($c, ['-e', "DROP DATABASE IF EXISTS `$scratch`; CREATE DATABASE `$scratch`"]);
            if (!$recreate->isSuccessful()) {
                return $this->fail('Cannot create scratch database: ' . trim($recreate->getErrorOutput()));
            }
            $in = fopen($sql, 'rb');
            $load = BackupVault::mysql($c, ['--database=' . $scratch], $in);
            fclose($in);
            if (!$load->isSuccessful()) {
                return $this->fail('Restore failed: ' . trim($load->getErrorOutput()));
            }
            $count = BackupVault::mysql($c, ['-N', '-e', "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$scratch'"]);
            $tables = (int) trim($count->getOutput());
            BackupVault::mysql($c, ['-e', "DROP DATABASE `$scratch`"]);
        } catch (\Throwable $e) {
            return $this->fail($e->getMessage());
        } finally {
            @unlink($sql);
        }

        if ($tables === 0) {
            return $this->fail('Restored database has no tables.');
        }
        $this->info(basename($files[0]['path']) . " restored OK ({$tables} tables).");

        return 0;
    }

    private function fail($msg)
    {
        Log::critical('Backup restore test: ' . $msg);
        $this->error($msg);

        return 1;
    }
}
