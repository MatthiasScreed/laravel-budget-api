<?php

namespace App\Notifications;

use App\Models\FinancialGoal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GoalCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected FinancialGoal $goal;

    /**
     * Create a new notification instance.
     */
    public function __construct(FinancialGoal $goal)
    {
        $this->goal = $goal;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🎉 Objectif atteint ! Félicitations !')
            ->greeting("Fantastique {$notifiable->name} ! 🎉")
            ->line("**Vous avez atteint votre objectif : {$this->goal->name} !**")
            ->line("Montant économisé : {$this->goal->current_amount}€ / {$this->goal->target_amount}€")
            ->line('🏆 Vous avez gagné 200 XP bonus pour cet accomplissement !')
            ->line('🎖️ Des succès supplémentaires ont peut-être été débloqués !')
            ->action('Voir mes objectifs', url('/goals'))
            ->line('Continuez sur cette lancée et créez un nouvel objectif ! 💪');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'goal_completed',
            'title' => 'Objectif atteint ! 🎉',
            'message' => "Félicitations ! Vous avez atteint votre objectif '{$this->goal->name}' de {$this->goal->target_amount}€",
            'icon' => '🎯',
            'action_url' => "/goals/{$this->goal->id}",
            'goal_id' => $this->goal->id,
            'goal_name' => $this->goal->name,
            'amount_saved' => $this->goal->current_amount,
            'target_amount' => $this->goal->target_amount,
            'xp_earned' => 200,
        ];
    }
}
