<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class SecurityAlertNotification extends Notification
{
    use Queueable;

    public string $action;
    public array $details;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $action, array $details = [])
    {
        $this->action = $action;
        $this->details = $details;
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
        $appName = config('app.name', 'Budget Gaming API');

        $actionMessages = [
            'password_changed' => [
                'subject' => 'Mot de passe modifié',
                'message' => 'Votre mot de passe a été modifié avec succès.',
                'icon' => '🔒'
            ],
            'password_reset' => [
                'subject' => 'Mot de passe réinitialisé',
                'message' => 'Votre mot de passe a été réinitialisé avec succès.',
                'icon' => '🔑'
            ],
            'login_from_new_device' => [
                'subject' => 'Nouvelle connexion détectée',
                'message' => 'Une connexion à votre compte a été détectée depuis un nouvel appareil.',
                'icon' => '📱'
            ],
            'multiple_failed_logins' => [
                'subject' => 'Tentatives de connexion suspectes',
                'message' => 'Plusieurs tentatives de connexion échouées ont été détectées sur votre compte.',
                'icon' => '⚠️'
            ]
        ];

        $actionData = $actionMessages[$this->action] ?? [
            'subject' => 'Activité de sécurité',
            'message' => 'Une activité de sécurité a été détectée sur votre compte.',
            'icon' => '🔐'
        ];

        $message = (new MailMessage)
            ->subject("{$actionData['icon']} {$actionData['subject']} - {$appName}")
            ->greeting("Bonjour {$notifiable->name},")
            ->line($actionData['message']);

        // Ajouter les détails spécifiques
        if (!empty($this->details)) {
            $message->line('Détails :');

            foreach ($this->details as $key => $value) {
                $label = $this->getDetailLabel($key);
                $message->line("• {$label} : {$value}");
            }
        }

        $message->line('Si cette action n\'a pas été effectuée par vous, nous vous recommandons de :')
            ->line('1. Modifier immédiatement votre mot de passe')
            ->line('2. Vérifier vos sessions actives et révoquer celles qui sont suspectes')
            ->line('3. Contacter notre support si nécessaire');

        if ($this->action !== 'password_changed' && $this->action !== 'password_reset') {
            $resetUrl = config('app.frontend_url', config('app.url')) . '/forgot-password';
            $message->action('Sécuriser mon compte', $resetUrl);
        }

        return $message->salutation(new HtmlString("Cordialement,<br>L'équipe sécurité {$appName}"));
    }

    /**
     * Obtenir le libellé d'un détail
     */
    protected function getDetailLabel(string $key): string
    {
        $labels = [
            'ip_address' => 'Adresse IP',
            'user_agent' => 'Navigateur',
            'device' => 'Appareil',
            'location' => 'Localisation',
            'timestamp' => 'Date et heure',
            'attempts_count' => 'Nombre de tentatives'
        ];

        return $labels[$key] ?? ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'security_alert',
            'action' => $this->action,
            'details' => $this->details,
            'user_id' => $notifiable->id,
            'sent_at' => now(),
        ];
    }
}
