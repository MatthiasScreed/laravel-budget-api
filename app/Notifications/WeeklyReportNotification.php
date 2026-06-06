<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class WeeklyReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $data;
    protected Carbon $weekStart;

    public function __construct(array $data, Carbon $weekStart)
    {
        $this->data      = $data;
        $this->weekStart = $weekStart;
    }

    /**
     * Canaux : mail + database
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Email résumé hebdomadaire
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appUrl   = config('app.frontend_url', config('app.url'));
        $weekEnd  = $this->weekStart->copy()->addDays(6)->locale('fr')->isoFormat('D MMM');
        $weekFrom = $this->weekStart->locale('fr')->isoFormat('D MMM');

        $saved      = $this->data['saved'];
        $spent      = $this->data['spent'];
        $txCount    = $this->data['transactions_count'];
        $streak     = $this->data['streak_days'];
        $xp         = $this->data['xp_earned'];
        $goalPct    = $this->data['goal_progress_pct'];
        $goalName   = $this->data['goal_name'];

        $emoji   = $saved > 0 ? '🎉' : '💪';
        $subject = $saved > 0
            ? "🏆 Tu as économisé {$saved}€ cette semaine !"
            : "📊 Ton résumé de la semaine — {$weekFrom} au {$weekEnd}";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Salut {$notifiable->name} ! {$emoji}")
            ->line("Voici ce qui s'est passé sur CoinQuest du **{$weekFrom} au {$weekEnd}** :")
            ->line('')
            ->line($this->buildSummaryLine('💰', 'Économisé cette semaine', "{$saved}€"))
            ->line($this->buildSummaryLine('💸', 'Dépensé', "{$spent}€"))
            ->line($this->buildSummaryLine('📝', 'Actions enregistrées', "{$txCount}"))
            ->line($this->buildSummaryLine('🔥', 'Jours de série', "{$streak} jours"))
            ->line($this->buildSummaryLine('⚡', 'XP gagné', "{$xp} XP"))
            ->line($this->buildSummaryLine('🎯', "Quête « {$goalName} »", "{$goalPct}%"))
            ->line('')
            ->line($this->getMotivationLine($saved, $streak))
            ->action('Voir ma quête 🎯', $appUrl . '/quete')
            ->line('')
            ->line(new HtmlString(
                '<small style="color:#9ca3af">Tu reçois ce résumé chaque lundi. '
                . '<a href="' . $appUrl . '/profile">Gérer mes préférences</a></small>'
            ))
            ->salutation(new HtmlString("Bonne semaine ! 🚀<br>L'équipe CoinQuest"));
    }

    /**
     * Entrée en base pour le centre de notifs in-app
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'weekly_report',
            'title'      => "Résumé de la semaine 📊",
            'message'    => "Tu as économisé {$this->data['saved']}€ — série de {$this->data['streak_days']} jours",
            'icon'       => '📊',
            'action_url' => '/quete',
            'week_start' => $this->weekStart->toDateString(),
            'data'       => $this->data,
        ];
    }

    // ==========================================
    // HELPERS PRIVÉS
    // ==========================================

    private function buildSummaryLine(string $icon, string $label, string $value): string
    {
        return "{$icon} **{$label}** : {$value}";
    }

    private function getMotivationLine(float $saved, int $streak): string
    {
        return match(true) {
            $saved > 100 && $streak >= 7 => "Semaine parfaite ! Tu économises ET tu maintiens ta série. Continue ! 🔥",
            $saved > 0   && $streak >= 3  => "Belle semaine — chaque euro économisé rapproche de ton objectif. 💪",
            $saved > 0                    => "Tu as économisé cette semaine, c'est l'essentiel. La semaine prochaine, vise la série !",
            $streak >= 3                  => "Ta série tient bon ! Essaie d'enregistrer quelques économies la semaine prochaine.",
            default                       => "Nouvelle semaine, nouvelles opportunités. Une petite action suffit pour démarrer. 🎯",
        };
    }
}
