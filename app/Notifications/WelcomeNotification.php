<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class WelcomeNotification extends Notification implements ShouldQueue
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
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name', 'Budget Gaming API');
        $appUrl = config('app.frontend_url', config('app.url'));

        return (new MailMessage)
            ->subject("🎉 Bienvenue sur {$appName} !")
            ->greeting("Bienvenue {$notifiable->name} ! 👋")
            ->line("Félicitations ! Votre compte {$appName} a été créé avec succès.")
            ->line('🏆 **Vous avez gagné 100 XP de bonus d\'inscription !**')
            ->line('🎖️ **Premier succès débloqué : "Bienvenue à bord" !**')
            ->line('')
            ->line('Vous pouvez maintenant :')
            ->line('• 💰 Gérer votre budget personnel avec style')
            ->line('• 🎯 Définir et suivre vos objectifs financiers')
            ->line('• 🏆 Débloquer des succès en gérant vos finances')
            ->line('• 📊 Consulter vos statistiques et progresser en niveau')
            ->line('• 🔥 Maintenir des séries pour gagner des bonus')
            ->action('🚀 Commencer maintenant', $appUrl)
            ->line('')
            ->line('**💡 Conseil de démarrage :**')
            ->line('1. Créez vos premières catégories de revenus et dépenses')
            ->line('2. Enregistrez quelques transactions récentes')
            ->line('3. Définissez votre premier objectif d\'épargne')
            ->line('4. Revenez chaque jour pour maintenir votre série !')
            ->line('')
            ->line(new HtmlString('Si vous avez des questions, consultez notre <a href="'.$appUrl.'/help">aide en ligne</a>.'))
            ->salutation(new HtmlString("Bonne gestion budgétaire ! 🚀<br>L'équipe {$appName}"));
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'welcome',
            'title' => 'Bienvenue ! 🎉',
            'message' => 'Votre compte a été créé avec succès. Vous avez gagné 100 XP de bonus !',
            'icon' => '🎉',
            'action_url' => '/dashboard',
            'xp_earned' => 100,
            'achievement_unlocked' => 'Bienvenue à bord',
        ];
    }
}
