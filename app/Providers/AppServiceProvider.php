<?php

namespace App\Providers;

use App\Auth\EncryptedUserProvider;
use App\Listeners\LogAuthEvent;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Revenue;
use App\Models\User;
use App\Observers\ModelActivityObserver;
use App\Services\EncryptionService;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EncryptionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // ── Encrypted user provider (replaces default Eloquent provider) ───────
        Auth::provider('encrypted', function ($app, array $config) {
            return new EncryptedUserProvider(
                $app['hash'],
                $config['model'] ?? User::class,
                $app->make(EncryptionService::class),
            );
        });

        // ── Activity logging ───────────────────────────────────────────────────
        foreach ([Budget::class, Expense::class, Revenue::class, Category::class] as $model) {
            $model::observe(ModelActivityObserver::class);
        }

        Event::listen(Login::class,         [LogAuthEvent::class, 'handleLogin']);
        Event::listen(Logout::class,        [LogAuthEvent::class, 'handleLogout']);
        Event::listen(Registered::class,    [LogAuthEvent::class, 'handleRegistered']);
        Event::listen(PasswordReset::class, [LogAuthEvent::class, 'handlePasswordReset']);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Mail::extend('brevo', function (array $config) {
            return (new BrevoTransportFactory)->create(
                new Dsn('brevo+api', 'default', $config['key'])
            );
        });
    }
}
