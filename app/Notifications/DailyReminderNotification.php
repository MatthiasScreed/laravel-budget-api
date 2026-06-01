<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification : rappel quotidien.
 * Envoyée chaque matin (ex: 9h) aux utilisateurs
 * qui n'ont pas encore agi aujourd'hui.
 * Canal : database (in-app) + mail.
 */
class DailyReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $questName,
        private string $questEmoji,
        private float  $progressPercentage,
        private float  $remainingAmount,
        private int    $currentStreak
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->getEmailSubject())
            ->greeting("Bonjour {$notifiable->name} !")
            ->line($this->getMotivationLine())
            ->line($this->getProgressLine())
            ->line($this->getStreakLine())
            ->action('Faire avancer ma quête', url('/quete'))
            ->line('30 secondes suffisent. Ton futur toi te remerciera. 🚀')
            ->salutation('— L\'équipe CoinQuest');
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'                => 'daily_reminder',
            'title'               => $this->getDatabaseTitle(),
            'body'                => $this->getDatabaseBody(),
            'icon'                => '🚀',
            'action_url'          => '/quete',
            'quest_name'          => $this->questName,
            'quest_emoji'         => $this->questEmoji,
            'progress_percentage' => $this->progressPercentage,
            'remaining_amount'    => $this->remainingAmount,
            'current_streak'      => $this->currentStreak,
        ];
    }

    // ==========================================
    // HELPERS PRIVÉS
    // ==========================================

    private function getEmailSubject(): string
    {
        if ($this->progressPercentage >= 75) {
            return "🎯 Plus que {$this->formatAmount($this->remainingAmount)} € pour {$this->questEmoji} {$this->questName} !";
        }

        if ($this->currentStreak >= 7) {
            return "🔥 {$this->currentStreak} jours de série — continue aujourd'hui !";
        }

        return "🚀 Fais avancer ta quête aujourd'hui";
    }

    private function getMotivationLine(): string
    {
        $messages = [
            "Chaque euro compte. C'est le moment d'agir pour **{$this->questEmoji} {$this->questName}**.",
            "Ta quête **{$this->questEmoji} {$this->questName}** progresse — une action aujourd'hui l'avancera encore.",
            "Les petites actions d'aujourd'hui font les grandes victoires de demain. **{$this->questEmoji} {$this->questName}** t'attend.",
        ];

        return $messages[array_rand($messages)];
    }

    private function getProgressLine(): string
    {
        $remaining = $this->formatAmount($this->remainingAmount);

        if ($this->progressPercentage >= 75) {
            return "🎯 Tu es à **{$this->progressPercentage}%** — plus que **{$remaining} €** pour atteindre ton objectif !";
        }

        return "📊 Progression actuelle : **{$this->progressPercentage}%** — il reste **{$remaining} €** à économiser.";
    }

    private function getStreakLine(): string
    {
        if ($this->currentStreak >= 30) {
            return "🏆 Incroyable : **{$this->currentStreak} jours de série** ! Tu es dans le top des utilisateurs CoinQuest.";
        }

        if ($this->currentStreak >= 7) {
            return "🔥 Tu es sur une série de **{$this->currentStreak} jours** — garde le rythme !";
        }

        if ($this->currentStreak >= 1) {
            return "🔥 Série en cours : **{$this->currentStreak} jour(s)**. Enregistre une action pour la maintenir.";
        }

        return "💡 Commence une nouvelle série aujourd'hui — les habitudes se créent une action à la fois.";
    }

    private function getDatabaseTitle(): string
    {
        if ($this->progressPercentage >= 75) {
            return "🎯 Tu y es presque !";
        }

        if ($this->currentStreak >= 7) {
            return "🔥 {$this->currentStreak}j de série — continue !";
        }

        return '🚀 Fais avancer ta quête aujourd\'hui';
    }

    private function getDatabaseBody(): string
    {
        return "{$this->questEmoji} {$this->questName} — {$this->progressPercentage}% atteint · {$this->formatAmount($this->remainingAmount)} € restants";
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 0, ',', ' ');
    }
}
