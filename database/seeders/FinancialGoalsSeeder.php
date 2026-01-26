<?php

namespace Database\Seeders;

use App\Models\FinancialGoal;
use App\Models\GoalContribution;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeder d'objectifs financiers réalistes
 *
 * ✅ VERSION FINALE - Colonnes corrigées selon la structure de la DB
 */
class FinancialGoalsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎯 Création des objectifs financiers...');

        // Récupérer l'utilisateur (le premier ou celui spécifié)
        $user = User::first();

        if (! $user) {
            $this->command->error('❌ Aucun utilisateur trouvé !');

            return;
        }

        $this->command->info("📊 Utilisateur: {$user->name} (ID: {$user->id})");

        // Supprimer les objectifs existants (optionnel)
        $existingCount = FinancialGoal::where('user_id', $user->id)->count();
        if ($existingCount > 0) {
            $this->command->warn("⚠️  {$existingCount} objectifs existants trouvés");

            if ($this->command->confirm('Voulez-vous les supprimer ?', true)) {
                FinancialGoal::where('user_id', $user->id)->delete();
                $this->command->info('✅ Objectifs supprimés');
            } else {
                $this->command->info('⏭️  Conservation des objectifs existants');

                return;
            }
        }

        // Créer des objectifs réalistes
        $goals = $this->createRealisticGoals($user);

        $this->command->info("✅ {$goals->count()} objectifs créés avec succès !");

        // Afficher le résumé
        $this->displaySummary($goals);
    }

    /**
     * Créer des objectifs réalistes pour l'utilisateur
     */
    protected function createRealisticGoals(User $user)
    {
        $goals = collect();

        // 1. FONDS D'URGENCE (3 mois de dépenses)
        $goals->push($this->createEmergencyFund($user));

        // 2. VACANCES D'ÉTÉ
        $goals->push($this->createVacationGoal($user));

        // 3. NOUVEAU LAPTOP
        $goals->push($this->createTechGoal($user));

        // 4. APPORT IMMOBILIER (long terme)
        $goals->push($this->createHousingGoal($user));

        // 5. VOITURE (moyen terme)
        $goals->push($this->createCarGoal($user));

        return $goals;
    }

    /**
     * Objectif 1 : Fonds d'urgence
     */
    protected function createEmergencyFund(User $user)
    {
        $goal = FinancialGoal::create([
            'user_id' => $user->id,
            'name' => '💼 Fonds d\'urgence',
            'description' => 'Épargne de sécurité équivalente à 3 mois de dépenses',
            'target_amount' => 3000.00,
            'current_amount' => 800.00,
            'target_date' => now()->addMonths(12),
            'type' => 'emergency_fund',
            'priority' => 1,
            'status' => 'active',
        ]);

        // Ajouter des contributions historiques
        $this->addContributions($goal, [
            ['amount' => 200, 'days_ago' => 60],
            ['amount' => 150, 'days_ago' => 45],
            ['amount' => 250, 'days_ago' => 30],
            ['amount' => 200, 'days_ago' => 15],
        ]);

        return $goal;
    }

    /**
     * Objectif 2 : Vacances d'été
     */
    protected function createVacationGoal(User $user)
    {
        $goal = FinancialGoal::create([
            'user_id' => $user->id,
            'name' => '🏖️ Vacances été 2025',
            'description' => '2 semaines au Portugal - Vols, hôtel, activités',
            'target_amount' => 2500.00,
            'current_amount' => 650.00,
            'target_date' => now()->addMonths(7),
            'type' => 'purchase',
            'priority' => 2,
            'status' => 'active',
        ]);

        $this->addContributions($goal, [
            ['amount' => 150, 'days_ago' => 50],
            ['amount' => 200, 'days_ago' => 35],
            ['amount' => 150, 'days_ago' => 20],
            ['amount' => 150, 'days_ago' => 10],
        ]);

        return $goal;
    }

    /**
     * Objectif 3 : Nouveau laptop
     */
    protected function createTechGoal(User $user)
    {
        $goal = FinancialGoal::create([
            'user_id' => $user->id,
            'name' => '💻 MacBook Pro M4',
            'description' => 'Nouveau laptop pour le travail',
            'target_amount' => 2200.00,
            'current_amount' => 420.00,
            'target_date' => now()->addMonths(6),
            'type' => 'purchase',
            'priority' => 3,
            'status' => 'active',
        ]);

        $this->addContributions($goal, [
            ['amount' => 200, 'days_ago' => 40],
            ['amount' => 120, 'days_ago' => 25],
            ['amount' => 100, 'days_ago' => 12],
        ]);

        return $goal;
    }

    /**
     * Objectif 4 : Apport immobilier
     */
    protected function createHousingGoal(User $user)
    {
        return FinancialGoal::create([
            'user_id' => $user->id,
            'name' => '🏠 Apport appartement',
            'description' => 'Économiser 20 000€ pour l\'apport d\'un T2',
            'target_amount' => 20000.00,
            'current_amount' => 2500.00,
            'target_date' => now()->addYears(3),
            'type' => 'savings',
            'priority' => 4,
            'status' => 'active',
        ]);
    }

    /**
     * Objectif 5 : Voiture
     */
    protected function createCarGoal(User $user)
    {
        return FinancialGoal::create([
            'user_id' => $user->id,
            'name' => '🚗 Voiture occasion',
            'description' => 'Citadine fiable 5-7 ans',
            'target_amount' => 8000.00,
            'current_amount' => 1200.00,
            'target_date' => now()->addMonths(18),
            'type' => 'purchase',
            'priority' => 5,
            'status' => 'active',
        ]);
    }

    /**
     * Ajouter des contributions historiques
     * ✅ COLONNES CORRIGÉES selon la structure de goal_contributions
     */
    protected function addContributions(FinancialGoal $goal, array $contributions)
    {
        foreach ($contributions as $contrib) {
            GoalContribution::create([
                'financial_goal_id' => $goal->id,
                'amount' => $contrib['amount'],
                'date' => now()->subDays($contrib['days_ago']),
                'description' => 'Contribution mensuelle',
                'is_automatic' => 0,
                'created_at' => now()->subDays($contrib['days_ago']),
                'updated_at' => now()->subDays($contrib['days_ago']),
            ]);
        }
    }

    /**
     * Afficher un résumé des objectifs créés
     */
    protected function displaySummary($goals)
    {
        $this->command->newLine();
        $this->command->info('📋 RÉSUMÉ DES OBJECTIFS CRÉÉS :');
        $this->command->info(str_repeat('=', 60));

        foreach ($goals as $goal) {
            $progress = $goal->target_amount > 0
                ? round(($goal->current_amount / $goal->target_amount) * 100, 1)
                : 0;

            $this->command->line(sprintf(
                '  %s %s',
                $this->getEmojiForType($goal->type),
                $goal->name
            ));

            $this->command->line(sprintf(
                '     Objectif: %s€  |  Économisé: %s€  |  Progression: %s%%',
                number_format($goal->target_amount, 2),
                number_format($goal->current_amount, 2),
                $progress
            ));

            $this->command->line(sprintf(
                '     Échéance: %s  |  Priorité: %d',
                $goal->target_date->format('d/m/Y'),
                $goal->priority
            ));

            $this->command->newLine();
        }

        $totalTarget = $goals->sum('target_amount');
        $totalSaved = $goals->sum('current_amount');
        $totalProgress = $totalTarget > 0 ? round(($totalSaved / $totalTarget) * 100, 1) : 0;

        $this->command->info(str_repeat('=', 60));
        $this->command->info(sprintf(
            'TOTAL: %s€ / %s€ (%s%%)',
            number_format($totalSaved, 2),
            number_format($totalTarget, 2),
            $totalProgress
        ));
    }

    /**
     * Emoji selon le type
     */
    protected function getEmojiForType(string $type): string
    {
        return match ($type) {
            'emergency_fund' => '💼',
            'savings' => '🏠',
            'purchase' => '🛒',
            'investment' => '📈',
            'debt_payoff' => '💳',
            default => '🎯'
        };
    }
}
