# PCM — Working Notes

Laravel 8 (`php ^7.3|^8.0`) personal cash / expense management app.

_Updated 2026-09-04. Three large passes so far, in order: (1) app-wide bug-fix + redesign
(green/dark-slate theme), (2) vibrant-gradient re-theme replacing (1)'s visuals, (3) a
financial-analytics upgrade (rule-based KPIs, forecasting, AJAX live-refresh) — see git log._

## Pass 3: Financial Analytics Upgrade (rule-based, no external AI/queue/Redis/WebSockets)

The user asked for burn rate, runway, budget utilization, anomaly detection, savings-rate
trends, linear-regression forecasts, and near-real-time refresh across all 6 report pages
plus two new ones. The request assumed infrastructure this app doesn't have (Redis, Pusher/
Echo, a real queue worker) and asked for external AI (OpenAI/Hugging Face) despite the user
having explicitly chosen "no external AI" earlier in the same conversation — confirmed with
the user before building anything: stay rule-based (extend `InsightEngine`, already built),
AJAX polling instead of WebSockets, cache-on-compute instead of a queue worker.

- **`App\Services\FinancialAnalyticsService`** (new) — single home for every KPI: cash
  balance, burn rate, projected month-end balance, budget utilization (actual vs.
  `ProjectedExpense`), rolling savings-rate trend, 90th-percentile anomaly detection per
  category, cash runway, investment *contribution* trend (see honesty note below), and a
  hand-rolled linear-regression forecaster with a naive confidence band. Everything cached
  (file driver) and invalidated via `forgetAll()`, wired into the same write paths
  (`ExpenseCalculationController`/`HandCashController` store/update/destroy,
  `calculateAndSaveBudget()`) that already had dashboard-cache invalidation from Pass 1.
- **Honesty constraint**: the request asked for investment "ROI." This app has no market-
  valuation data, only contribution/withdrawal transactions — there's no way to compute a
  true return. Built a net-contribution-growth trend instead and labeled it as that, rather
  than fabricating a return percentage.
- **`App\Http\Controllers\AnalyticsController`** (new) — AJAX endpoints (`/home/data/kpis`,
  `/Budge_Projection/data/{forecast,compare-actual}`, `/interactive-dashboard/data/budget-alerts`)
  plus two new pages, **Cost Optimisation** (`/cost-optimisation`) and **Predictive Budget**
  (`/predictive-budget`, additive alongside Budget Projection's manual approach, not a
  replacement). Caught and fixed a real bug while building this: the forecast's training
  window originally included the current in-progress month as a full data point, skewing the
  regression negative and mislabeling which calendar months were being forecast — fixed to
  use 6 fully-completed prior months.
- Per-page additions, all built on the service above: Home gets a live-polling (30s) Financial
  Health widget; Budget Projection gets a forecast chart + confidence band + "Compare with
  Actual"; Yearly Report gets an explicit YoY/seasonal-trends callout + client-side what-if
  slider; Monthly Report gets a day-of-week spending chart, same-period-last-year comparison,
  and a top-saving-category card; Monthly Investment gets a compound-growth projection
  (explicitly labeled as using an assumed 7%/year return, not real data), an asset-allocation
  chart, and a 4%-rule retirement estimate; Interactive Dashboard gets a clickable category
  pie-chart drill-down, 30s auto-refresh, a what-if scenario builder, and a budget-exceeded
  alert banner.

## Pass 2: Vibrant-gradient re-theme

Replaced Pass 1's green/dark-slate/Nunito visual system with a vibrant-gradient one (purple-
blue primary + pink-orange/teal-blue/coral-yellow accents, Poppins font, Bootstrap 5.3.3,
Select2 with the bootstrap-5 theme) per explicit user request, reusing the same shared-
component architecture so the change propagated app-wide from a handful of foundation files.
Course-corrected mid-plan: the request assumed no working responsive sidebar existed and
called for converting to Bootstrap's native offcanvas component — inspecting the vendor CSS
first showed SB-Admin's own fixed-position/CSS-transform toggle already implemented exactly
that (hidden by default under 992px, static above it), so kept it and just fixed the one real
gap (the mobile dark backdrop didn't close the sidebar on click) instead of fighting the
existing working mechanism. Also fixed a latent bug found while re-theming: the shared
`input`/`select`/`autocomplete-input` Blade components hardcoded `class="form-control"` and
separately echoed `{{ $attributes }}` — passing a `class` from a call site would have produced
two `class` attributes (invalid HTML, second one silently dropped); fixed via
`$attributes->class([...])`, which is also what made Select2 classing possible at all.

## Pass 1: App-wide bug-fix + redesign

A full redesign touched every page in the app (design system, entry-form UX, rule-based
"instant analysis" panels) and, in the process, surfaced and fixed a long tail of
pre-existing bugs — several of them severe. Summary below; see commit history for detail.

### Critical bugs fixed
- **Registration was completely broken (500 error) since the app was deployed.** `RegisteredUserController`
  and `auth/register.blade.php` required `division_id`/`company_id`/`department_id`/`designation_id`/
  `emp_id`/`joining_date` — none of which exist as columns on `users`, and referenced
  `App\Models\Division`/`Company`/`Department`/`Designation`, none of which exist at all. No new user
  could ever sign up. Rebuilt around the fields that actually exist (name, email, mobile, dob,
  password, optional picture).
- **User profile edit/view pages were completely broken** for the same reason (same nonexistent
  models/columns referenced in `UserController` and the edit/profile views). Fixed the same way.
- **Dashboard crashed for any non-Admin user** (`role_id` 2/3) — `resources/views/backend/home.blade.php`
  included `layouts.User`/`layouts.Manager`, neither of which existed. Consolidated into one
  role-gated dashboard.
- **A recurring `types`/`rules` case-sensitivity bug** (`where('types', 'income')` instead of
  `'INCOME'`, against uppercase-stored data) was found and fixed in ~15 separate locations across
  controllers, a view composer, and inline view queries — it had been silently zeroing out income/
  expense figures on the interactive dashboard, the old Admin dashboard, the expense-calculations
  index summary bar, and the budget projection page.
- **`->withMessages()` vs `->withMessage()`**: every "created"/"updated" success redirect in
  `HandCashController`/`ExpenseCalculationController` flashed to the wrong session key, so those
  banners never appeared (only delete confirmations did). Fixed across both controllers.
- **`@can(Auth::user()->role_id = 1)`** in `users/edit.blade.php` — assignment instead of comparison,
  wrong argument type for `@can`. The Role dropdown never rendered for anyone. Fixed to `@can('Admin')`.
- Roles edit form/route verb mismatch (`@method('patch')` vs `Route::put`), dead "Issue Entry" stub
  views standing in for expense create/edit, Category `types` casing bug that silently zeroed
  `rules` on create, dead `users.create`/`users.store` routes, a broken auto-toggle script on the
  HandCash transfer form — all fixed.

### Dead code removed
`layouts/Admin.blade.php`, `dashboard.blade.php` + `layouts/app.blade.php` +
`layouts/navigation.blade.php` (unreachable stock Breeze scaffolding), `users/superindex.blade.php`
(referenced a nonexistent `SupervisorAssign` model), the two "Issue Entry" stub views, a stray
`projection_report.blade copy.php`, an unwired jQuery bulk-delete system, and a BS4-era
modal-switching script superseded by Bootstrap 5's native `data-bs-dismiss`.

### New shared infrastructure
- `config/finance.php` — single source of truth for the `types`/`rules` option lists that were
  previously hardcoded and drifting across 5+ Blade files.
- `App\Services\AutocompleteSource` + `<x-backend.form.autocomplete-input>` — "remember previous
  entries" datalist component, backed by one query (not N+1), applied to free-text `name` fields.
- `App\Services\InsightEngine` + `<x-backend.insights-panel>` — rule-based (no external AI/API)
  natural-language insight cards (savings rate, period-over-period change, top category) shown on
  the dashboard and CRUD index pages.
- `public/ui/backend/css/app-theme.css` — resolved the previously-conflicting brand colors
  (near-black variable vs. green buttons vs. blue chrome) onto one green primary + dark-slate
  sidebar/topbar; app-wide Nunito font. Removed the duplicate Bootstrap 4.5.1 CDN that was loading
  alongside Bootstrap 5.
- Auth pages (`login`, `register`, `forgot-password`, `reset-password`, `verify-email`,
  `confirm-password`) rebuilt from Tailwind/Breeze defaults (register.blade.php was previously a
  three-way Tailwind/Bootstrap/MDB collision) to match the Bootstrap system used everywhere else.

## Known remaining gaps (as of Pass 3)
- `PetiCashController` and `App\Models\ProjectedExpense`'s admin UI are still unrouted/orphaned
  (flagged previously; left alone since nothing references them and removing/wiring them up is a
  product decision, not a bug fix).
- No automated test suite committed — verification during this session relied on temporary
  `actingAs()` HTTP tests that were written, run, and deleted per batch. Worth turning into a
  permanent regression suite given how much was silently broken before this pass.
- `welcome.blade.php` (public landing page) is self-contained (own Bootstrap/MDB, doesn't use
  `master.blade.php`) and was left as-is — it's cosmetically inconsistent with the rest of the app
  but was not broken, and restyling it was out of scope for this pass.
- An untracked `localhost.sql` file exists in the project root (not created by this session) —
  worth checking it isn't meant to be gitignored if it's a DB dump.
- No Redis, no Pusher/Echo/WebSocket packages, `QUEUE_CONNECTION=sync` — confirmed in Pass 3.
  All "real-time" is 30s AJAX polling and all "background" work is cache-on-compute (file
  cache driver). Fine for a single-user local app; would need real infrastructure decisions
  before this could scale to multiple concurrent users or true push updates.
- Monthly Investment's compound-growth projection and 4%-rule estimate use an explicitly
  labeled **assumed** 7%/year return — this app has no real market-valuation data source, so
  there's no way to make this a true projection based on actual performance.
- Anomaly detection (`FinancialAnalyticsService::anomalies()`) needs at least 4 months of
  per-category history before it can flag anything — expect quiet/empty results on categories
  or time periods with little data yet.
