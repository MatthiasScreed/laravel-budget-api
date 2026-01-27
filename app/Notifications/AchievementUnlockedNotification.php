<?php

namespace App\Notifications;

use App\Models\Achievement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AchievementUnlockedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Achievement $achievement;

    /**
     * Create a new notification instance.
     */
    public function __construct(Achievement $achievement)
    {
        $this->achievement = $achievement;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'achievement_unlocked',
            'title' => 'Nouveau succès débloqué ! 🏆',
            'message' => "Vous avez débloqué le succès '{$this->achievement->name}'",
            'icon' => $this->achievement->icon ?? '🏆',
            'action_url' => '/achievements',
            'achievement_id' => $this->achievement->id,
            'achievement_name' => $this->achievement->name,
            'achievement_description' => $this->achievement->description,
            'xp_earned' => $this->achievement->points,
            'rarity' => $this->achievement->rarity,
            'rarity_color' => $this->achievement->rarity_color,
        ];
    }
}
