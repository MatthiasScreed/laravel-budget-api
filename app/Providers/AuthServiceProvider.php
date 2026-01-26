<?php

namespace App\Providers;

use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Policies\BankConnectionPolicy;
use App\Policies\BankTransactionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        BankConnection::class => BankConnectionPolicy::class,
        BankTransaction::class => BankTransactionPolicy::class,
        // Exemple de policies pour votre app budget gaming
        // 'App\Models\Transaction' => 'App\Policies\TransactionPolicy',
        // 'App\Models\FinancialGoal' => 'App\Policies\FinancialGoalPolicy',
        // 'App\Models\Category' => 'App\Policies\CategoryPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Enregistrer les policies automatiquement
        $this->registerPolicies();

        // ==========================================
        // GATES PERSONNALISÉS POUR VOTRE APP GAMING
        // ==========================================

        // Gate pour accès admin
        Gate::define('admin-access', function ($user) {
            return $user->is_admin ?? false;
        });

        // Gate pour fonctionnalités premium basées sur le niveau
        Gate::define('premium-features', function ($user) {
            return $user->level?->level >= 10;
        });

        // Gate pour export de données (niveau 20+)
        Gate::define('export-data', function ($user) {
            return $user->level?->level >= 20;
        });

        // Gate pour accès API (niveau 30+)
        Gate::define('api-access', function ($user) {
            return $user->level?->level >= 30;
        });

        // Gate pour modération communautaire
        Gate::define('moderate-community', function ($user) {
            return $user->level?->level >= 50 || $user->is_moderator ?? false;
        });

        // ==========================================
        // GATES POUR LES RESSOURCES FINANCIÈRES
        // ==========================================

        // Vérifier la propriété d'une transaction
        Gate::define('manage-transaction', function ($user, $transaction) {
            return $user->id === $transaction->user_id;
        });

        // Vérifier la propriété d'un objectif financier
        Gate::define('manage-goal', function ($user, $goal) {
            return $user->id === $goal->user_id;
        });

        // Vérifier la propriété d'une catégorie
        Gate::define('manage-category', function ($user, $category) {
            return $user->id === $category->user_id;
        });

        // ==========================================
        // GATES POUR LE SYSTÈME GAMING
        // ==========================================

        // Accès aux statistiques avancées
        Gate::define('advanced-stats', function ($user) {
            return $user->level?->level >= 15;
        });

        // Accès aux défis communautaires
        Gate::define('community-challenges', function ($user) {
            return $user->level?->level >= 5;
        });

        // Création de défis personnalisés
        Gate::define('create-custom-challenges', function ($user) {
            return $user->level?->level >= 25;
        });

        // ==========================================
        // SUPERUSER GATE (pour développement)
        // ==========================================

        Gate::before(function ($user, $ability) {
            // Superuser bypass (pour le développement)
            if ($user->email === 'admin@budget-gaming.com') {
                return true;
            }
        });
    }
}
