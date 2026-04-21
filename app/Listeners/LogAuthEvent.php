<?php

namespace App\Listeners;

use App\Services\ActivityLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\PasswordReset;

class LogAuthEvent
{
    public function handleLogin(Login $event): void
    {
        ActivityLogger::log('login', $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        ActivityLogger::log('logout', $event->user);
    }

    public function handleRegistered(Registered $event): void
    {
        ActivityLogger::log('registered', $event->user);
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        ActivityLogger::log('password_reset', $event->user);
    }
}
