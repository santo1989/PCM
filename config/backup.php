<?php

return [

    // Path to the mysqldump binary. Left as just "mysqldump" it relies on PATH,
    // which a web server process doesn't always inherit the same way an interactive
    // shell does (confirmed true on this Windows/Laragon setup) — set MYSQLDUMP_PATH
    // in .env to an absolute path if backups fail with a "command not found" error.
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

    // Backups older than this many days are pruned after each successful run.
    'retention_days' => env('BACKUP_RETENTION_DAYS', 30),

];
