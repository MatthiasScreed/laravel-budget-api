<?php

namespace App\Events;

use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GoalCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public FinancialGoal $goal;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, FinancialGoal $goal)
    {
        $this->user = $user;
        $this->goal = $goal;
    }

}
