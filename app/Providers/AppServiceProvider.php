<?php

namespace App\Providers;

use App\Listeners\LogAuthEvent;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Revenue;
use App\Observers\ModelActivityObserver;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

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
