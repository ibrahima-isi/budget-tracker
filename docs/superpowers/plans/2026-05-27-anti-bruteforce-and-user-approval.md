# Anti Brute Force and User Approval Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add layered brute-force and abuse protection, plus an admin approval gate for every newly registered user before they can access app resources.

**Architecture:** Login credential failures stay separate from general request throttling: failed credentials are locked per email/IP after 5 failures for 5 minutes, while all dynamic web requests are limited by IP and network with a 15-minute block after abuse. User approval is enforced after authentication and email verification via a dedicated middleware, with admin-only user management routes and UI under settings.

**Tech Stack:** Laravel 12, Inertia.js, Vue 3, PHPUnit, Laravel cache/rate limiter, existing encrypted `User` storage compatibility.

---

## Design Decisions To Approve

- Login failure lockout: 5 failed credential attempts per normalized email and IP, then 5-minute server-side lockout.
- Login countdown: server sends `lockoutUntil`; Vue displays a client-side countdown, but backend remains authoritative.
- General request throttling: 120 dynamic web requests per minute per IP; 600 dynamic web requests per minute per IPv4 `/24` or IPv6 `/64` network; violation creates a 15-minute block.
- General request throttling scope: applies to Laravel web routes, including authenticated app routes and public auth routes; excludes `/health` and `/up`; static assets are not affected because they are not served through Laravel web routes in normal deployment.
- New user approval: new registered users are created as unapproved and are not auto-logged in.
- Existing users: migration backfills existing users as approved to avoid locking out current accounts.
- Admin safety: an admin cannot revoke their own approval, and the last approved admin cannot be revoked.
- Factory behavior: `UserFactory` defaults to approved users so existing feature tests keep representing normal active users; a new `unapproved()` factory state covers pending users.

---

## File Map

### New Files

- `config/security.php`  
  Central configuration for login lockout and dynamic request throttling values.

- `app/Support/NetworkRateLimitKey.php`  
  Converts client IPs into stable network keys: IPv4 `/24`, IPv6 `/64`, or `unknown`.

- `app/Http/Middleware/ThrottleDynamicRequests.php`  
  Enforces the general per-IP and per-network request limits and 15-minute block state.

- `app/Http/Middleware/EnsureUserIsApproved.php`  
  Blocks unapproved authenticated users from app resources.

- `app/Http/Controllers/UserManagementController.php`  
  Admin-only user approval listing/actions.

- `database/migrations/2026_05_27_000001_add_approval_fields_to_users_table.php`  
  Adds `is_approved`, `approved_at`, and `approved_by`.

- `database/migrations/2026_05_27_000002_update_user_encryption_functions_for_approval.php`  
  Updates PostgreSQL encrypted user helper functions to include approval fields.

- `resources/js/Pages/Settings/Users.vue`  
  Admin user-management page.

- `tests/Feature/Auth/LoginThrottlingTest.php`  
  Focused tests for credential lockout and countdown metadata.

- `tests/Feature/Security/DynamicRequestRateLimitTest.php`  
  Focused tests for general IP/network request throttling.

- `tests/Feature/UserApprovalTest.php`  
  Focused tests for pending registration, access blocking, and admin approval actions.

- `lang/en/auth.php`, `lang/fr/auth.php`, `lang/es/auth.php`  
  Auth validation messages used by login failure, throttle, and pending approval flows.

### Modified Files

- `app/Http/Requests/Auth/LoginRequest.php`  
  Use configured 5-minute credential lockout and flash `lockoutUntil` metadata.

- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`  
  Reject successful credentials for unapproved users and pass login lockout props.

- `app/Http/Controllers/Auth/RegisteredUserController.php`  
  Create pending users and redirect to login with a pending approval status.

- `app/Models/User.php`  
  Add approval casts/fillable support, encrypted hydration support, and approval helpers.

- `database/factories/UserFactory.php`  
  Default factory users to approved and add `unapproved()`.

- `database/seeders/DatabaseSeeder.php`  
  Seed the admin as approved.

- `app/Console/Commands/MakeAdminCommand.php`  
  Promote users to admin and approve them at the same time.

- `bootstrap/app.php`  
  Register dynamic request throttle middleware and `approved` alias.

- `routes/web.php`  
  Add `approved` middleware to app resources and add admin user-management routes.

- `app/Http/Middleware/HandleInertiaRequests.php`  
  Share `is_approved` with the frontend auth user prop.

- `resources/js/Pages/Auth/Login.vue`  
  Render lockout countdown and disable submit during visible lockout.

- `resources/js/Layouts/AuthenticatedLayout.vue`  
  Add admin navigation link for users.

- `resources/js/i18n/en.js`, `resources/js/i18n/fr.js`, `resources/js/i18n/es.js`  
  Add navigation/user-management/counter labels.

- Existing auth/registration/settings/security tests  
  Update assertions impacted by pending registration and approved-only access.

---

## Task 1: Add Security Configuration

**Files:**
- Create: `config/security.php`

- [ ] **Step 1: Create config with explicit defaults**

```php
<?php

return [
    'login' => [
        'max_attempts' => env('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_seconds' => env('LOGIN_LOCKOUT_SECONDS', 300),
    ],

    'dynamic_requests' => [
        'enabled' => env('DYNAMIC_RATE_LIMIT_ENABLED', true),
        'ip_attempts' => env('DYNAMIC_RATE_LIMIT_IP_ATTEMPTS', 120),
        'network_attempts' => env('DYNAMIC_RATE_LIMIT_NETWORK_ATTEMPTS', 600),
        'window_seconds' => env('DYNAMIC_RATE_LIMIT_WINDOW_SECONDS', 60),
        'block_seconds' => env('DYNAMIC_RATE_LIMIT_BLOCK_SECONDS', 900),
        'excluded_paths' => [
            'health',
            'up',
        ],
    ],
];
```

- [ ] **Step 2: Verify config loads**

Run:

```bash
php artisan tinker --execute='var_export(config("security.login.max_attempts"));'
```

Expected:

```text
5
```

---

## Task 2: Test Login Credential Lockout

**Files:**
- Create: `tests/Feature/Auth/LoginThrottlingTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginThrottlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_locked_for_five_minutes_after_five_failed_attempts(): void
    {
        config([
            'security.login.max_attempts' => 5,
            'security.login.lockout_seconds' => 300,
        ]);

        $user = User::factory()->create([
            'email' => 'lockout@example.com',
            'password' => 'password',
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->from('/login')->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->travel(301)->seconds();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_page_receives_lockout_countdown_timestamp(): void
    {
        config([
            'security.login.max_attempts' => 1,
            'security.login.lockout_seconds' => 300,
        ]);

        $user = User::factory()->create([
            'email' => 'countdown@example.com',
            'password' => 'password',
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->where('lockoutUntil', fn ($value) => is_int($value) && $value > now()->timestamp)
            );
    }
}
```

- [ ] **Step 2: Run tests and confirm RED**

Run:

```bash
php artisan test tests/Feature/Auth/LoginThrottlingTest.php
```

Expected: failures because `lockoutUntil` is not passed and the lockout duration is not yet configured to 300 seconds.

---

## Task 3: Implement Login Credential Lockout

**Files:**
- Modify: `app/Http/Requests/Auth/LoginRequest.php`
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Create: `lang/en/auth.php`
- Create: `lang/fr/auth.php`
- Create: `lang/es/auth.php`

- [ ] **Step 1: Update `LoginRequest`**

Implementation requirements:

- Read `config('security.login.max_attempts')`.
- Read `config('security.login.lockout_seconds')`.
- On failed credentials, call `RateLimiter::hit($this->throttleKey(), $lockoutSeconds)`.
- After the fifth failure, immediately return the throttle validation message.
- Flash `login_lockout_until` into the session as `now()->addSeconds($seconds)->timestamp`.
- Clear the credential throttle key after a valid approved login.
- Keep the throttle key based on normalized email and IP.

Key methods to add or update:

```php
private function maxAttempts(): int
{
    return (int) config('security.login.max_attempts', 5);
}

private function lockoutSeconds(): int
{
    return (int) config('security.login.lockout_seconds', 300);
}

private function throwLockoutValidationException(): never
{
    event(new Lockout($this));

    $seconds = RateLimiter::availableIn($this->throttleKey());
    $this->session()->flash('login_lockout_until', now()->addSeconds($seconds)->timestamp);

    throw ValidationException::withMessages([
        'email' => trans('auth.throttle', [
            'seconds' => $seconds,
            'minutes' => ceil($seconds / 60),
        ]),
    ]);
}
```

- [ ] **Step 2: Update login page controller props**

In `AuthenticatedSessionController::create()`, add:

```php
'lockoutUntil' => session('login_lockout_until'),
```

- [ ] **Step 3: Add auth language files**

`lang/fr/auth.php`:

```php
<?php

return [
    'failed' => 'Ces identifiants ne correspondent pas a nos enregistrements.',
    'password' => 'Le mot de passe est incorrect.',
    'throttle' => 'Trop de tentatives. Reessayez dans :minutes minute(s).',
    'pending_approval' => 'Votre compte est en attente d’approbation par un administrateur.',
    'registered_pending_approval' => 'Votre compte a ete cree. Un administrateur doit l’approuver avant votre premiere connexion.',
];
```

`lang/en/auth.php`:

```php
<?php

return [
    'failed' => 'These credentials do not match our records.',
    'password' => 'The provided password is incorrect.',
    'throttle' => 'Too many attempts. Please try again in :minutes minute(s).',
    'pending_approval' => 'Your account is pending administrator approval.',
    'registered_pending_approval' => 'Your account was created. An administrator must approve it before your first login.',
];
```

`lang/es/auth.php`:

```php
<?php

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contrasena proporcionada es incorrecta.',
    'throttle' => 'Demasiados intentos. Intentalo de nuevo en :minutes minuto(s).',
    'pending_approval' => 'Tu cuenta esta pendiente de aprobacion por un administrador.',
    'registered_pending_approval' => 'Tu cuenta fue creada. Un administrador debe aprobarla antes de tu primer inicio de sesion.',
];
```

- [ ] **Step 4: Run lockout tests and confirm GREEN**

Run:

```bash
php artisan test tests/Feature/Auth/LoginThrottlingTest.php
```

Expected: all tests pass.

---

## Task 4: Test General Dynamic Request Throttling

**Files:**
- Create: `tests/Feature/Security/DynamicRequestRateLimitTest.php`

- [ ] **Step 1: Write failing tests**

```php
<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DynamicRequestRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        config([
            'security.dynamic_requests.enabled' => true,
            'security.dynamic_requests.ip_attempts' => 3,
            'security.dynamic_requests.network_attempts' => 100,
            'security.dynamic_requests.window_seconds' => 60,
            'security.dynamic_requests.block_seconds' => 900,
        ]);
    }

    public function test_ip_is_blocked_for_fifteen_minutes_after_request_limit_is_exceeded(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->fromIp('203.0.113.10')->get('/login')->assertOk();
        }

        $this->fromIp('203.0.113.10')
            ->get('/login')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '900');

        $this->travel(61)->seconds();

        $this->fromIp('203.0.113.10')
            ->get('/login')
            ->assertStatus(429);

        $this->travel(900)->seconds();

        $this->fromIp('203.0.113.10')->get('/login')->assertOk();
    }

    public function test_network_is_blocked_when_many_ips_in_same_network_exceed_limit(): void
    {
        config([
            'security.dynamic_requests.ip_attempts' => 100,
            'security.dynamic_requests.network_attempts' => 3,
        ]);

        $this->fromIp('198.51.100.10')->get('/login')->assertOk();
        $this->fromIp('198.51.100.11')->get('/login')->assertOk();
        $this->fromIp('198.51.100.12')->get('/login')->assertOk();

        $this->fromIp('198.51.100.13')
            ->get('/login')
            ->assertStatus(429)
            ->assertHeader('Retry-After', '900');
    }

    public function test_health_routes_are_excluded_from_dynamic_rate_limit(): void
    {
        config([
            'security.dynamic_requests.ip_attempts' => 1,
            'security.dynamic_requests.network_attempts' => 1,
        ]);

        $this->fromIp('192.0.2.50')->get('/health')->assertOk();
        $this->fromIp('192.0.2.50')->get('/health')->assertOk();
        $this->fromIp('192.0.2.50')->get('/up')->assertOk();
        $this->fromIp('192.0.2.50')->get('/up')->assertOk();
    }

    private function fromIp(string $ip): self
    {
        return $this->withServerVariables([
            'REMOTE_ADDR' => $ip,
        ]);
    }
}
```

- [ ] **Step 2: Run tests and confirm RED**

Run:

```bash
php artisan test tests/Feature/Security/DynamicRequestRateLimitTest.php
```

Expected: failures because no global dynamic request throttling middleware exists.

---

## Task 5: Implement General Dynamic Request Throttling

**Files:**
- Create: `app/Support/NetworkRateLimitKey.php`
- Create: `app/Http/Middleware/ThrottleDynamicRequests.php`
- Modify: `bootstrap/app.php`

- [ ] **Step 1: Add network key helper**

`app/Support/NetworkRateLimitKey.php`:

```php
<?php

namespace App\Support;

class NetworkRateLimitKey
{
    public static function fromIp(?string $ip): string
    {
        if (! $ip) {
            return 'unknown';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return "{$parts[0]}.{$parts[1]}.{$parts[2]}.0/24";
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);

            if ($packed === false) {
                return 'unknown';
            }

            return bin2hex(substr($packed, 0, 8)).'/64';
        }

        return 'unknown';
    }
}
```

- [ ] **Step 2: Add middleware**

`app/Http/Middleware/ThrottleDynamicRequests.php` requirements:

- Skip when `security.dynamic_requests.enabled` is false.
- Skip paths listed in `security.dynamic_requests.excluded_paths`.
- Before counting the request, reject active IP or network blocks.
- Check per-minute counters before hitting them so exactly `N` requests are allowed and request `N + 1` is blocked.
- When a counter is exceeded, hit a separate block key with `block_seconds`.
- Return `429` with `Retry-After`, `X-RateLimit-Limit`, and `X-RateLimit-Remaining` headers.
- For Inertia requests, redirect back with flash error is not appropriate for abuse control; return a hard `429`.

Core behavior:

```php
private function rejectIfBlocked(string $blockKey, int $limit): ?Response
{
    if (! RateLimiter::tooManyAttempts($blockKey, 1)) {
        return null;
    }

    return $this->tooManyRequests(RateLimiter::availableIn($blockKey), $limit);
}
```

- [ ] **Step 3: Register middleware globally for web routes**

In `bootstrap/app.php`, import:

```php
use App\Http\Middleware\ThrottleDynamicRequests;
```

Then add it to the web middleware stack before performance logging:

```php
$middleware->web(prepend: [
    ThrottleDynamicRequests::class,
]);
```

Keep existing web appends unchanged.

- [ ] **Step 4: Run dynamic throttling tests and confirm GREEN**

Run:

```bash
php artisan test tests/Feature/Security/DynamicRequestRateLimitTest.php
```

Expected: all tests pass.

---

## Task 6: Test User Approval Workflow

**Files:**
- Create: `tests/Feature/UserApprovalTest.php`
- Modify: `tests/Feature/Auth/RegistrationTest.php`

- [ ] **Step 1: Add focused approval tests**

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_users_are_pending_approval_and_not_authenticated(): void
    {
        $this->post('/register', [
            'name' => 'Pending User',
            'email' => 'pending@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect('/login')
          ->assertSessionHas('status');

        $this->assertGuest();

        $user = User::where('email', 'pending@example.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_approved);
        $this->assertNull($user->approved_at);
    }

    public function test_unapproved_user_with_valid_credentials_cannot_log_in(): void
    {
        $user = User::factory()->unapproved()->create([
            'email' => 'not-approved@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $this->from('/login')->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unapproved_authenticated_user_cannot_access_app_resources(): void
    {
        $user = User::factory()->unapproved()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHas('status');

        $this->assertGuest();
    }

    public function test_admin_can_approve_user(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $user = User::factory()->unapproved()->create();

        $this->actingAs($admin)
            ->patch(route('settings.users.approve', $user))
            ->assertRedirect(route('settings.users.index'));

        $user->refresh();

        $this->assertTrue($user->is_approved);
        $this->assertNotNull($user->approved_at);
        $this->assertSame($admin->id, $user->approved_by);
    }

    public function test_admin_can_revoke_user_approval_except_self(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $user = User::factory()->create([
            'is_approved' => true,
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->patch(route('settings.users.revoke-approval', $user))
            ->assertRedirect(route('settings.users.index'));

        $this->assertFalse($user->refresh()->is_approved);

        $this->actingAs($admin)
            ->patch(route('settings.users.revoke-approval', $admin))
            ->assertRedirect(route('settings.users.index'))
            ->assertSessionHas('error');

        $this->assertTrue($admin->refresh()->is_approved);
    }

    public function test_non_admin_cannot_manage_user_approvals(): void
    {
        $regular = User::factory()->create([
            'is_admin' => false,
            'is_approved' => true,
        ]);
        $pending = User::factory()->unapproved()->create();

        $this->actingAs($regular)->get(route('settings.users.index'))->assertForbidden();
        $this->actingAs($regular)->patch(route('settings.users.approve', $pending))->assertForbidden();
    }
}
```

- [ ] **Step 2: Update registration test expectation**

Change `tests/Feature/Auth/RegistrationTest.php` so `test_new_users_can_register` expects:

```php
$this->assertGuest();
$response->assertRedirect('/login');
```

Also assert the created user is not approved.

- [ ] **Step 3: Run tests and confirm RED**

Run:

```bash
php artisan test tests/Feature/UserApprovalTest.php tests/Feature/Auth/RegistrationTest.php
```

Expected: failures because approval columns, middleware, routes, and controller do not exist yet.

---

## Task 7: Add Approval Columns and Encrypted Function Support

**Files:**
- Create: `database/migrations/2026_05_27_000001_add_approval_fields_to_users_table.php`
- Create: `database/migrations/2026_05_27_000002_update_user_encryption_functions_for_approval.php`

- [ ] **Step 1: Add approval field migration**

Migration behavior:

- Add `is_approved` default false.
- Add `approved_at` nullable.
- Add `approved_by` nullable FK to `users.id`, null on delete.
- Backfill existing users as approved with `approved_at = now()`.

Important migration detail:

```php
Schema::table('users', function (Blueprint $table) {
    $table->boolean('is_approved')->default(false)->after('is_admin');
    $table->timestamp('approved_at')->nullable()->after('is_approved');
    $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
});

DB::table('users')->update([
    'is_approved' => true,
    'approved_at' => now(),
]);
```

- [ ] **Step 2: Update PostgreSQL encrypted functions**

In the second migration:

- Skip on non-PostgreSQL.
- Drop old `create_user`, `read_user`, and `search_by_email_hash` signatures.
- Recreate `create_user` with `p_is_approved BOOLEAN DEFAULT FALSE`.
- Recreate `read_user` and `search_by_email_hash` with `is_approved`, `approved_at`, and `approved_by` in returned table.
- Keep `update_user` identity-only behavior unchanged.

The PHP `User` model will call the new 7-argument `create_user(...)` signature.

- [ ] **Step 3: Run migrations in test database**

Run:

```bash
php artisan migrate:fresh --env=testing
```

Expected: migration succeeds on SQLite; PostgreSQL-specific function migration skips.

---

## Task 8: Update User Model, Factory, Seeder, and Admin Command

**Files:**
- Modify: `app/Models/User.php`
- Modify: `database/factories/UserFactory.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Modify: `app/Console/Commands/MakeAdminCommand.php`

- [ ] **Step 1: Update `User` casts and fillable**

Add:

```php
'is_approved',
'approved_at',
'approved_by',
```

Add casts:

```php
'is_approved' => 'boolean',
'approved_at' => 'datetime',
```

- [ ] **Step 2: Update encrypted create/hydrate paths**

Requirements:

- `createEncrypted()` reads `$isApproved = ($attributes['is_approved'] ?? false) ? 'true' : 'false';`.
- `create_user` SQL call passes seven arguments.
- `plainColumnAttributes()` allow-list includes `approved_at` and `approved_by` during create.
- `updateEncrypted()` allow-list includes `is_approved`, `approved_at`, and `approved_by`.
- `hydrateRow()` sets `is_approved`, `approved_at`, and `approved_by`.

- [ ] **Step 3: Add model helpers**

Add:

```php
public function approve(?self $approver = null): bool
{
    return $this->forceFill([
        'is_approved' => true,
        'approved_at' => now(),
        'approved_by' => $approver?->id,
    ])->save();
}

public function revokeApproval(): bool
{
    return $this->forceFill([
        'is_approved' => false,
        'approved_at' => null,
        'approved_by' => null,
    ])->save();
}
```

- [ ] **Step 4: Update factory defaults**

Default factory users should be approved:

```php
'is_approved' => true,
'approved_at' => now(),
'approved_by' => null,
```

Add:

```php
public function unapproved(): static
{
    return $this->state(fn (array $attributes) => [
        'is_approved' => false,
        'approved_at' => null,
        'approved_by' => null,
    ]);
}
```

- [ ] **Step 5: Update seeder admin**

Add:

```php
'is_approved' => true,
'approved_at' => now(),
```

- [ ] **Step 6: Update admin command**

When making an admin, also approve:

```php
$user->forceFill([
    'is_admin' => true,
    'is_approved' => true,
    'approved_at' => now(),
    'approved_by' => null,
])->save();
```

- [ ] **Step 7: Run existing auth test subset**

Run:

```bash
php artisan test tests/Feature/Auth
```

Expected: failures only where controller/middleware behavior is still pending.

---

## Task 9: Enforce Approval in Auth and Routes

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsApproved.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`

- [ ] **Step 1: Add approval middleware**

`EnsureUserIsApproved` behavior:

- If no user, let `auth` middleware handle it.
- If approved, continue.
- If unapproved, logout, invalidate session, regenerate token, redirect to login with pending approval status.

- [ ] **Step 2: Register middleware alias**

In `bootstrap/app.php`:

```php
use App\Http\Middleware\EnsureUserIsApproved;
```

Add alias:

```php
'approved' => EnsureUserIsApproved::class,
```

- [ ] **Step 3: Add `approved` to protected routes**

Change main app group:

```php
Route::middleware(['auth', 'verified', 'approved'])->group(function () {
```

Change profile group:

```php
Route::middleware(['auth', 'verified', 'approved'])->group(function () {
```

Keep email verification routes as `auth` only so pending users can complete verification if needed.

- [ ] **Step 4: Reject unapproved valid login**

After `$request->authenticate()` in `AuthenticatedSessionController::store()`:

```php
if (! $request->user()->is_approved) {
    Auth::guard('web')->logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    throw ValidationException::withMessages([
        'email' => __('auth.pending_approval'),
    ]);
}
```

Then regenerate session only for approved users.

- [ ] **Step 5: Change registration behavior**

In `RegisteredUserController::store()`:

- Create user with `is_approved => false`.
- Keep `event(new Registered($user))`.
- Remove `Auth::login($user)`.
- Redirect to login with `__('auth.registered_pending_approval')` status.

- [ ] **Step 6: Share approval state**

In `HandleInertiaRequests`, add:

```php
'is_approved' => (bool) $request->user()->is_approved,
```

- [ ] **Step 7: Run approval tests and confirm partial GREEN**

Run:

```bash
php artisan test tests/Feature/UserApprovalTest.php tests/Feature/Auth/RegistrationTest.php
```

Expected: registration/access tests pass except user-management route/action tests, which will still fail until Task 10.

---

## Task 10: Add Admin User Management Backend

**Files:**
- Create: `app/Http/Controllers/UserManagementController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Add controller**

Controller methods:

- `index()` returns `Settings/Users` with paginated users.
- `approve(Request $request, User $user)` approves target user with current admin as approver.
- `revokeApproval(Request $request, User $user)` revokes approval unless target is current admin or last approved admin.

Encrypted storage list requirement:

```php
$users = User::query()
    ->latest('created_at')
    ->paginate(20)
    ->through(function (User $user) {
        $decrypted = User::usesEncryptedStorage() ? User::findDecrypted($user->id) : $user;
        $displayUser = $decrypted ?? $user;

        return [
            'id' => $displayUser->id,
            'name' => $displayUser->name,
            'email' => $displayUser->email,
            'is_admin' => (bool) $displayUser->is_admin,
            'is_approved' => (bool) $displayUser->is_approved,
            'approved_at' => optional($displayUser->approved_at)->toISOString(),
            'created_at' => optional($displayUser->created_at)->toISOString(),
        ];
    });
```

- [ ] **Step 2: Add admin routes**

Inside the existing admin route group:

```php
Route::get('settings/users', [UserManagementController::class, 'index'])->name('settings.users.index');
Route::patch('settings/users/{user}/approve', [UserManagementController::class, 'approve'])->name('settings.users.approve');
Route::patch('settings/users/{user}/revoke-approval', [UserManagementController::class, 'revokeApproval'])->name('settings.users.revoke-approval');
```

- [ ] **Step 3: Run approval tests and confirm backend GREEN**

Run:

```bash
php artisan test tests/Feature/UserApprovalTest.php
```

Expected: all approval backend tests pass.

---

## Task 11: Add Login Countdown UI

**Files:**
- Modify: `resources/js/Pages/Auth/Login.vue`
- Modify: `resources/js/i18n/en.js`
- Modify: `resources/js/i18n/fr.js`
- Modify: `resources/js/i18n/es.js`

- [ ] **Step 1: Add lockout props and timer**

In `Login.vue`:

- Import `computed`, `onBeforeUnmount`, `onMounted`, and `ref`.
- Assign `defineProps()` to `props`.
- Track `now` with `setInterval`.
- Compute remaining seconds from `props.lockoutUntil`.
- Disable submit while remaining seconds is positive.

Core state:

```js
const props = defineProps({
    canResetPassword: { type: Boolean },
    status:           { type: String },
    lockoutUntil:     { type: Number, default: null },
});

const now = ref(Date.now());
let timer = null;

const lockoutRemainingSeconds = computed(() => {
    if (!props.lockoutUntil) return 0;
    return Math.max(0, Math.ceil((props.lockoutUntil * 1000 - now.value) / 1000));
});

const lockoutRemainingLabel = computed(() => {
    const seconds = lockoutRemainingSeconds.value;
    const minutes = Math.floor(seconds / 60);
    const rest = seconds % 60;

    return `${minutes}:${String(rest).padStart(2, '0')}`;
});
```

- [ ] **Step 2: Render countdown**

Render a warning below status/errors:

```vue
<div
    v-if="lockoutRemainingSeconds > 0"
    class="mb-5 rounded-xl bg-amber-500/10 border border-amber-500/20 px-4 py-3 text-sm text-amber-300"
>
    {{ $t('auth.lockoutCountdown', { time: lockoutRemainingLabel }) }}
</div>
```

Button disabled state:

```vue
:disabled="form.processing || lockoutRemainingSeconds > 0"
```

- [ ] **Step 3: Add i18n keys**

Add under `auth`:

```js
lockoutCountdown: 'Trop de tentatives. Réessayez dans {time}.',
```

Use English and Spanish equivalents in `en.js` and `es.js`.

- [ ] **Step 4: Build frontend**

Run:

```bash
npm run build
```

Expected: production frontend build passes.

---

## Task 12: Add User Management UI

**Files:**
- Create: `resources/js/Pages/Settings/Users.vue`
- Modify: `resources/js/Layouts/AuthenticatedLayout.vue`
- Modify: `resources/js/i18n/en.js`
- Modify: `resources/js/i18n/fr.js`
- Modify: `resources/js/i18n/es.js`

- [ ] **Step 1: Create `Settings/Users.vue`**

Page requirements:

- Use `AuthenticatedLayout`.
- Use `Head`, `useForm`, and existing button/badge components.
- Show table columns: user, role, approval status, created date, actions.
- Pending users have a clear warning badge.
- Approved users have a success badge.
- Admin users have an admin badge.
- Actions use Inertia `useForm().patch(...)`.
- Disable actions while processing.
- Hide revoke action for current user.

Props:

```js
const props = defineProps({
    users: Object,
});
```

Forms:

```js
const actionForm = useForm({});

function approveUser(user) {
    actionForm.patch(route('settings.users.approve', user.id), {
        preserveScroll: true,
    });
}

function revokeApproval(user) {
    if (!confirm(t('users.confirmRevoke'))) return;

    actionForm.patch(route('settings.users.revoke-approval', user.id), {
        preserveScroll: true,
    });
}
```

- [ ] **Step 2: Add admin nav link**

In `AuthenticatedLayout.vue`, add desktop and mobile links visible only for admins:

```vue
<NavLink
    v-if="$page.props.auth.user?.is_admin"
    :href="route('settings.users.index')"
    :active="route().current('settings.users.*')"
>
    {{ $t('nav.users') }}
</NavLink>
```

Add the equivalent `ResponsiveNavLink`.

- [ ] **Step 3: Add i18n keys**

Add `nav.users`.

Add a `users` group:

```js
users: {
    title: 'Utilisateurs',
    user: 'Utilisateur',
    role: 'Role',
    status: 'Statut',
    createdAt: 'Cree le',
    approved: 'Approuve',
    pending: 'En attente',
    admin: 'Admin',
    member: 'Utilisateur',
    approve: 'Approuver',
    revoke: 'Retirer',
    noData: 'Aucun utilisateur.',
    confirmRevoke: 'Retirer l’approbation de cet utilisateur ?',
}
```

Use English and Spanish equivalents in `en.js` and `es.js`.

- [ ] **Step 4: Build frontend**

Run:

```bash
npm run build
```

Expected: production frontend build passes.

---

## Task 13: Update Existing Tests Impacted by Approval

**Files:**
- Modify existing tests only where approval changes expected behavior.

- [ ] **Step 1: Run auth/security/resource suite**

Run:

```bash
php artisan test tests/Feature/Auth tests/Feature/Security tests/Feature/DashboardTest.php tests/Feature/BudgetTest.php tests/Feature/CategorieTest.php tests/Feature/DepenseTest.php tests/Feature/RevenuTest.php tests/Feature/ProfileTest.php tests/Feature/SettingsTest.php tests/Feature/CurrencyTest.php
```

- [ ] **Step 2: Apply narrow test updates**

Expected legitimate updates:

- Registration now redirects to login and leaves the user unauthenticated.
- If any test creates a manual user without factory defaults, add `is_approved => true`.
- Route protection tests may need a new case for unapproved users.

Do not loosen assertions unrelated to approval or throttling.

- [ ] **Step 3: Rerun same suite**

Run the same command again.

Expected: all selected tests pass.

---

## Task 14: Full Verification

**Files:**
- No planned edits unless verification exposes defects.

- [ ] **Step 1: Run PHP tests**

Run:

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 2: Run frontend build**

Run:

```bash
npm run build
```

Expected: build passes with no compile errors.

- [ ] **Step 3: Optional PHP formatting**

If PHP files changed broadly, run:

```bash
./vendor/bin/pint
```

Expected: formatting completes. Review diff to ensure no unrelated churn.

- [ ] **Step 4: Review diff**

Run:

```bash
git diff --stat
git diff --check
```

Expected:

- Diff only contains planned files.
- No whitespace errors.
- No private keys, plaintext sensitive data logs, or raw SQL beyond existing encryption-function migrations.

---

## Rollback Notes

- Approval migration down path drops `approved_by`, `approved_at`, and `is_approved`.
- Dynamic rate limits can be disabled immediately with `DYNAMIC_RATE_LIMIT_ENABLED=false`.
- Login limits can be adjusted without code deployment through:
  - `LOGIN_MAX_ATTEMPTS`
  - `LOGIN_LOCKOUT_SECONDS`
- Request limits can be adjusted through:
  - `DYNAMIC_RATE_LIMIT_IP_ATTEMPTS`
  - `DYNAMIC_RATE_LIMIT_NETWORK_ATTEMPTS`
  - `DYNAMIC_RATE_LIMIT_WINDOW_SECONDS`
  - `DYNAMIC_RATE_LIMIT_BLOCK_SECONDS`

---

## Self-Review

- Spec coverage: login attempt lockout, countdown, global IP/network request limit, 15-minute block, admin approval of new users, approved-only app access, admin management UI, and tests are all covered.
- Placeholder scan: no `TBD`, no unspecified implementation-only steps, and each task names exact files and commands.
- Type consistency: approval fields are consistently named `is_approved`, `approved_at`, and `approved_by`; route names use `settings.users.*`; config keys use `security.login.*` and `security.dynamic_requests.*`.
- Scope check: no new dependencies; no broad architecture refactor; changes stay inside auth, middleware, users, admin settings, tests, and UI needed for the request.
