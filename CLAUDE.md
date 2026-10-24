# PCM

Auto-generated overview (2026-10-24); refine as the app is worked on.

## Stack
- Laravel ^8.75, MySQL `personal_cost_management` @ 127.0.0.1:3306 (local Laragon)
- Remote: https://github.com/santo1989/PCM.git

## Layout
- Controllers: Category, ExpenseCalculation, HandCash, Notification, PetiCash, ProjectedExpense, Role, User
- `app/Console/Commands/DbBackup.php`, `config/license.php`, `app/Http/Middleware/{CheckLicense,SecurityHeaders}.php` (added 2026-10-24)

## Operations (added 2026-10-24)
- Scheduler: needs a cron / Task Scheduler entry running `php artisan schedule:run` every minute; `db:backup` runs daily 02:00, keeps newest 14 dumps in `storage/app/backups` (`--keep=N`). Set `MYSQLDUMP_PATH` if `mysqldump` is not on PATH.
- Licence: `LICENSE_ENFORCE=false` by default. Enforce with `LICENSE_ENFORCE=true`, `LICENSE_KEY`, `LICENSE_EXPIRES_AT` (YYYY-MM-DD); expired/missing key returns HTTP 402. Middleware: `CheckLicense`, `SecurityHeaders` (web group).

## Work log
- 2026-10-24: `composer update` within constraints (Laravel L8.83.29 -> L8.83.29); audit 37 -> 2. Tests on a scratch DB only: before Tests: 17, Assertions: 25, Failures: 2, after Tests: 17, Assertions: 25, Failures: 2.
- 2026-10-24: pre-work zip + DB dump in `D:\NTG_APP\_backups\pre-work-2026-10-24`. Branch `chore/claude-hardening-2026-10-24`: added LICENSE, licence check, security headers, db:backup, docs; `.env` gitignored and untracked.

## Open items (need owner approval)
- `composer audit`: 2 advisories left in 2 packages (laravel/framework,league/flysystem); remaining ones need a major upgrade. Needs owner approval.
- Local `.env`: APP_ENV=local, APP_DEBUG=true. Must be production/false on any live server.
- `.env` was tracked in git and remains in earlier history; secrets there (e.g. APP_KEY) are not rotated, history not rewritten.
