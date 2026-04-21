# BudgetTrack

![CI/CD](https://github.com/ibrahima-isi/budget-tracker/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![Composer](https://img.shields.io/badge/Composer-2.9-885630?logo=composer&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql&logoColor=white)

A personal budget tracking web application built with Laravel 12, Inertia.js, and Vue 3. Track your budgets, expenses, revenues, and categories with a clean interface, dark mode, and multi-language support.

---

## Features

- **Budgets** — create monthly or annual budgets, track planned vs. spent amounts in real time
- **Expenses** — log expenses against a budget and category, filter by budget or category
- **Revenues** — record income sources by date, automatically grouped by month/year
- **Categories** — manage expense categories with custom colors and icons
- **Dashboard** — summary cards, budget progress bar, donut chart by category, last 5 expenses
- **Admin backoffice** — manage app settings (business name, logo, language, default currency) and currencies
- **Dark mode** — toggleable, persisted in `localStorage`, respects system preference on first visit
- **Multi-language** — French, English, Spanish (set by admin in Settings; applied app-wide via vue-i18n)
- **Email verification** — required on registration, sent via Brevo HTTP API
- **Private logo storage** — logo served through a controller, never exposed via public `/storage/` URL
- **CI/CD** — GitHub Actions runs 250 tests on every push; deploys to Railway only when all pass

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12, PHP 8.4 |
| Frontend | Vue 3, Inertia.js, Tailwind CSS v3 |
| Auth scaffold | Laravel Breeze |
| Database | PostgreSQL (Neon) — MySQL also supported |
| Email | Brevo HTTP API (`symfony/brevo-mailer`) |
| Charts | Chart.js via vue-chartjs |
| i18n | vue-i18n (fr / en / es) |
| Hosting | Railway |
| CI/CD | GitHub Actions |

---

## Requirements

- PHP 8.2+
- Composer
- Node.js 22+
- PostgreSQL (or MySQL)
- A [Brevo](https://www.brevo.com) account for transactional email

---

## Local Setup

### 1. Clone and install dependencies

```bash
git clone https://github.com/ibrahima-isi/budget-tracker.git
cd budget-tracker
composer install
npm install
```

### 2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and fill in at minimum:

```env
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=budgettrack
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

MAIL_MAILER=brevo
BREVO_KEY=your-brevo-api-key
MAIL_FROM_ADDRESS=you@example.com
MAIL_FROM_NAME="BudgetTrack"
```

> **MySQL users:** change `DB_CONNECTION=mysql` and `DB_PORT=3306`. All queries use Eloquent — no raw SQL.

### 3. Run migrations and seed

```bash
php artisan migrate
php artisan db:seed          # seeds categories, budgets, revenues, expenses + an admin user
```

The seeder creates an admin account at `admin@example.com` / `password`.

### 4. Build frontend and start the dev server

```bash
npm run dev
php artisan serve
```

Visit `http://localhost:8000`.

---

## Admin Account

The first time you seed, `admin@example.com` is created as an admin.

To promote any existing user to admin:

```bash
php artisan admin:make user@example.com
```

Admin users see a **Settings** link in the navbar to manage:
- Business name, email, phone
- Logo (privately stored, served via `/logo`)
- App language (fr / en / es) — applies to all users
- Default currency
- Currency list (add, edit, enable/disable, set default)

---

## Running Tests

```bash
php artisan test
```

Run a single test file:

```bash
php artisan test tests/Feature/BudgetControllerTest.php
```

Run with coverage (requires Xdebug or PCOV):

```bash
php artisan test --coverage
```

The test suite uses **SQLite in-memory** — no database setup required. 250 tests covering models, policies, controllers, and auth flows.

---

## Deployment (Railway)

### First deploy

1. Create a new project on [Railway](https://railway.app) and add a service from your GitHub repo
2. Set the following environment variables in the Railway dashboard:

```env
APP_NAME=BudgetTrack
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-railway-url.up.railway.app
APP_KEY=                        # generate with: php artisan key:generate --show

DB_CONNECTION=pgsql
DB_HOST=...                     # from your Neon/Railway PostgreSQL connection string
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
DB_SSLMODE=require

SESSION_DRIVER=database

MAIL_MAILER=brevo
BREVO_KEY=your-brevo-api-key
MAIL_FROM_ADDRESS=you@example.com
MAIL_FROM_NAME="BudgetTrack"
```

3. Railway auto-deploys on push using `nixpacks.toml`. On startup it runs:
   ```
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```

4. After first deploy, promote your account to admin:
   ```bash
   railway run php artisan admin:make you@example.com
   ```

### Continuous deployment via GitHub Actions

The CI/CD pipeline (`.github/workflows/ci.yml`) runs on every push to `main`:

1. **Test job** — installs dependencies, builds frontend, runs the full test suite
2. **Deploy job** — only runs if tests pass, deploys to Railway via `railway up`

Required GitHub Actions secrets:

| Secret | Value |
|---|---|
| `RAILWAY_TOKEN` | Railway API token (Account Settings → Tokens) |
| `RAILWAY_SERVICE_NAME` | Your Railway service name (e.g. `budget-tracker`) |

---

## Project Structure

```
app/
├── Console/Commands/       # MakeAdminCommand
├── Http/
│   ├── Controllers/        # BudgetController, DepenseController, RevenuController,
│   │                         CategorieController, DashboardController,
│   │                         SettingsController, CurrencyController, LogoController
│   ├── Middleware/         # EnsureUserIsAdmin, HandleInertiaRequests
│   └── Requests/           # Form request validation classes
├── Models/                 # User, Budget, Depense, Revenu, Categorie, Setting, Currency
├── Notifications/          # VerifyEmailNotification, ResetPasswordNotification
└── Policies/               # BudgetPolicy, DepensePolicy, RevenuPolicy

resources/js/
├── Components/             # AppModal, AppBadge, AppTable, StatCard, BudgetProgress, ...
├── composables/            # useFormatMoney, useFlash, useDarkMode, useLocale
├── i18n/                   # fr.js, en.js, es.js + index.js (vue-i18n setup)
├── Layouts/                # AuthenticatedLayout, GuestLayout
└── Pages/
    ├── Auth/               # Login, Register, ForgotPassword, ...
    ├── Budgets/            # Index, Show
    ├── Categories/         # Index
    ├── Depenses/           # Index
    ├── Revenus/            # Index
    └── Settings/           # Index

database/
├── migrations/
└── seeders/                # DatabaseSeeder, CategorieSeeder, BudgetSeeder, ...
```

---

## Key Design Decisions

- **No raw SQL** — all queries go through Eloquent for MySQL/PostgreSQL compatibility
- **Private logo storage** — uploaded logos use `Storage::disk('local')` and are served via `LogoController`, never accessible via a public path
- **Trusted proxies** — `trustProxies(at: '*')` in `bootstrap/app.php` ensures Railway's HTTPS proxy headers are respected, which is required for signed email verification URLs to work correctly
- **Brevo over SMTP** — Railway blocks outbound port 587; Brevo's HTTP API is used instead via `symfony/brevo-mailer`
- **All amounts in XOF (Franc CFA)** — formatted with `Intl.NumberFormat` via the `useFormatMoney` composable
- **Inertia-only responses** — controllers always return `Inertia::render()`, never `view()`; forms always use `useForm()` from `@inertiajs/vue3`

---

## License

MIT
