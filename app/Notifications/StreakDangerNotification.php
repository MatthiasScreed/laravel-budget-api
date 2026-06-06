<?php

namespace App\Notifications;

use App\Models\Streak;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\HtmlString;

class StreakDangerNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Streak $streak;

    public function __construct(Streak $streak)
    {
        $this->streak = $streak;
    }

    /**
     * Canaux : mail + database
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Email "ta série est en danger"
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appUrl   = config('app.frontend_url', config('app.url'));
        $days     = $this->streak->current_count;
        $emoji    = $days >= 7 ? '🔥' : '⚡';
        $subject  = $days >= 7
            ? "🔥 Ta série de {$days} jours est en danger !"
            : "⚡ N'oublie pas d'épargner aujourd'hui !";

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Salut {$notifiable->name} ! {$emoji}")
            ->line($this->getDangerLine($days))
            ->line('')
            ->line($this->getMotivationLine($days))
            ->action('💰 Enregistrer une action maintenant', $appUrl . '/quete')
            ->line('')
            ->line(new HtmlString(
                '<small style="color:#9ca3af">Il suffit d\'1 minute. '
                . 'Ta série repart dès que tu enregistres une économie ou une dépense.</small>'
            ))
            ->salutation(new HtmlString("On compte sur toi ! 🎯<br>L'équipe CoinQuest"));
    }

    /**
     * Entrée en base pour le centre de notifs in-app
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'streak_danger',
            'title'         => "Série en danger 🔥",
            'message'       => "Ta série de {$this->streak->current_count} jours se termine ce soir !",
            'icon'          => '🔥',
            'action_url'    => '/quete',
            'streak_type'   => $this->streak->type,
            'streak_count'  => $this->streak->current_count,
        ];
    }

    // ==========================================
    // HELPERS PRIVÉS
    // ==========================================

    private function getDangerLine(int $days): string
    {
        if ($days === 0) {
            return "Tu n'as pas encore enregistré d'action aujourd'hui. C'est le moment !";
        }

        return "Ta série de **{$days} jour" . ($days > 1 ? 's' : '') . "** "
            . "expire à minuit si tu n'enregistres rien ce soir.";
    }

    private function getMotivationLine(int $days): string
    {
        return match(true) {
            $days >= 30 => "30 jours de suite, c'est énorme. Ne laisse pas tomber maintenant !",
            $days >= 7  => "Une semaine complète — tu es sur une belle lancée. 💪",
            $days >= 3  => "3 jours consécutifs, le rythme se prend. Continue !",
            default     => "Chaque action compte, même petite. Lance-toi !",
        };
    }
}
