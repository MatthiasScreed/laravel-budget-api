<?php

namespace App\Notifications;

use App\Models\Streak;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class StreakMilestoneNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Streak $streak;

    protected int $milestone;

    /**
     * Create a new notification instance.
     */
    public function __construct(Streak $streak, int $milestone)
    {
        $this->streak = $streak;
        $this->milestone = $milestone;
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
            'type' => 'streak_milestone',
            'title' => 'Jalon de série atteint ! 🔥',
            'message' => "Vous avez atteint {$this->milestone} jours consécutifs pour '{$this->streak->name}' !",
            'icon' => '🔥',
            'action_url' => '/streaks',
            'streak_id' => $this->streak->id,
            'streak_name' => $this->streak->name,
            'milestone' => $this->milestone,
            'current_count' => $this->streak->current_count,
        ];
    }
}
