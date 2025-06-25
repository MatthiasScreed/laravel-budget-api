<?php

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Transaction $transaction;
    protected int $xpEarned;

    /**
     * Create a new notification instance.
     */
    public function __construct(Transaction $transaction, int $xpEarned = 0)
    {
        $this->transaction = $transaction;
        $this->xpEarned = $xpEarned;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'transaction_created',
            'title' => 'Transaction enregistrée ! 💸',
            'message' => "Transaction de {$this->transaction->amount}€ enregistrée dans {$this->transaction->category->name}",
            'icon' => $this->transaction->type === 'income' ? '💰' : '💸',
            'action_url' => "/transactions/{$this->transaction->id}",
            'transaction_id' => $this->transaction->id,
            'amount' => $this->transaction->amount,
            'type' => $this->transaction->type,
            'category' => $this->transaction->category->name,
            'xp_earned' => $this->xpEarned
        ];
    }
}
