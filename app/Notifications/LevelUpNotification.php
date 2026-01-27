<?php

namespace App\Notifications;

use App\Models\UserLevel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LevelUpNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected UserLevel $userLevel;

    protected int $previousLevel;

    protected int $newLevel;

    /**
     * Create a new notification instance.
     */
    public function __construct(UserLevel $userLevel, int $previousLevel, int $newLevel)
    {
        $this->userLevel = $userLevel;
        $this->previousLevel = $previousLevel;
        $this->newLevel = $newLevel;
    }

    /**
     * Get the notification's delivery channels.
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
        $levelBonus = $this->newLevel * 50;

        return (new MailMessage)
            ->subject('🎉 Montée de niveau ! Niveau '.$this->newLevel.' atteint !')
            ->greeting("Fantastique {$notifiable->name} ! 🎉")
            ->line("**Vous venez de passer au niveau {$this->newLevel} !**")
            ->line("Niveau précédent : {$this->previousLevel}")
            ->line("Nouveau niveau : {$this->newLevel}")
            ->line("XP total : {$this->userLevel->total_xp}")
            ->line("🏆 Bonus de niveau : +{$levelBonus} XP !")
            ->line($this->getLevelRewards())
            ->action('Voir mon profil', url('/profile'))
            ->line('Continuez comme ça pour débloquer encore plus de récompenses ! 💪');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'level_up',
            'title' => 'Montée de niveau ! 🎉',
            'message' => "Félicitations ! Vous avez atteint le niveau {$this->newLevel}",
            'icon' => '⬆️',
            'action_url' => '/profile',
            'previous_level' => $this->previousLevel,
            'new_level' => $this->newLevel,
            'total_xp' => $this->userLevel->total_xp,
            'level_title' => $this->userLevel->getTitle(),
            'bonus_xp' => $this->newLevel * 50,
        ];
    }

    /**
     * Get level-specific rewards message
     */
    private function getLevelRewards(): string
    {
        $rewards = [
            5 => '🎨 Avatar personnalisé débloqué !',
            10 => '📊 Statistiques premium débloquées !',
            15 => '🎯 Objectifs avancés débloqués !',
            20 => '📁 Fonctions d\'export débloquées !',
            25 => '🆘 Support prioritaire débloqué !',
            30 => '🧪 Accès aux fonctionnalités bêta !',
            50 => '👑 Premium à vie débloqué !',
        ];

        return $rewards[$this->newLevel] ?? '🎁 Continuez pour débloquer plus de récompenses !';
    }
}
