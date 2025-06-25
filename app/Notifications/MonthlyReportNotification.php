<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonthlyReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $reportData;
    protected Carbon $month;

    /**
     * Create a new notification instance.
     */
    public function __construct(array $reportData, Carbon $month)
    {
        $this->reportData = $reportData;
        $this->month = $month;
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
        $monthName = $this->month->locale('fr')->format('F Y');

        return (new MailMessage)
            ->subject("📊 Votre rapport mensuel - {$monthName}")
            ->greeting("Salut {$notifiable->name} ! 📊")
            ->line("Votre rapport financier pour {$monthName} est prêt !")
            ->line("**Résumé du mois :**")
            ->line("• Revenus : {$this->reportData['income']}€")
            ->line("• Dépenses : {$this->reportData['expenses']}€")
            ->line("• Solde : {$this->reportData['balance']}€")
            ->line("• Transactions : {$this->reportData['transactions_count']}")
            ->line("• XP gagné : {$this->reportData['xp_earned']}")
            ->action('Voir le rapport complet', url("/reports/monthly/{$this->month->format('Y-m')}"))
            ->line('Continuez vos efforts pour améliorer vos finances ! 💪');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'monthly_report',
            'title' => 'Rapport mensuel disponible 📊',
            'message' => "Votre rapport pour {$this->month->format('F Y')} est prêt à consulter",
            'icon' => '📊',
            'action_url' => "/reports/monthly/{$this->month->format('Y-m')}",
            'month' => $this->month->format('Y-m'),
            'report_data' => $this->reportData
        ];
    }
}
