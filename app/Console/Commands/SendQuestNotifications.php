<?php

namespace App\Console\Commands;

use App\Models\Quest;
use App\Models\Streak;
use App\Models\User;
use App\Notifications\DailyReminderNotification;
use App\Notifications\StreakReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Commande : envoi des notifications quotidiennes MVP.
 *
 * Planification dans routes/console.php (Laravel 11+) ou Kernel.php :
 *   Schedule::command('quest:notify')->dailyAt('09:00');     // rappel matin
 *   Schedule::command('quest:notify --streak')->dailyAt('20:00'); // alerte série soir
 *
 * Usage manuel :
 *   php artisan quest:notify             → rappels matin
 *   php artisan quest:notify --streak    → alertes série en danger
 *   php artisan quest:notify --dry-run   → simulation sans envoi
 */
class SendQuestNotifications extends Command
{
    protected $signature   = 'quest:notify
                              {--streak : Envoyer uniquement les alertes de série en danger}
                              {--dry-run : Simuler sans envoyer}';

    protected $description = 'Envoie les notifications quotidiennes aux utilisateurs actifs';

    public function handle(): int
    {
        $isDryRun    = $this->option('dry-run');
        $streakOnly  = $this->option('streak');

        $this->info($isDryRun ? '🔍 Mode dry-run — aucun email ne sera envoyé' : '📬 Envoi des notifications...');

        if ($streakOnly) {
            $sent = $this->sendStreakReminders($isDryRun);
        } else {
            $sent = $this->sendDailyReminders($isDryRun);
        }

        $this->info("✅ {$sent} notification(s) " . ($isDryRun ? 'simulée(s)' : 'envoyée(s)'));

        return self::SUCCESS;
    }

    // ==========================================
    // RAPPELS QUOTIDIENS (matin)
    // ==========================================

    /**
     * Envoyer les rappels aux utilisateurs qui n'ont pas encore agi aujourd'hui
     */
    private function sendDailyReminders(bool $isDryRun): int
    {
        $sent = 0;

        // Utilisateurs actifs avec une quête principale
        $users = User::whereNull('deleted_at')
            ->with(['quests' => fn($q) => $q->where('is_main', true)->where('status', 'active')])
            ->get();

        foreach ($users as $user) {
            $quest = $user->quests->first();

            if (!$quest) {
                continue;
            }

            // Ne pas envoyer si l'utilisateur a déjà agi aujourd'hui
            if ($this->hasActedToday($user->id)) {
                continue;
            }

            $streak = $user->streaks()->where('type', 'daily')->first();

            $this->line("  → {$user->email} | {$quest->emoji} {$quest->name} | {$quest->progress_percentage}%");

            if (!$isDryRun) {
                $user->notify(new DailyReminderNotification(
                    questName:          $quest->name,
                    questEmoji:         $quest->emoji,
                    progressPercentage: $quest->progress_percentage,
                    remainingAmount:    $quest->remaining_amount,
                    currentStreak:      $streak?->current_count ?? 0,
                ));
            }

            $sent++;
        }

        return $sent;
    }

    // ==========================================
    // ALERTES SÉRIE EN DANGER (soir)
    // ==========================================

    /**
     * Envoyer les alertes aux utilisateurs dont la série est en danger
     * (série > 0 ET pas d'action depuis >= 20h)
     */
    private function sendStreakReminders(bool $isDryRun): int
    {
        $sent = 0;

        // Streaks actives de type 'daily' avec current_count > 0
        $atRiskStreaks = Streak::where('type', 'daily')
            ->where('is_active', true)
            ->where('current_count', '>', 0)
            ->whereDate('last_activity_date', today())  // dernière action aujourd'hui
            ->with('user')
            ->get();

        // On ne cible que les streaks où l'utilisateur a agi aujourd'hui
        // MAIS n'a pas encore enregistré d'action (streak maintenue mais fragile)
        // → En pratique : utilisateurs avec streak > 0 et last_activity_date = hier
        $atRiskStreaks = Streak::where('type', 'daily')
            ->where('is_active', true)
            ->where('current_count', '>', 0)
            ->whereDate('last_activity_date', today()->subDay())  // dernière action = hier
            ->with(['user.quests' => fn($q) => $q->where('is_main', true)->where('status', 'active')])
            ->get();

        foreach ($atRiskStreaks as $streak) {
            $user  = $streak->user;
            $quest = $user?->quests?->first();

            if (!$user || $user->deleted_at) {
                continue;
            }

            // Déjà agi aujourd'hui → pas de danger
            if ($this->hasActedToday($user->id)) {
                continue;
            }

            $this->line("  🔥 {$user->email} | série: {$streak->current_count}j");

            if (!$isDryRun) {
                $user->notify(new StreakReminderNotification(
                    currentStreak: $streak->current_count,
                    questName:     $quest?->name    ?? 'Ma quête',
                    questEmoji:    $quest?->emoji   ?? '🎯',
                ));
            }

            $sent++;
        }

        return $sent;
    }

    // ==========================================
    // HELPERS
    // ==========================================

    private function hasActedToday(int $userId): bool
    {
        return \App\Models\DailyAction::where('user_id', $userId)
            ->whereDate('action_date', today())
            ->exists();
    }
}
