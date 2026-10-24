<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class RunDatabaseBackup extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Back up the application database to storage/app/database_backup';

    public function handle(DatabaseBackupService $service)
    {
        $path = $service->run();

        $this->info("Database backup written to {$path}");

        return self::SUCCESS;
    }
}
