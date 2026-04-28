# BudgetTrack Project Context

A personal budget tracking application built with **Laravel 12**, **Inertia.js**, and **Vue 3**.

## 🏗️ Architecture & Core Tech

| Layer | Technology |
|---|---|
| **Backend** | Laravel 12, PHP 8.4 |
| **Frontend** | Vue 3, Inertia.js (v2), Tailwind CSS (v4) |
| **Database** | PostgreSQL 17 (Neon) with `pgcrypto` extension |
| **Auth** | Laravel Breeze (customized for encrypted users) |
| **Encryption** | Asymmetric RSA-4096 (OpenPGP format) via PostgreSQL |
| **Email** | Brevo HTTP API (`symfony/brevo-mailer`) |
| **CI/CD** | GitHub Actions & Railway |

## 🔒 Security: Asymmetric Encryption

The project implements **database-level asymmetric encryption** for user PII (`name`, `email`).

### Implementation Details
- **Encryption Logic**: Handles by `App\Services\EncryptionService`.
- **Stored Functions**: PHP calls PostgreSQL functions (`create_user`, `read_user`, `search_by_email_hash`, `update_user`) to perform cryptographic operations. This ensures plain-text data is only visible to the database engine during the operation and never stored on disk in plain text.
- **Model Usage**:
  - **NEVER** use `User::find($id)` if you need to read name or email. It returns a binary stream.
  - **ALWAYS** use `User::findDecrypted($id)` or `User::findByEmail($email)`.
  - The `name` and `email` accessors return `[encrypted]` if accessed on a binary resource to prevent accidental leakage in logs/dumps.
- **Key Storage**:
  - **Public Key**: `storage/keys/public.pgp` (dev) or `APP_PUBLIC_KEY` (env).
  - **Private Key**: `APP_PRIVATE_KEY` (env only). **NEVER** store the private key on disk in production.

## 📁 Key Directories & Logic

- `app/Models/`:
  - `User`: Custom methods for encrypted CRUD.
  - `Categorie`: Supports global (admin) and personal (user) scopes.
  - `Budget`: Monthly/Annual types with auto-calculated `montant_depense`.
- `app/Services/`:
  - `EncryptionService`: Manages PGP key loading.
  - `DashboardService`: Aggregates stats for the dashboard.
- `resources/js/`:
  - `composables/`: `useFormatMoney` (XOF formatting), `useLocale`.
  - `i18n/`: Translations for EN, FR, ES.
- `database/migrations/`: Includes `pgcrypto` setup and stored functions.

## 🛠️ Development Workflow

### Commands
- **Run Dev**: `composer dev` (runs concurrently: serve, queue, vite, pail).
- **Run Tests**: `php artisan test` (uses SQLite in-memory).
- **Promote Admin**: `php artisan admin:make user@example.com`.
- **Encryption Rotation**: `php artisan users:rotate-keys` (see `ENCRYPTION.md` for details).

### Conventions
- **Surgical Changes**: This project values clean, minimal updates.
- **No Raw SQL**: Except for the encryption functions in migrations, always use Eloquent to maintain compatibility.
- **Inertia Protocol**: Always use `Inertia::render()`, `useForm()`, and `<Link>`. No manual Axios/Fetch for navigation.
- **Currency**: Default is XOF (Franc CFA). Multi-currency is enabled via a session-stored preference (`current_currency`).

## 📋 Guidelines for Gemini
1. **PII Protection**: When writing tests or examples, never use real emails/names.
2. **Encryption Awareness**: If you modify the `User` model or any logic touching user registration/auth, you **must** respect the `createEncrypted` and `updateEncrypted` patterns.
3. **Category Scoping**: When querying categories, use `Categorie::visibleFor(auth()->user())` to ensure users see their personal categories plus global ones.
4. **Admin Routes**: Any new administrative feature should be nested under the `admin` middleware in `web.php`.
