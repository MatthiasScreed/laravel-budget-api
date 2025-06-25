<?php

namespace App\Notifications;

use App\Models\FinancialGoal;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class GoalCreatedNotification extends Notification implements ShouldQueue
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
            ->subject('🎯 Nouvel objectif créé !')
            ->greeting("Félicitations {$notifiable->name} ! 🎉")
            ->line("Vous venez de créer un nouvel objectif financier : **{$this->goal->name}**")
            ->line("Montant cible : {$this->goal->target_amount}€")
            ->line("Date cible : " . $this->goal->target_date->format('d/m/Y'))
            ->line('🏆 Vous avez gagné 50 XP pour cette action !')
            ->action('Voir mon objectif', url("/goals/{$this->goal->id}"))
            ->line('Bon courage pour atteindre cet objectif ! 💪');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'goal_created',
            'title' => 'Nouvel objectif créé !',
            'message' => "Vous avez créé l'objectif '{$this->goal->name}' d'un montant de {$this->goal->target_amount}€",
            'icon' => '🎯',
            'action_url' => "/goals/{$this->goal->id}",
            'goal_id' => $this->goal->id,
            'goal_name' => $this->goal->name,
            'target_amount' => $this->goal->target_amount,
            'xp_earned' => 50
        ];
    }
}
