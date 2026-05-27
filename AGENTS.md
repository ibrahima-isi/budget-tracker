# Repository Guidelines

## Project Structure & Module Organization

BudgetTrack is a Laravel 12 application with Inertia.js and Vue 3. Backend code lives in `app/`: controllers, models, policies, services, observers, and Artisan commands use standard Laravel subdirectories. Routes are split across `routes/web.php`, `routes/auth.php`, and `routes/console.php`. Frontend pages, layouts, and composables live in `resources/js/` organized by feature (Pages/Budgets, Pages/Expenses, Pages/Categories, etc.); Vue i18n translations live in `resources/js/i18n/`; CSS is in `resources/css/app.css`, and the root Blade shell is `resources/views/app.blade.php`. Database files are under `database/`. Tests are in `tests/Feature` and `tests/Unit`. All model and table names use English conventions (Expense, Revenue, Category) and are mapped via `$table` property; legacy French names (Depense, Revenu, Categorie) exist as model aliases for backward compatibility.

## Coding Practices & restrictions
- Use plan mode before editing more than 2 files or making significant changes. This allows for discussion and feedback before code is written.
- Avoid large refactors or architectural changes without prior discussion. Focus on incremental improvements and bug fixes.
- Avoid adding new dependencies without discussion. If a new package is needed, explain the use case and consider if it can be implemented with existing tools first.
- For UI changes, provide screenshots or screen recordings in the pull request description to illustrate the change and its impact on the user experience.
- When in doubt, ask for feedback early. It's better to get input on an idea or approach before investing time in implementation.
- Use 3 to 5 subagents for focused tasks. For example, if working on a new feature, you might have one subagent for backend logic, one for frontend implementation, and one for testing. This allows for more targeted assistance and clearer progress tracking.

## Build, Test, and Development Commands

- `composer install` and `npm install`: install PHP and Node dependencies.
- `composer setup`: install dependencies, create `.env`, generate the app key, run migrations, and build assets.
- `composer dev`: run Laravel, queue listener, logs, and Vite together for local development.
- `npm run dev`: start only the Vite frontend dev server.
- `npm run build`: build production frontend assets.
- `composer test` or `php artisan test`: clear config and run PHPUnit.

## Coding Style & Naming Conventions

Use 4-space indentation, LF endings, UTF-8, and final newlines as defined in `.editorconfig`. Format PHP with Laravel Pint (`./vendor/bin/pint`) before submitting broad PHP changes. Keep Laravel class names singular and descriptive (`BudgetPolicy`, `DashboardService`), Vue pages in PascalCase, and composables as `useThing.js`. Use Eloquent and Query Builder; avoid raw SQL except for existing encryption functions and PostgreSQL-specific pgcrypto operations. In Inertia views, use `<Link>` and `useForm()` instead of manual navigation or ad hoc Axios calls. Finance-related models (Expense, Revenue, Budget) use `FinanceCacheObserver` to invalidate cached dashboard data on create/update/delete; always include `user_id` in queries to leverage cache key versioning. Currency filtering is applied via `applyCurrency($query, $currency)` methods in services; pass `currency_code` from session or request. Multi-language strings use vue-i18n key groupings (e.g., `amounts.label`, `categories.title`) referenced via `{{ $t('amounts.label') }}` in Vue components.

## Frontend Conventions

Vue pages organize by feature folder (Pages/Budgets, Pages/Expenses, Pages/Categories, Pages/Revenues, Pages/Settings). Use the `useForm()` composable from `@inertiajs/vue3` for all form submissions (never manual fetch/axios); the composable handles CSRF tokens and automatic redirects. For client-side validation and state management, leverage Inertia's prop-to-component data binding and the `form` object's error and progress tracking. Composables include: `useFormatMoney()` for XOF formatting with `Intl.NumberFormat`, `useFlash()` for reading Laravel flash messages (`success`, `error`), `useDarkMode()` for toggling dark mode via `localStorage`, and `useLocale()` for reading the current language key. Import and use vue-i18n's `{{ $t() }}` function in templates for all user-facing text; keys are namespaced (e.g., `dashboard.summary.balance`, `budgets.form.label`). Reusable components in `resources/js/Components/` include `AppModal`, `AppBadge`, `StatCard`, `BudgetProgress`, and `PeriodFilter` for month/year selection; pass props and slots to customize behavior.

Brand assets live in `public/`. The GUI CONNECT favicon is `public/favicon.svg` with `public/favicon.ico` as the fallback; the root Blade shell (`resources/views/app.blade.php`) links both explicitly. Keep favicons simple and legible at 16px/32px, and avoid replacing them with generated build assets.

## Testing Guidelines

PHPUnit is configured in `phpunit.xml` with Unit and Feature suites. Tests run against SQLite in-memory using array cache, mail, and session drivers, so no local database is required. Name tests after the behavior and place them in the matching suite, for example `tests/Feature/BudgetControllerTest.php` or `tests/Unit/Services/EncryptionServiceTest.php`. Run a focused file with `php artisan test tests/Feature/BudgetControllerTest.php`; use `php artisan test --coverage` when coverage evidence is needed. The PostgreSQL pgcrypto encryption feature tests are skipped under the default SQLite test setup; run them against PostgreSQL with `DB_CONNECTION=pgsql` and valid `APP_PUBLIC_KEY` / `APP_PRIVATE_KEY` values.

## Key Services & Patterns

**DashboardService** — centralizes dashboard data queries with per-user caching. Methods like `monthly()`, `annual()`, and `recentExpenses()` use `AppCache::financeKey()` to generate user-scoped cache keys that include a versioning UUID. Call it from `DashboardController` to retrieve multi-month/year summaries and expense breakdowns by category. Always pass `$currency` to filter by currency code; pass `'all'` to include all currencies.

**AppCache** — static utility for managing finance-related caches. Use `financeKey($userId, $name, $parts)` to create versioned, user-scoped cache keys. Call `bumpFinanceVersion($userId)` to invalidate finance caches when Expense/Revenue/Budget records change. The `FinanceCacheObserver` automatically bumps the version on `saved()` and `deleted()` events (`app/Observers/FinanceCacheObserver.php`), so queries are always cache-safe.

**EncryptionService** — handles PGP encryption/decryption for sensitive user fields (name, email). Do not log or return plaintext PII directly; use `User::findDecrypted()` or `User::findByEmail()` when decrypted data is required. Private/public keys and email hash key are stored in environment variables (`APP_PRIVATE_KEY`, `APP_PUBLIC_KEY`, `APP_EMAIL_HASH_KEY`).

**Security throttling** — login brute-force protection is implemented in `app/Http/Requests/Auth/LoginRequest.php` and configured in `config/security.php`. It uses separate RateLimiter keys for failed-attempt counting and the lockout timer so the 5-minute lockout starts when the threshold is reached. Global dynamic request throttling is prepended to the web middleware stack via `ThrottleDynamicRequests`; it limits requests by IP and network and returns 429 with `Retry-After` headers. Keep `health` and `up` excluded so Railway health checks are not blocked.

**User approval** — new registrations are created as unapproved users. Approved-resource routes use `middleware(['auth', 'verified', 'approved'])`, and admins manage approvals at `/settings/users` through `UserManagementController`. Do not remove approval checks from dashboard, finance, settings, or profile routes; unapproved users must not reach app resources even if they have valid credentials.

**Currency & Settings** — admin-only routes in `/settings` and `/settings/currencies` allow management of currencies, default currency, business name, logo, and app language (fr / en / es). Currency preferences are session-scoped; users select a currency via `POST /user/currency` to filter dashboard and resource lists. All financial amounts default to XOF (West African franc CFA); format via `useFormatMoney` composable in Vue.

**Activity Logging** — `ModelActivityObserver` tracks create/update/delete events on core models; logs are admin-only visible via `GET /activity-logs`. Use for audit trails but do not rely on activity logs for critical business logic.

## Commit & Pull Request Guidelines

Recent history favors short imperative subjects such as `Fix yearly period filters`, with occasional conventional prefixes like `feat:` or `fix(scope):`. Keep commits focused and mention migrations, encryption, or deployment changes explicitly. Pull requests should include a concise description, test results, linked issues when applicable, and screenshots for UI changes.

## Security & Configuration Tips

User `name` and `email` are encrypted. Do not log plaintext PII, commit private keys, or bypass `User::findDecrypted()` / `User::findByEmail()` when decrypted user data is required. Keep `APP_PRIVATE_KEY`, `APP_PUBLIC_KEY`, and `APP_EMAIL_HASH_KEY` in environment-managed secrets, and review `ENCRYPTION.md` before changing auth or user storage flows. Admin routes are protected via `middleware('admin')` on group in `routes/web.php`; check `app/Http/Middleware/EnsureUserIsAdmin.php` for the middleware logic. App-resource routes require `middleware(['auth', 'verified', 'approved'])`, including dashboard, finance resources, settings, and profile. For deployment on Railway, set `DB_SSLMODE=verify-full` and `SESSION_DRIVER=database` to ensure secure database connections and persistent sessions across instances. When running Railway commands locally on macOS against Neon with `DB_SSLMODE=verify-full`, override `DB_SSLROOTCERT` to a local CA bundle such as `/opt/homebrew/etc/openssl@3/cert.pem` rather than disabling SSL verification.
