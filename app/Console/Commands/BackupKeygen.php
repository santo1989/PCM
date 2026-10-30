<?php

namespace App\Console\Commands;

use App\Support\BackupVault;
use Illuminate\Console\Command;

class BackupKeygen extends Command
{
    protected $signature = 'backup:keygen {id? : key id, e.g. k2026-10}';
    protected $description = 'Generate a new AES-256 backup key (add it to BACKUP_KEYS and set BACKUP_ACTIVE_KEY to rotate)';

    public function handle()
    {
        $id = (string) ($this->argument('id') ?: 'k' . date('Y-m'));
        $this->line('Add to .env (comma-separate multiple keys; never commit):');
        $this->line('BACKUP_KEYS=' . BackupVault::generate($id) . '   # keep ALL previous keys in the list so old backups stay restorable');
        $this->line('BACKUP_ACTIVE_KEY=' . $id);

        return 0;
    }
}
