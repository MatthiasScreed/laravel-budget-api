<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Notification de réinitialisation de mot de passe.
 * FIX: Implémente ShouldQueue pour envoi asynchrone.
 * FIX: URL pointe vers le frontend (coinquest.fr) et non l'API.
 */
class ResetPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = $this->buildResetUrl($notifiable);
        $appName  = config('app.name', 'CoinQuest');

        return (new MailMessage)
            ->subject("🔐 Réinitialise ton mot de passe - {$appName}")
            ->greeting("Bonjour {$notifiable->name} !")
            ->line('Tu as demandé à réinitialiser ton mot de passe.')
            ->line('Clique sur le bouton ci-dessous pour choisir un nouveau mot de passe :')
            ->action('Réinitialiser mon mot de passe', $resetUrl)
            ->line(new HtmlString('Ce lien est valable <strong>24 heures</strong>.'))
            ->line('Si tu n\'as pas fait cette demande, ignore simplement cet email.')
            ->salutation(new HtmlString("L'équipe {$appName} 🪙"));
    }

    /**
     * ✅ FIX: Utilise APP_FRONTEND_URL (coinquest.fr) et non APP_URL (api.coinquest.fr)
     */
    protected function buildResetUrl(object $notifiable): string
    {
        // APP_FRONTEND_URL=https://coinquest.fr dans .env
        // Fallback sur APP_URL si non défini
        $frontendUrl = rtrim(
            config('app.frontend_url', 'https://coinquest.fr'),
            '/'
        );

        return $frontendUrl . '/reset-password?' . http_build_query([
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], '', '&', PHP_QUERY_RFC3986);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'token'      => $this->token,
            'email'      => $notifiable->getEmailForPasswordReset(),
            'expires_at' => now()->addHours(24)->toISOString(),
        ];
    }
}
