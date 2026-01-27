<?php

namespace App\Notifications;

use App\Models\Streak;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StreakBrokenNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Streak $streak;

    protected int $previousCount;

    /**
     * Create a new notification instance.
     */
    public function __construct(Streak $streak, int $previousCount)
    {
        $this->streak = $streak;
        $this->previousCount = $previousCount;
    }

    /**
     * Get the notification's delivery channels.
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
            'type' => 'streak_broken',
            'title' => 'Série interrompue 😞',
            'message' => "Votre série '{$this->streak->name}' de {$this->previousCount} jours s'est arrêtée",
            'icon' => '💔',
            'action_url' => '/streaks',
            'streak_id' => $this->streak->id,
            'streak_name' => $this->streak->name,
            'previous_count' => $this->previousCount,
            'encouragement' => 'Pas de souci ! Recommencez dès aujourd\'hui pour battre votre record !',
        ];
    }
}
