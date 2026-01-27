<?php

namespace App\Events;

use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GoalCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;

    public FinancialGoal $goal;

    /**
     * Create a new event instance.
     *
     * @param  User  $user  L'utilisateur qui a créé l'objectif
     * @param  FinancialGoal  $goal  L'objectif financier créé
     */
    public function __construct(User $user, FinancialGoal $goal)
    {
        $this->user = $user;
        $this->goal = $goal;
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
        return 'goal.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'goal' => [
                'id' => $this->goal->id,
                'name' => $this->goal->name,
                'target_amount' => $this->goal->target_amount,
                'priority' => $this->goal->priority,
                'category' => $this->goal->category,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }
}
