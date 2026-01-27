<?php

namespace App\Events;

use App\Models\Streak;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreakMilestone
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;

    public Streak $streak;

    public int $milestone;

    public int $bonusXp;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, Streak $streak, int $milestone, int $bonusXp = 0)
    {
        $this->user = $user;
        $this->streak = $streak;
        $this->milestone = $milestone;
        $this->bonusXp = $bonusXp;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->user->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'streak' => [
                'id' => $this->streak->id,
                'name' => $this->streak->name,
                'type' => $this->streak->type,
                'current_count' => $this->streak->current_count,
                'best_count' => $this->streak->best_count,
            ],
            'milestone' => $this->milestone,
            'bonus_xp' => $this->bonusXp,
            'timestamp' => now()->toISOString(),
        ];
    }
}
