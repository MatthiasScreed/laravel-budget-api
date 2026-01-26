<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserEngagementUpdate implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;

    public $engagementData;

    public function __construct(User $user, array $engagementData)
    {
        $this->user = $user;
        $this->engagementData = $engagementData;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->user->id.'.engagement'),
        ];
    }

    /**
     * Data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'engagement_update',
            'data' => $this->engagementData,
            'timestamp' => now()->toISOString(),
        ];
    }
}
