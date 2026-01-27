<?php

namespace App\Notifications;

use App\Models\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BudgetExceededNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Category $category;

    protected float $budgetLimit;

    protected float $currentSpending;

    protected float $percentage;

    /**
     * Create a new notification instance.
     */
    public function __construct(Category $category, float $budgetLimit, float $currentSpending)
    {
        $this->category = $category;
        $this->budgetLimit = $budgetLimit;
        $this->currentSpending = $currentSpending;
        $this->percentage = ($currentSpending / $budgetLimit) * 100;
    }

    /**
     * Get the notification's delivery channels.
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
            ->subject('⚠️ Budget dépassé - '.$this->category->name)
            ->greeting("Attention {$notifiable->name} !")
            ->line("Votre budget pour la catégorie **{$this->category->name}** a été dépassé.")
            ->line("Budget fixé : {$this->budgetLimit}€")
            ->line("Dépenses actuelles : {$this->currentSpending}€")
            ->line('Dépassement : '.round($this->percentage - 100, 1).'%')
            ->action('Voir mes dépenses', url("/categories/{$this->category->id}"))
            ->line('Conseil : Analysez vos dernières transactions pour identifier les dépenses importantes.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'budget_exceeded',
            'title' => 'Budget dépassé ! ⚠️',
            'message' => "Budget dépassé pour {$this->category->name} : {$this->currentSpending}€ / {$this->budgetLimit}€",
            'icon' => '⚠️',
            'action_url' => "/categories/{$this->category->id}",
            'category_id' => $this->category->id,
            'category_name' => $this->category->name,
            'budget_limit' => $this->budgetLimit,
            'current_spending' => $this->currentSpending,
            'percentage' => round($this->percentage, 1),
            'severity' => $this->percentage > 150 ? 'high' : 'medium',
        ];
    }
}
