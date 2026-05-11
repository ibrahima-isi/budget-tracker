# Repository Guidelines

## Project Structure & Module Organization

BudgetTrack is a Laravel 12 application with Inertia.js and Vue 3. Backend code lives in `app/`: controllers, models, policies, services, observers, and Artisan commands use standard Laravel subdirectories. Routes are split across `routes/web.php`, `routes/auth.php`, and `routes/console.php`. Frontend pages, layouts, and composables live in `resources/js/`; CSS is in `resources/css/app.css`, and the root Blade shell is `resources/views/app.blade.php`. Database files are under `database/`. Tests are in `tests/Feature` and `tests/Unit`.

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

Use 4-space indentation, LF endings, UTF-8, and final newlines as defined in `.editorconfig`. Format PHP with Laravel Pint (`./vendor/bin/pint`) before submitting broad PHP changes. Keep Laravel class names singular and descriptive (`BudgetPolicy`, `DashboardService`), Vue pages in PascalCase, and composables as `useThing.js`. Use Eloquent and Query Builder; avoid raw SQL except for existing encryption functions. In Inertia views, use `<Link>` and `useForm()` instead of manual navigation or ad hoc Axios calls.

## Testing Guidelines

PHPUnit is configured in `phpunit.xml` with Unit and Feature suites. Tests run against SQLite in-memory using array cache, mail, and session drivers, so no local database is required. Name tests after the behavior and place them in the matching suite, for example `tests/Feature/BudgetTest.php` or `tests/Unit/Services/EncryptionServiceTest.php`. Run a focused file with `php artisan test tests/Feature/BudgetTest.php`; use `php artisan test --coverage` when coverage evidence is needed.

## Commit & Pull Request Guidelines

Recent history favors short imperative subjects such as `Fix yearly period filters`, with occasional conventional prefixes like `feat:` or `fix(scope):`. Keep commits focused and mention migrations, encryption, or deployment changes explicitly. Pull requests should include a concise description, test results, linked issues when applicable, and screenshots for UI changes.

## Security & Configuration Tips

User `name` and `email` are encrypted. Do not log plaintext PII, commit private keys, or bypass `User::findDecrypted()` / `User::findByEmail()` when decrypted user data is required. Keep `APP_PRIVATE_KEY`, `APP_PUBLIC_KEY`, and `APP_EMAIL_HASH_KEY` in environment-managed secrets, and review `ENCRYPTION.md` before changing auth or user storage flows.
