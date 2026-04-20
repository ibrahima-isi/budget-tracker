<?php

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        $settings = Setting::instance();
        $name     = $settings->business_name;
        $from     = $settings->business_email ?: config('mail.from.address');

        return (new MailMessage)
            ->from($from, $name)
            ->subject("Confirmez votre adresse email — {$name}")
            ->greeting('Bienvenue !')
            ->line('Merci de vous être inscrit(e). Veuillez cliquer sur le bouton ci-dessous pour vérifier votre adresse email.')
            ->action('Vérifier mon adresse email', $url)
            ->line('Ce lien expirera dans 60 minutes.')
            ->line('Si vous n\'avez pas créé de compte, ignorez cet email.');
    }
}
