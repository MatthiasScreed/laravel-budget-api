<?php

namespace App\Console\Commands;

use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Console\Command;

class CleanDuplicateGoals extends Command
{
    protected $signature   = 'goals:clean-duplicates {--user= : ID utilisateur spécifique (optionnel)}';
    protected $description = 'Supprime les objectifs financiers en double (garde le plus ancien)';

    public function handle(): int
    {
        $userId = $this->option('user');

        // Cibler un utilisateur ou tous
        $users = $userId
            ? User::where('id', $userId)->get()
            : User::all();

        if ($users->isEmpty()) {
            $this->error('Aucun utilisateur trouvé.');
            return 1;
        }

        $totalDeleted = 0;

        foreach ($users as $user) {
            $goals = FinancialGoal::where('user_id', $user->id)
                ->orderBy('created_at', 'asc')
                ->get(['id', 'name', 'target_amount', 'created_at']);

            $seen     = [];
            $toDelete = [];

            foreach ($goals as $goal) {
                $key = strtolower(trim($goal->name)) . '|' . (float) $goal->target_amount;

                if (isset($seen[$key])) {
                    $toDelete[] = $goal->id;
                } else {
                    $seen[$key] = $goal->id;
                }
            }

            if (!empty($toDelete)) {
                FinancialGoal::whereIn('id', $toDelete)->delete();
                $totalDeleted += count($toDelete);

                $this->line("  👤 {$user->email} → " . count($toDelete) . " doublon(s) supprimé(s)");
            } else {
                $this->line("  👤 {$user->email} → aucun doublon");
            }
        }

        $this->newLine();
        $this->info("✅ Terminé — {$totalDeleted} doublon(s) supprimé(s) au total.");

        return 0;
    }
}
