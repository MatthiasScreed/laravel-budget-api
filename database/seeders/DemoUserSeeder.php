<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\FinancialGoal;
use App\Models\GoalContribution;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Vérifier si l'utilisateur existe déjà
        $existingUser = User::where('email', 'demo@budget-gaming.com')->first();
        if ($existingUser) {
            $this->command->info('Utilisateur de démo existe déjà, suppression...');
            $existingUser->delete();
        }

        // Créer un utilisateur de démo
        $user = User::create([
            'name' => 'Marie Dupont',
            'email' => 'demo@budget-gaming.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        $this->command->info("✅ Utilisateur de démo créé : {$user->email} / password");

        // Créer des catégories de base
        $this->createCategories($user);

        // Créer des objectifs financiers
        $this->createFinancialGoals($user);

        // Créer des transactions
        $this->createTransactions($user);

        // Créer des contributions
        $this->createContributions($user);

        $this->command->info('✅ Données de démo créées avec succès !');

    }

    /**
     * Créer les catégories de base
     */
    protected function createCategories(User $user): void
    {
        $categories = [
            // Revenus
            ['name' => 'Salaire', 'type' => 'income', 'color' => '#10B981', 'icon' => 'dollar-sign'],
            ['name' => 'Freelance', 'type' => 'income', 'color' => '#059669', 'icon' => 'briefcase'],
            ['name' => 'Prime', 'type' => 'income', 'color' => '#34D399', 'icon' => 'gift'],

            // Dépenses
            ['name' => 'Alimentation', 'type' => 'expense', 'color' => '#F59E0B', 'icon' => 'utensils'],
            ['name' => 'Transport', 'type' => 'expense', 'color' => '#3B82F6', 'icon' => 'car'],
            ['name' => 'Logement', 'type' => 'expense', 'color' => '#8B5CF6', 'icon' => 'home'],
            ['name' => 'Loisirs', 'type' => 'expense', 'color' => '#EC4899', 'icon' => 'gamepad-2'],
            ['name' => 'Santé', 'type' => 'expense', 'color' => '#EF4444', 'icon' => 'heart'],
            ['name' => 'Shopping', 'type' => 'expense', 'color' => '#F97316', 'icon' => 'shopping-bag'],
            ['name' => 'Éducation', 'type' => 'expense', 'color' => '#7C3AED', 'icon' => 'book'],
        ];

        foreach ($categories as $index => $categoryData) {
            $categoryData['user_id'] = $user->id;
            $categoryData['sort_order'] = $index;
            $categoryData['is_active'] = true;

            Category::create($categoryData);
        }

        $this->command->info('✅ Catégories créées');
    }

    /**
     * Créer des objectifs financiers
     */
    protected function createFinancialGoals(User $user): void
    {
        $goals = [
            [
                'name' => 'Voyage au Japon',
                'description' => 'Voyage de 2 semaines au Japon en automne',
                'target_amount' => 3500,
                'current_amount' => 450,
                'target_date' => now()->addMonths(8),
                'start_date' => now()->subMonths(2),
                'type' => 'purchase',
                'color' => '#10B981',
                'icon' => 'airplane',
                'priority' => 1,
                'monthly_target' => 350,
            ],
            [
                'name' => 'Fonds d\'urgence',
                'description' => 'Constituer une réserve de 6 mois de charges',
                'target_amount' => 12000,
                'current_amount' => 2800,
                'target_date' => now()->addMonths(18),
                'start_date' => now()->subMonths(4),
                'type' => 'emergency_fund',
                'color' => '#8B5CF6',
                'icon' => 'shield',
                'priority' => 2,
                'monthly_target' => 600,
            ],
            [
                'name' => 'Nouvelle voiture',
                'description' => 'Achat d\'une voiture électrique',
                'target_amount' => 25000,
                'current_amount' => 5200,
                'target_date' => now()->addMonths(24),
                'start_date' => now()->subMonths(3),
                'type' => 'purchase',
                'color' => '#EF4444',
                'icon' => 'car',
                'priority' => 3,
                'monthly_target' => 900,
            ],
        ];

        foreach ($goals as $goalData) {
            $goalData['user_id'] = $user->id;
            $goalData['status'] = 'active';

            FinancialGoal::create($goalData);
        }

        $this->command->info('✅ Objectifs financiers créés');
    }

    /**
     * Créer des transactions de démo
     */
    protected function createTransactions(User $user): void
    {
        // Récupérer les catégories
        $salaryCategory = $user->categories()->where('name', 'Salaire')->first();
        $foodCategory = $user->categories()->where('name', 'Alimentation')->first();
        $transportCategory = $user->categories()->where('name', 'Transport')->first();
        $leisureCategory = $user->categories()->where('name', 'Loisirs')->first();
        $freelanceCategory = $user->categories()->where('name', 'Freelance')->first();
        $shoppingCategory = $user->categories()->where('name', 'Shopping')->first();

        // Transactions du mois courant
        $transactions = [
            // Revenus
            [
                'category_id' => $salaryCategory->id,
                'type' => 'income',
                'amount' => 2800,
                'transaction_date' => now()->startOfMonth(),
                'description' => 'Salaire mensuel',
                'payment_method' => 'transfer',
                'status' => 'completed',
            ],
            [
                'category_id' => $freelanceCategory->id,
                'type' => 'income',
                'amount' => 650,
                'transaction_date' => now()->subDays(10),
                'description' => 'Mission freelance site web',
                'payment_method' => 'transfer',
                'status' => 'completed',
            ],

            // Dépenses
            [
                'category_id' => $foodCategory->id,
                'type' => 'expense',
                'amount' => 85.50,
                'transaction_date' => now()->subDays(2),
                'description' => 'Courses Carrefour',
                'payment_method' => 'card',
                'status' => 'completed',
            ],
            [
                'category_id' => $transportCategory->id,
                'type' => 'expense',
                'amount' => 45.20,
                'transaction_date' => now()->subDays(1),
                'description' => 'Plein d\'essence',
                'payment_method' => 'card',
                'status' => 'completed',
            ],
            [
                'category_id' => $foodCategory->id,
                'type' => 'expense',
                'amount' => 12.80,
                'transaction_date' => now(),
                'description' => 'Déjeuner restaurant',
                'payment_method' => 'cash',
                'status' => 'completed',
            ],
            [
                'category_id' => $leisureCategory->id,
                'type' => 'expense',
                'amount' => 25.00,
                'transaction_date' => now()->subDays(3),
                'description' => 'Cinéma',
                'payment_method' => 'card',
                'status' => 'completed',
            ],
            [
                'category_id' => $shoppingCategory->id,
                'type' => 'expense',
                'amount' => 89.99,
                'transaction_date' => now()->subDays(5),
                'description' => 'Vêtements H&M',
                'payment_method' => 'card',
                'status' => 'completed',
            ],
        ];

        // Ajouter des transactions pour le mois précédent
        $lastMonth = now()->subMonth();
        $lastMonthTransactions = [
            [
                'category_id' => $salaryCategory->id,
                'type' => 'income',
                'amount' => 2800,
                'transaction_date' => $lastMonth->startOfMonth(),
                'description' => 'Salaire mensuel',
                'payment_method' => 'transfer',
                'status' => 'completed',
            ],
            [
                'category_id' => $foodCategory->id,
                'type' => 'expense',
                'amount' => 320.45,
                'transaction_date' => $lastMonth->addDays(15),
                'description' => 'Courses du mois',
                'payment_method' => 'card',
                'status' => 'completed',
            ],
            [
                'category_id' => $transportCategory->id,
                'type' => 'expense',
                'amount' => 180.00,
                'transaction_date' => $lastMonth->addDays(10),
                'description' => 'Abonnement transport',
                'payment_method' => 'transfer',
                'status' => 'completed',
            ],
        ];

        $allTransactions = array_merge($transactions, $lastMonthTransactions);

        foreach ($allTransactions as $transactionData) {
            $transactionData['user_id'] = $user->id;
            $transactionData['source'] = 'manual';

            Transaction::create($transactionData);
        }

        $this->command->info('✅ Transactions créées');
    }

    /**
     * Créer des contributions aux objectifs
     */
    protected function createContributions(User $user): void
    {
        $travelGoal = $user->financialGoals()->where('name', 'Voyage au Japon')->first();
        $emergencyGoal = $user->financialGoals()->where('name', 'Fonds d\'urgence')->first();
        $carGoal = $user->financialGoals()->where('name', 'Nouvelle voiture')->first();

        $contributions = [
            // Contributions voyage
            [
                'financial_goal_id' => $travelGoal->id,
                'amount' => 200,
                'date' => now()->subMonths(2),
                'description' => 'Contribution initiale voyage',
                'is_automatic' => false,
            ],
            [
                'financial_goal_id' => $travelGoal->id,
                'amount' => 150,
                'date' => now()->subMonth(),
                'description' => 'Contribution mensuelle voyage',
                'is_automatic' => true,
            ],
            [
                'financial_goal_id' => $travelGoal->id,
                'amount' => 100,
                'date' => now()->subDays(5),
                'description' => 'Contribution voyage',
                'is_automatic' => false,
            ],

            // Contributions fonds d'urgence
            [
                'financial_goal_id' => $emergencyGoal->id,
                'amount' => 1000,
                'date' => now()->subMonths(4),
                'description' => 'Contribution initiale fonds urgence',
                'is_automatic' => false,
            ],
            [
                'financial_goal_id' => $emergencyGoal->id,
                'amount' => 600,
                'date' => now()->subMonths(3),
                'description' => 'Contribution mensuelle fonds urgence',
                'is_automatic' => true,
            ],
            [
                'financial_goal_id' => $emergencyGoal->id,
                'amount' => 600,
                'date' => now()->subMonths(2),
                'description' => 'Contribution mensuelle fonds urgence',
                'is_automatic' => true,
            ],
            [
                'financial_goal_id' => $emergencyGoal->id,
                'amount' => 600,
                'date' => now()->subMonth(),
                'description' => 'Contribution mensuelle fonds urgence',
                'is_automatic' => true,
            ],

            // Contributions voiture
            [
                'financial_goal_id' => $carGoal->id,
                'amount' => 2000,
                'date' => now()->subMonths(3),
                'description' => 'Contribution initiale voiture',
                'is_automatic' => false,
            ],
            [
                'financial_goal_id' => $carGoal->id,
                'amount' => 1600,
                'date' => now()->subMonths(2),
                'description' => 'Contribution voiture',
                'is_automatic' => false,
            ],
            [
                'financial_goal_id' => $carGoal->id,
                'amount' => 800,
                'date' => now()->subMonth(),
                'description' => 'Contribution mensuelle voiture',
                'is_automatic' => true,
            ],
            [
                'financial_goal_id' => $carGoal->id,
                'amount' => 800,
                'date' => now()->subDays(3),
                'description' => 'Contribution mensuelle voiture',
                'is_automatic' => true,
            ],
        ];

        foreach ($contributions as $contributionData) {
            GoalContribution::create($contributionData);
        }

        // Mettre à jour les montants actuels des objectifs
        $travelGoal->recalculateCurrentAmount();
        $emergencyGoal->recalculateCurrentAmount();
        $carGoal->recalculateCurrentAmount();

        $this->command->info('✅ Contributions créées');
    }
}
