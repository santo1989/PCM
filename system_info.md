# PCM system info

## Current state (2026-10-24, branch chore/claude-hardening-2026-10-24)
- Laravel ^8.75, PHP 8.1.10, MySQL `personal_cost_management` local
- Licence file + enforceable expiry check (disabled by default); security headers on web group
- `db:backup` scheduled daily 02:00 (needs scheduler entry)
- `.env` gitignored; `.env.example` has licence/backup keys
- Dependencies: `composer update` within existing constraints applied 2026-10-24 (Laravel L8.83.29 -> L8.83.29); advisories 37 -> 2

## Previous state (main @ 60fe550 update logic)
- No LICENSE, licence check, db:backup or security headers
- `.env` was tracked in git
- Pre-work backup: `D:\NTG_APP\_backups\pre-work-2026-10-24\PCM.zip` and `db\personal_cost_management.sql`(MySQL dump)
