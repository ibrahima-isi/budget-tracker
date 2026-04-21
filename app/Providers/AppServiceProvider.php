<?php

namespace App\Providers;

use App\Listeners\LogAuthEvent;
use App\Models\Budget;
use App\Models\Categorie;
use App\Models\Depense;
use App\Models\Revenu;
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
        foreach ([Budget::class, Depense::class, Revenu::class, Categorie::class] as $model) {
            $model::observe(ModelActivityObserver::class);
        }

        $listener = new LogAuthEvent();
        Event::listen(Login::class,         fn ($e) => $listener->handleLogin($e));
        Event::listen(Logout::class,        fn ($e) => $listener->handleLogout($e));
        Event::listen(Registered::class,    fn ($e) => $listener->handleRegistered($e));
        Event::listen(PasswordReset::class, fn ($e) => $listener->handlePasswordReset($e));

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
