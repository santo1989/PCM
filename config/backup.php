<?php

return [

    // Path to the mysqldump binary. Left as just "mysqldump" it relies on PATH,
    // which a web server process doesn't always inherit the same way an interactive
    // shell does (confirmed true on this Windows/Laragon setup) — set MYSQLDUMP_PATH
    // in .env to an absolute path if backups fail with a "command not found" error.
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'mysqldump'),

    // Backups older than this many days are pruned after each successful run.
    'retention_days' => env('BACKUP_RETENTION_DAYS', 30),

    // AES-256-GCM key ring: "id:base64key,id2:base64key2". Generate with `php artisan backup:keygen`; keep it in .env, never in git.
    // Rotation = add a new key and make it active; keep old keys in the list so old backups stay restorable.
    'keys' => env('BACKUP_KEYS', ''),
    'active_key' => env('BACKUP_ACTIVE_KEY', ''),
    'chunk_bytes' => 4 * 1024 * 1024,

    // Retention: newest N, plus one per day for X days, plus one per month for Y months.
    'keep_last' => (int) env('BACKUP_KEEP_LAST', 14),
    'keep_daily_days' => (int) env('BACKUP_KEEP_DAILY_DAYS', 30),
    'keep_monthly' => (int) env('BACKUP_KEEP_MONTHLY', 12),

    // backup:health fails when the newest backup is older than this.
    'stale_after_hours' => (int) env('BACKUP_STALE_AFTER_HOURS', 26),

    // backup:restore-test loads the latest dump into this SCRATCH database (never the live one) and drops it. Empty = skipped.
    'verify_database' => env('BACKUP_VERIFY_DATABASE'),

    'mysql' => env('MYSQL_PATH', 'mysql'),
    'mssql_dir' => env('MSSQL_BACKUP_DIR'),
];
