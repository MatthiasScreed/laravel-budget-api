<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LevelUp
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public User $user;
    public array $levelData;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user, array $levelData)
    {
        $this->user = $user;
        $this->levelData = $levelData;
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
        return 'level.up';
    }

    /**
     * Données à diffuser
     */
    public function broadcastWith(): array
    {
        return [
            'new_level' => $this->levelData['new_level'],
            'levels_gained' => $this->levelData['levels_gained'],
            'total_xp' => $this->levelData['total_xp'],
            'user_title' => $this->user->getTitle(),
            'celebration_message' => $this->getCelebrationMessage()
        ];
    }

    /**
     * Obtenir un message de félicitations personnalisé
     */
    protected function getCelebrationMessage(): string
    {
        $level = $this->levelData['new_level'];

        return match(true) {
            $level >= 100 => '🏆 Incroyable ! Vous êtes maintenant Maître de l\'Épargne !',
            $level >= 75 => '🎯 Fantastique ! Vous êtes Expert Financier !',
            $level >= 50 => '⭐ Excellent travail ! Niveau ' . $level . ' atteint !',
            $level >= 25 => '🎉 Bravo ! Vous progressez vers l\'expertise !',
            $level >= 10 => '👏 Super ! Vous maîtrisez les bases !',
            default => '🚀 Félicitations ! Niveau ' . $level . ' !'
        };
    }
}
