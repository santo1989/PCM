<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DatabaseBackupService
{
    private const DISK_DIR = 'database_backup';

    public static function backupDir(): string
    {
        return storage_path('app/' . self::DISK_DIR);
    }

    public static function hasTodaysBackup(): bool
    {
        // run() names files backup_{Y-m-d_His}.sql, so "today" is a glob, not one fixed path.
        foreach (glob(self::backupDir() . '/backup_' . now()->format('Y-m-d') . '_*.sql') ?: [] as $path) {
            if (File::size($path) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Runs mysqldump for the app's configured connection and writes the result under
     * storage/app/database_backup. Throws on failure — callers decide how to surface that.
     */
    public function run(): string
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if (!File::isDirectory(self::backupDir())) {
            File::makeDirectory(self::backupDir(), 0755, true);
        }

        $destination = self::backupDir() . '/backup_' . now()->format('Y-m-d_His') . '.sql';

        $command = [
            config('backup.mysqldump_path'),
            '--host=' . $config['host'],
            '--port=' . $config['port'],
            '--user=' . $config['username'],
            '--single-transaction',
            '--result-file=' . $destination,
            $config['database'],
        ];

        $process = new Process($command, null, [
            // Password via env, not a CLI arg, so it doesn't show up in process listings.
            'MYSQL_PWD' => $config['password'],
            // Under the web SAPI (Apache/PHP-FPM), PHP's visible environment often lacks
            // SystemRoot — mysqldump then fails Winsock init with "Can't create TCP/IP
            // socket (10106)" even though the exact same command works fine from a CLI
            // shell, where the OS environment is inherited in full. Force it explicitly
            // so the child process can always load ws2_32.dll regardless of SAPI.
            'SystemRoot' => getenv('SystemRoot') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: (getenv('SystemRoot') ?: 'C:\\Windows'),
        ]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $this->pruneOldBackups();

        return $destination;
    }

    private function pruneOldBackups(): void
    {
        $cutoff = now()->subDays((int) config('backup.retention_days'));

        foreach (File::files(self::backupDir()) as $file) {
            if (\Carbon\Carbon::createFromTimestamp($file->getMTime())->lt($cutoff)) {
                File::delete($file->getPathname());
            }
        }
    }
}
