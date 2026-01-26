<?php

namespace App\Events;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AchievementUnlocked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;

    public Achievement $achievement;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, Achievement $achievement)
    {
        $this->user = $user;
        $this->achievement = $achievement;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->user->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'achievement.unlocked';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'achievement' => [
                'id' => $this->achievement->id,
                'name' => $this->achievement->name,
                'description' => $this->achievement->description,
                'icon' => $this->achievement->icon,
                'points' => $this->achievement->points,
                'rarity' => $this->achievement->rarity,
                'rarity_name' => $this->achievement->rarity_name,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'total_xp' => $this->user->level?->total_xp ?? 0,
                'level' => $this->user->level?->level ?? 1,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}
