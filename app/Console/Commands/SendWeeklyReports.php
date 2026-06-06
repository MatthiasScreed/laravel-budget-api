<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Streak;
use App\Notifications\WeeklyReportNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendWeeklyReports extends Command
{
    protected $signature   = 'coinquest:weekly-report';
    protected $description = 'Envoie le résumé hebdomadaire chaque lundi matin';

    public function handle(): int
    {
        $weekStart = now()->startOfWeek()->subWeek(); // semaine écoulée (lundi précédent)
        $weekEnd   = $weekStart->copy()->endOfWeek();
        $sent      = 0;

        $this->info("📊 Résumés hebdomadaires — semaine du {$weekStart->toDateString()}");

        User::whereNotNull('email')
            ->whereNull('deleted_at')
            ->each(function (User $user) use ($weekStart, $weekEnd, &$sent) {
                $this->sendReport($user, $weekStart, $weekEnd, $sent);
            });

        $this->info("✅ {$sent} résumé(s) envoyé(s).");
        Log::info("SendWeeklyReports: {$sent} emails envoyés", ['week' => $weekStart->toDateString()]);

        return Command::SUCCESS;
    }

    /**
     * Compile les données et envoie le résumé à un utilisateur
     */
    private function sendReport(User $user, Carbon $weekStart, Carbon $weekEnd, int &$sent): void
    {
        try {
            $data = $this->buildReportData($user, $weekStart, $weekEnd);

            // N'envoie pas si aucune activité cette semaine ET pas de quête active
            if ($data['transactions_count'] === 0 && $data['goal_progress_pct'] === 0) {
                return;
            }

            $user->notify(new WeeklyReportNotification($data, $weekStart));
            $sent++;
            $this->line("  ✉️  {$user->email}");
        } catch (\Exception $e) {
            $this->error("  ❌ {$user->email} : " . $e->getMessage());
            Log::error("WeeklyReport failed for user {$user->id}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Construit le tableau de données pour la semaine écoulée
     */
    private function buildReportData(User $user, Carbon $weekStart, Carbon $weekEnd): array
    {
        // Transactions de la semaine
        $transactions = $user->transactions()
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();

        $saved = $transactions->where('type', 'income')->sum('amount');
        $spent = $transactions->where('type', 'expense')->sum('amount');

        // Streak courante
        $streak = $user->streaks()
            ->where('type', Streak::TYPE_DAILY_TRANSACTION)
            ->where('is_active', true)
            ->first();

        // Objectif principal actif
        $goal = $user->financialGoals()
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->first();

        $goalPct  = $goal ? min(100, round(($goal->current_amount / max(1, $goal->target_amount)) * 100)) : 0;
        $goalName = $goal?->name ?? 'Aucun objectif';

        // XP gagné cette semaine (approximation via level)
        $xpEarned = $user->level?->weekly_xp ?? 0;

        return [
            'saved'              => round($saved, 2),
            'spent'              => round($spent, 2),
            'balance'            => round($saved - $spent, 2),
            'transactions_count' => $transactions->count(),
            'streak_days'        => $streak?->current_count ?? 0,
            'xp_earned'          => $xpEarned,
            'goal_name'          => $goalName,
            'goal_progress_pct'  => $goalPct,
        ];
    }
}
