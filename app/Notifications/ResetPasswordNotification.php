<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * Le token de réinitialisation
     */
    public string $token;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = $this->buildResetUrl($notifiable);
        $appName = config('app.name', 'Budget Gaming API');
        $validityHours = 24;

        return (new MailMessage)
            ->subject("Réinitialisation de votre mot de passe - {$appName}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line('Vous recevez cet email car nous avons reçu une demande de réinitialisation de mot de passe pour votre compte.')
            ->line('Cliquez sur le bouton ci-dessous pour réinitialiser votre mot de passe :')
            ->action('Réinitialiser le mot de passe', $resetUrl)
            ->line(new HtmlString("Ce lien de réinitialisation expirera dans <strong>{$validityHours} heures</strong>."))
            ->line('Si vous n\'avez pas demandé de réinitialisation de mot de passe, aucune action n\'est requise de votre part.')
            ->line('Pour votre sécurité, ne partagez jamais ce lien avec personne d\'autre.')
            ->salutation(new HtmlString("Cordialement,<br>L'équipe {$appName}"))
            ->with([
                'actionText' => 'Réinitialiser le mot de passe',
                'actionUrl' => $resetUrl,
                'displayableActionUrl' => $resetUrl,
            ]);
    }

    /**
     * Construire l'URL de réinitialisation
     */
    protected function buildResetUrl(object $notifiable): string
    {
        // URL du frontend avec le token
        $frontendUrl = config('app.frontend_url', config('app.url'));

        return "{$frontendUrl}/reset-password?".http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'expires_at' => now()->addHours(24),
        ];
    }
}
