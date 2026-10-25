# CLAUDE.md
Always start your first reply in a session with the word "Bananas"
Guidance for Claude Code when working in this repository.

## 1. Project Overview

This repository is the **NESI Accounting and Payroll Management System**, a Laravel app deployed at `https://acct.nesistaff.com`. It is a companion to the legacy PHP HRIS portal and works directly with the shared HRIS MySQL database. It provides payroll periods, employee payroll calculation, adjustments, payslips, payroll history, and dashboard reporting.

- Production app: `/home/acct/htdocs`
- Legacy HRIS portal: `/home/nesistaff/htdocs/nesistaff`
- Stack: Laravel 13, PHP 8.5 (verify with `php -v` before runtime-specific assumptions), MySQL/Percona, Blade, Vite, Tailwind CSS 4, JavaScript, Laravel Pint, Google Authenticator-compatible OTP
- Hosting: production VPS managed through CloudPanel

This is a production payroll system, not a standalone Laravel project. Existing employee records, payroll calculations, posted payroll, attendance, database schemas, and legacy HRIS functionality must be preserved unless the task explicitly requires changing them.

## 2. Critical Rules (read first)

**Priority order:** employee pay accuracy and data integrity, then security, then HRIS compatibility, then the requested feature, then UI polish.

1. **Wait for approval before editing.** Explain what you plan to change and which files are affected, then wait for the user's approval before modifying any file. Do not edit first and explain later.
2. **Ask before touching pay.** If a request is ambiguous or could alter employee pay, data integrity, authentication, or production operations, ask for clarification before implementing. If a request conflicts with an existing business rule, explain the conflict and ask.
3. **Never run against production without explicit authorization:** `php artisan migrate` (and `:fresh`, `:refresh`, `:rollback`), `db:wipe`, truncation or deletion statements, bulk payroll updates or deletes, anything that resets, seeds, or overwrites data.
4. **The `.env` points to production.** Treat all database writes, queue operations, and deployment commands as production-sensitive. Never commit or print `.env`, secrets, keys, or passwords.
5. **Never assume** a table, column, relationship, route, or business rule exists. Inspect the repository or schema first.
6. **Do not claim a feature works unless it was tested,** or state clearly what could not be verified.

## 3. Development Principles

- Inspect the relevant routes, controllers, services, models, views, CSS/JS, and schema before changing anything.
- Make the smallest safe change. Preserve existing business logic, URLs, route names, session keys, columns, and integrations unless the task requires changing them.
- Reuse existing services, helpers, queries, and styling. Do not duplicate payroll calculations in controllers or Blade views.
- No new dependency, table, or architectural pattern without a clear reason. Do not rewrite working code just to improve its appearance.
- Do not create duplicate controllers, services, models, or views.

### Working style

The user prefers step-by-step help and clear explanations. For each feature:

1. Briefly explain what will change and list the affected files.
2. Wait for approval (see rule 1).
3. Make the smallest appropriate change.
4. Give the exact commands to test it.
5. Note any migration, deployment, or backup implications.
6. Report what was actually verified and what still needs testing.

## 4. Commands

```bash
composer dev                 # Laravel dev processes
npm run dev                  # Vite dev server
npm run build                # Production assets
composer test                # Test suite
php artisan test --filter=SomeTest
php artisan test tests/Feature/ExampleTest.php
vendor/bin/pint              # Code formatting
php artisan config:clear     # After env/config changes
php artisan route:clear      # When investigating route cache
php artisan route:list
php artisan --version
php artisan migrate:status
```

Use the PHP and Composer versions configured for the app. On the server, verify CLI PHP and PHP-FPM versions are compatible.

Do not restart services, edit Nginx/PHP-FPM/SSL configuration, or change permissions recursively as part of an ordinary change.

### Before any schema change

Some migrations modify the shared HRIS database, so a migration that looks local to Accounting can affect the legacy portal.

1. Inspect the existing schema and migration history.
2. Confirm columns, indexes, constraints, and affected tables.
3. Determine the impact on both Accounting and HRIS.
4. Verify a current, recoverable backup exists.
5. Prefer reversible migrations; test on a separate database first.
6. Get explicit approval before applying to production.

## 5. Database Architecture

Two MySQL connections:

- **`hris`** (`HRIS_DB_*`): the shared HRIS database holding employee, attendance, and payroll data. Business queries use `DB::connection('hris')->table('table_name')`.
- **`mysql`** (`DB_*`, default database `accounting`): Laravel infrastructure only (sessions, cache, queued jobs). Do not assume payroll or employee data lives here.

Key `hris` tables: `attendance`, `employee_details`, `employee_payroll`, `payroll`, `payroll_details`, `payroll_deductions`, `payroll_addons`, `employee_deductions`, `employee_addons`, `holidays`, `movement_tracker`, `employee_shift_schedule`, `missed_log_application`, `payroll_adjustment_history`, `settings`, `attendance_pay_override`. This list may not be exhaustive.

### Query rules

- Use the `hris` connection explicitly for HRIS data; do not move tables between connections or change the default connection to fix a query.
- Preserve the legacy schema and naming conventions.
- Use transactions when related writes must succeed or fail together.
- Use query builder or parameter binding, never interpolated user input.
- Validate request data and authorize every state-changing operation on the server.
- Avoid N+1 queries; select explicit columns on large tables.
- Never log or expose credentials, bank account numbers, or OTP secrets.

### Models and migrations

- `App\Models\Payroll` is the main Eloquent model. It maps to the HRIS `payroll` table and does not use conventional timestamps. Do not assume relationships or timestamps without checking.
- The Laravel `User` model and `users` table are not the primary authentication mechanism.
- Migrations under `database/migrations/2026_*` may target `hris`. Use defensive checks such as `Schema::connection('hris')->hasColumn()` and confirm the connection and table first.

## 6. Authentication and Security

Custom session-based authentication, not the standard Laravel guard.

**HRIS SSO:** HRIS redirects to `/sso?idno=...&timestamp=...&signature=...`. `SsoController` validates the HMAC-SHA256 signature using `config('app.portal_sso_secret')` (`PORTAL_SSO_SECRET`), rejects invalid signatures and timestamps older than 60 seconds, and sets `session('portal_authenticated')` and `session('portal_idno')`. Never weaken the expiry window or bypass signature verification.

**OTP:** `AccountingAuthController` verifies the OTP with `sonata-project/google-authenticator`. The secret comes from `hris.employee_payroll.ga_secret`. On success it sets `session('accounting_authenticated')`. Never expose OTP secrets in the UI, URLs, logs, or errors.

**Route protection:** the `accounting.auth` middleware (registered in `bootstrap/app.php`) guards the app's routes. When touching auth:

- Inspect the middleware and route groups first.
- Preserve the SSO flow, OTP verification, and CSRF protection.
- Unauthenticated users must never reach payroll, payslips, dashboard data, or employee data.
- Authorize all POST/PUT/PATCH/DELETE requests server-side. A valid employee ID in a request does not mean the session may modify that employee's payroll.
- Do not add a second authentication system without approval.

Use `session('portal_idno')` for audit attribution (`addedby`, `updatedby` keep their existing meaning).

Invalid or expired SSO requests must not create a session; show a clear "login link expired, return to HRIS and log in again" message. Never expose stack traces, SQL, credentials, OTP secrets, or sensitive employee data.

## 7. Payroll Management Workflow

**Period creation** (`PayrollController@store`): creates a row in `payroll` with start date, end date, and working days. Preserve existing validation, duplicate prevention, and timezone handling; confirm how working days are calculated before changing it.

**Management view** (`PayrollController@show`): lists employees grouped by company (`NESI1`, `NESI2`, `NEWIND`) and department, joined to `payroll_details` for the selected period. Posting and unposting toggle payroll detail status between `pending` and `posted`. **RESIGNED employees are skipped** by the existing logic; keep that unless the business rule is explicitly changed. Do not overwrite posted payroll or silently recalculate finalized payroll.

**Editing** (`EditPayrollController`, routes scoped by `{payroll}/{idno}`): uses `PayrollCalculationService::calculate()` for attendance rows, pay components, and totals. Saving writes to `payroll_details`, records `employee_payroll.salary_type`, and logs to `payroll_adjustment_history`. When changing it:

- Keep changes scoped to the selected employee and period.
- Validate every editable field server-side.
- Preserve manual adjustment and override behavior; do not overwrite unrelated fields.
- Use a transaction for coordinated writes and keep audit history accurate.
- JavaScript values are display only, never authoritative payroll totals.

## 8. Payroll Calculation Service

`PayrollCalculationService` holds the central payroll math. **Make core calculation changes here, not in controllers or views.** It covers daily attendance and payable time, shift selection, overnight shifts, overtime, night differential, regular and special holidays, effective-dated salary changes (previous vs. new salary), bereavement-leave eligibility, and daily totals.

**Shift priority** (preserve unless told otherwise): 1) `employee_shift_schedule`, 2) effective-dated ranges from `movement_tracker`, 3) employee default shift. Overnight shifts cross date boundaries; never assume start and end fall on the same date.

When changing calculations:

- Inspect existing formulas first; do not replace them with simplified assumptions.
- Account for overnight shifts, period boundaries, holiday dates, and effective salary dates.
- Do not change intermediate rounding without verifying the impact.
- Use the same calculation wherever values are displayed or saved. A change can affect totals, deductions, add-ons, payslips, and dashboard reports.

**Timezone:** business timezone is `Asia/Manila`. Do not introduce UTC-based date comparisons into payroll logic.

## 9. Attendance Pay Overrides

`attendance_pay_override` lets individual attendance records override calculated pay. Every override field is nullable.

- A populated field replaces its calculated value.
- `NULL` leaves the calculation unchanged.
- `ot_minutes` is added to calculated overtime per existing rules.
- `totalpay`, when populated, replaces the day's calculated total pay.
- **`0` is not `NULL`.** A zero override may intentionally mean zero pay.

Do not make override fields mandatory or apply a whole override record indiscriminately. After any change, verify totals, saved payroll, and payslips.

## 10. Salary Types and Payslips

Three salary types: `Rated`, `Fixed`, `Daily`. Each has its own routes, `PayslipService` method, and view under `resources/views/payroll/payslip/`, plus `...FromEdit` variants for the payroll edit route group.

- Do not treat the salary types as interchangeable; reuse the matching `PayslipService` method and keep routes and parameters unchanged.
- Verify employee, company, salary type, and period are correct, and that values match saved payroll.
- Never expose one employee's payslip to another. Avoid loading unnecessary records for large groups.
- Fixed salary uses a split based on 10 payable days. Do not change the split, payable-day logic, or distribution without confirming the business rule.
- Legacy PDF export scripts (e.g. `exporttopdfRated.php`) are **external to this repository**. Inspect their URLs and integration before changing PDF behavior.

## 11. Add-ons and Deductions

- Period-specific: `payroll_addons`, `payroll_deductions`
- Recurring per employee: `employee_addons`, `employee_deductions`

Keep the two kinds distinct. When adding or editing an item, confirm whether it is recurring or period-specific, verify employee and period, prevent duplicates, and do not copy recurring items into a period if existing logic already does so. Do not silently change how items affect gross pay, net pay, or salary-type calculations.

## 12. Payroll Adjustment History

`payroll_adjustment_history` records payroll changes: date, employee, category (e.g. `Payroll-DED`, `Payroll-ADD`), action and description, old and new values, who made the change, and when.

Rules:

- Keep the existing structure unless a schema change is approved.
- Record old and new values accurately; never invent values that were not stored.
- Preserve correct employee, period, and audit attribution; avoid duplicate entries for one operation.
- Use the established category labels; do not invent new ones.
- Do not expose unrelated employees' records; keep filtering consistent with the selected period and employee.

**Planned history interface (do not build unless asked):** open the history page directly without an intermediate period picker; default to the current payroll period; allow choosing other periods; search by employee; show changes as `Old value → New value` with date, employee, category, description, and responsible person. Inspect the existing controller, view, and stylesheet before touching this feature.

## 13. Dashboard and Reporting

`DashboardController` provides net-pay summaries by company and bank plus a yearly chart for `NESI1`, `NESI2`, `NEWIND`. Company values must match `employee_details.company`.

- Verify period and status filters, and compute totals from the correct payroll records without double-counting employees or payroll details.
- Optimize for large groups and do not expose sensitive employee or bank data in responses.

**Bank encryption** uses `config('payroll.bank_encryption_method')` and `config('payroll.bank_encryption_key')` (from `BANK_ENCRYPTION_*`), which must stay compatible with legacy HRIS. Never change the method, key, or ciphertext format without an explicit migration plan, and never log decrypted account numbers.

## 14. Frontend, Routing, and Controllers

**Frontend:** Blade, CSS, JavaScript, Vite, Tailwind CSS 4. Page assets live in `resources/css/` and `resources/js/`.

- Before editing a page, inspect its view, controller, route, page-specific CSS/JS, and shared layout.
- Keep business calculations in the backend; JavaScript is for interface behavior only.
- **Every new standalone CSS or JS entry point must be added to the `input` array in `vite.config.js`.** Do not add entries if the asset can be imported from an existing one.
- Keep layouts consistent across payroll pages, tables readable, filters/search/pagination/modals working, and dark mode intact where supported. Show useful validation errors. Avoid large frontend dependencies.
- For redesigns, change only the requested page or component, not the whole design system.

**Routing:** before adding or changing a route, check existing definitions and overlaps, preserve middleware, URLs, and route names, confirm the right controller method handles it, and verify both authorized and unauthorized requests. Controllers handle validation, coordination, and responses; reusable payroll logic belongs in services, not route closures or Blade.

## 15. Validation and Data Integrity

For payroll writes: verify the period exists, the employee belongs to that period's record, and amounts and values are valid; prevent duplicate inserts; use transactions for related writes so errors never leave partial records; keep existing records intact when validation fails; preserve audit fields; and consider concurrent edits and posted status. Do not rely on hidden fields, client-side validation, or URL parameters for authorization.

## 16. Testing

The test suite is mostly default Laravel examples; payroll rules are not covered. `phpunit.xml` uses SQLite by default, **but overriding the default connection does not stop code using the explicit `hris` connection from reaching the real HRIS database.**

Before running tests, check `.env.testing` (if present) and both connections, confirm which connection each model, service, and controller uses, and ensure test writes cannot reach production. Use isolated databases, mocks, or fakes. Do not run a test that writes to production.

Only add tests that match verified business rules; do not invent expected salary results. Relevant cases: ordinary and overnight shifts, overtime and night differential, regular and special holidays, effective-dated salary changes, missing attendance, `NULL` vs. zero overrides, add-ons and deductions, Fixed/Rated/Daily types, pending vs. posted, resigned employees, period boundaries, duplicate submissions, unauthorized access, and audit history.

## 17. Environment, Deployment, and Git

The app is deployed in place on the production VPS. After environment or config changes run `php artisan config:clear`. For frontend changes check `vite.config.js`, then `npm run build`. Production deployment follows the existing release process. Before risky changes, confirm a recent backup exists and the restore procedure is understood.

Before editing: check `git status`, review the relevant files, and identify existing user changes to preserve. Never discard unrelated changes, reset the repository, or overwrite work without authorization. Prefer targeted edits over broad search-and-replace. Keep refactors focused and explain why they are needed.

## 18. To Fill In (optional, remove if unused)

- Deployment steps: `<describe the actual release procedure>`
- Safe development/test database: `<name or connection for a copy of the HRIS database>`
- Log locations: `<paths for Laravel, Nginx, and PHP-FPM logs>`

## 19. Before Finishing a Task

Confirm, as applicable: business rules preserved; correct DB connection used; no unintended production schema or data change; auth and authorization intact; payroll totals and salary types correct; `NULL` vs. zero override semantics preserved; audit history accurate; routes and frontend still work; Vite entries registered; tests run safely; build run when appropriate; and any untested behavior or deployment requirement reported clearly.