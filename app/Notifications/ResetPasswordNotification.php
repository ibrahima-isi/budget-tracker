<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $settings = Setting::instance();
        $name     = $settings->business_name;
        $from     = $settings->business_email ?: config('mail.from.address');

        return (new MailMessage)
            ->from($from, $name)
            ->subject("Réinitialisation de votre mot de passe — {$name}")
            ->greeting('Bonjour,')
            ->line('Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.')
            ->action('Réinitialiser mon mot de passe', $url)
            ->line('Ce lien expirera dans ' . config('auth.passwords.' . config('auth.defaults.passwords') . '.expire') . ' minutes.')
            ->line('Si vous n\'avez pas demandé de réinitialisation, ignorez cet email. Votre mot de passe restera inchangé.');
    }
}
