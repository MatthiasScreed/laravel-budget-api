<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class WelcomeNotification extends Notification
{
    use Queueable;


    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
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
        $appUrl = config('app.frontend_url', config('app.url'));

        return (new MailMessage)
            ->subject("Bienvenue sur {$appName} ! 🎉")
            ->greeting("Bienvenue {$notifiable->name} ! 👋")
            ->line("Félicitations ! Votre compte {$appName} a été créé avec succès.")
            ->line('Vous pouvez maintenant :')
            ->line('• 💰 Gérer votre budget personnel')
            ->line('• 🎯 Définir et suivre vos objectifs financiers')
            ->line('• 🏆 Débloquer des succès en gérant vos finances')
            ->line('• 📊 Consulter vos statistiques et progresser en niveau')
            ->action('Commencer maintenant', $appUrl)
            ->line('Conseil : Commencez par créer vos premières catégories et enregistrer quelques transactions pour débloquer votre premier succès !')
            ->line(new HtmlString('Si vous avez des questions, n\'hésitez pas à consulter notre <a href="' . $appUrl . '/help">aide en ligne</a>.'))
            ->salutation(new HtmlString("Bonne gestion budgétaire ! 🚀<br>L'équipe {$appName}"));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'welcome',
            'user_id' => $notifiable->id,
            'sent_at' => now(),
        ];
    }
}
