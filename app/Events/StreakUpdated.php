<?php

namespace App\Events;

use App\Models\Streak;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StreakUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public Streak $streak;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, Streak $streak)
    {
        $this->user = $user;
        $this->streak = $streak;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->user->id),
        ];
    }

    /**
     * Nom de l'événement diffusé
     */
    public function broadcastAs(): string
    {
        return 'streak.updated';
    }

    /**
     * Données à diffuser
     */
    public function broadcastWith(): array
    {
        return [
            'streak_type' => $this->streak->type,
            'current_count' => $this->streak->current_count,
            'best_count' => $this->streak->best_count,
            'is_new_record' => $this->streak->current_count === $this->streak->best_count,
            'motivation_message' => $this->getMotivationMessage()
        ];
    }

    /**
     * Obtenir un message de motivation
     */
    protected function getMotivationMessage(): string
    {
        $count = $this->streak->current_count;

        return match(true) {
            $count >= 100 => '🔥 LÉGENDE ! ' . $count . ' jours consécutifs !',
            $count >= 50 => '🏆 CHAMPION ! ' . $count . ' jours de suite !',
            $count >= 30 => '⭐ EXCELLENT ! Un mois complet !',
            $count >= 14 => '💪 SUPER ! Deux semaines !',
            $count >= 7 => '🎯 BRAVO ! Une semaine complète !',
            $count >= 3 => '🚀 C\'est parti ! ' . $count . ' jours !',
            default => '👍 Continuez comme ça !'
        };
    }

}
