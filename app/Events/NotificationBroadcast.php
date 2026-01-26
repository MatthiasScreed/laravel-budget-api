<?php

namespace App\Events;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;

    public $notification;

    public function __construct(User $user, UserNotification $notification)
    {
        $this->user = $user;
        $this->notification = $notification;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->user->id.'.notifications'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'type' => 'new_notification',
            'notification' => [
                'id' => $this->notification->id,
                'type' => $this->notification->type,
                'title' => $this->notification->title,
                'body' => $this->notification->body,
                'data' => $this->notification->data,
                'timestamp' => $this->notification->created_at->toISOString(),
            ],
        ];
    }
}
