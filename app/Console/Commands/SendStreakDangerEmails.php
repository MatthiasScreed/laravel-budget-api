<?php

namespace App\Console\Commands;

use App\Models\Streak;
use App\Models\User;
use App\Notifications\StreakDangerNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendStreakDangerEmails extends Command
{
    protected $signature   = 'coinquest:streak-danger';
    protected $description = 'Envoie un email aux utilisateurs dont la série est en danger ce soir (lancé à 18h)';

    /**
     * Seuil minimum de jours pour mériter un email d'alerte
     */
    private const MIN_STREAK_DAYS = 2;

    public function handle(): int
    {
        $today = now()->toDateString();
        $sent  = 0;

        $this->info("🔍 Recherche des séries en danger pour le {$today}...");

        // Récupère les utilisateurs avec une streak daily active
        // qui n'ont PAS agi aujourd'hui et ont au moins MIN_STREAK_DAYS
        $usersAtRisk = $this->getUsersAtRisk($today);

        $this->info("📧 {$usersAtRisk->count()} utilisateur(s) à alerter.");

        foreach ($usersAtRisk as $user) {
            $this->sendDangerAlert($user, $sent);
        }

        $this->info("✅ {$sent} email(s) envoyé(s).");

        Log::info("SendStreakDangerEmails: {$sent} emails envoyés", ['date' => $today]);

        return Command::SUCCESS;
    }

    /**
     * Récupère les users avec une streak daily en danger
     *
     * @param string $today
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getUsersAtRisk(string $today)
    {
        return User::whereHas('streaks', function ($query) use ($today) {
            $query->where('type', Streak::TYPE_DAILY_TRANSACTION)
                ->where('is_active', true)
                ->where('current_count', '>=', self::MIN_STREAK_DAYS)
                ->where(function ($q) use ($today) {
                    // N'a pas agi aujourd'hui
                    $q->whereNull('last_activity_date')
                        ->orWhereDate('last_activity_date', '<', $today);
                });
        })
            ->with(['streaks' => function ($query) use ($today) {
                $query->where('type', Streak::TYPE_DAILY_TRANSACTION)
                    ->where('is_active', true)
                    ->whereDate('last_activity_date', '<', $today);
            }])
            ->whereNotNull('email')
            ->get();
    }

    /**
     * Envoie l'alerte à un utilisateur et incrémente le compteur
     *
     * @param User $user
     * @param int  $sent référence au compteur
     */
    private function sendDangerAlert(User $user, int &$sent): void
    {
        $streak = $user->streaks->first();

        if (!$streak) {
            return;
        }

        try {
            $user->notify(new StreakDangerNotification($streak));
            $sent++;
            $this->line("  ✉️  {$user->email} — série de {$streak->current_count} jours");
        } catch (\Exception $e) {
            $this->error("  ❌ Erreur pour {$user->email} : " . $e->getMessage());
            Log::error("StreakDangerEmail failed for user {$user->id}", ['error' => $e->getMessage()]);
        }
    }
}
