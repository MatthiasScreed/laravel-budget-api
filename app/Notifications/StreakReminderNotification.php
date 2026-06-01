<?php

namespace App\Notifications;

use App\Models\Streak;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification : série en danger.
 * Envoyée quand l'utilisateur n'a pas agi depuis >= 20h
 * et que sa série est > 0.
 * Canal : database (in-app) + mail.
 */
class StreakReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private int    $currentStreak,
        private string $questName,
        private string $questEmoji = '🎯'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->currentStreak >= 7
            ? "🔥 Danger ! Ta série de {$this->currentStreak} jours va disparaître"
            : "🔥 N'oublie pas ta série aujourd'hui !";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hey {$notifiable->name} !")
            ->line($this->getUrgencyLine())
            ->line("Ta quête **{$this->questEmoji} {$this->questName}** t'attend.")
            ->line("Il te suffit d'enregistrer une action — même 1 € — pour garder ta série.")
            ->action('Faire avancer ma quête', url('/quete'))
            ->line("Ne laisse pas tes progrès s'envoler. 💪")
            ->salutation('— L\'équipe CoinQuest');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'           => 'streak_reminder',
            'title'          => $this->getDatabaseTitle(),
            'body'           => $this->getDatabaseBody(),
            'icon'           => '🔥',
            'action_url'     => '/quete',
            'current_streak' => $this->currentStreak,
            'quest_name'     => $this->questName,
            'quest_emoji'    => $this->questEmoji,
            'urgency'        => $this->currentStreak >= 7 ? 'high' : 'normal',
        ];
    }

    // ==========================================
    // HELPERS PRIVÉS
    // ==========================================

    private function getUrgencyLine(): string
    {
        if ($this->currentStreak >= 30) {
            return "⚠️ **Attention !** Ta série de **{$this->currentStreak} jours** est en danger — c'est un record impressionnant, ne le perds pas !";
        }

        if ($this->currentStreak >= 7) {
            return "Ta série de **{$this->currentStreak} jours** consécutifs va disparaître si tu n'agis pas aujourd'hui.";
        }

        return "Ta série de **{$this->currentStreak} jours** est en jeu — une petite action suffit !";
    }

    private function getDatabaseTitle(): string
    {
        if ($this->currentStreak >= 7) {
            return "🔥 Série de {$this->currentStreak}j en danger !";
        }

        return '🔥 Ta série est en danger';
    }

    private function getDatabaseBody(): string
    {
        return "Enregistre une action pour garder ta série de {$this->currentStreak} jours !";
    }
}
