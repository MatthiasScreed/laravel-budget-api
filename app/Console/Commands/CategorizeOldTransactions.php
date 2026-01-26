<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionCategorizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Commande pour catégoriser automatiquement les anciennes transactions
 *
 * Usage:
 * php artisan transactions:categorize-old
 * php artisan transactions:categorize-old --user=1
 * php artisan transactions:categorize-old --dry-run
 * php artisan transactions:categorize-old --force
 */
class CategorizeOldTransactions extends Command
{
    /**
     * Signature de la commande
     */
    protected $signature = 'transactions:categorize-old
                            {--user= : ID de l\'utilisateur spécifique}
                            {--dry-run : Simuler sans sauvegarder}
                            {--force : Forcer même les transactions déjà catégorisées}
                            {--limit= : Limiter le nombre de transactions}';

    /**
     * Description de la commande
     */
    protected $description = 'Catégoriser automatiquement les anciennes transactions sans catégorie';

    /**
     * Service de catégorisation
     */
    protected TransactionCategorizationService $categorizationService;

    /**
     * Constructeur
     */
    public function __construct(TransactionCategorizationService $service)
    {
        parent::__construct();
        $this->categorizationService = $service;
    }

    /**
     * Exécuter la commande
     */
    public function handle(): int
    {
        $this->info('🤖 Début de la catégorisation automatique');
        $this->newLine();

        // Options
        $userId = $this->option('user');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $limit = $this->option('limit');

        if ($dryRun) {
            $this->warn('⚠️  Mode DRY-RUN activé - Aucune modification ne sera sauvegardée');
        }

        if ($force) {
            $this->warn('⚠️  Mode FORCE activé - Toutes les transactions seront retraitées');
        }

        try {
            // Récupérer les utilisateurs
            $users = $this->getUsers($userId);

            if ($users->isEmpty()) {
                $this->error('❌ Aucun utilisateur trouvé');

                return Command::FAILURE;
            }

            $this->info("👥 {$users->count()} utilisateur(s) à traiter");
            $this->newLine();

            // Statistiques globales
            $globalStats = [
                'total_users' => $users->count(),
                'total_transactions' => 0,
                'total_categorized' => 0,
                'total_failed' => 0,
                'total_skipped' => 0,
            ];

            // Barre de progression
            $progressBar = $this->output->createProgressBar($users->count());
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

            // Traiter chaque utilisateur
            foreach ($users as $user) {
                $progressBar->setMessage("Traitement: {$user->email}");

                $stats = $this->processUser($user, $dryRun, $force, $limit);

                $globalStats['total_transactions'] += $stats['total'];
                $globalStats['total_categorized'] += $stats['categorized'];
                $globalStats['total_failed'] += $stats['failed'];
                $globalStats['total_skipped'] += $stats['skipped'];

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Afficher les résultats
            $this->displayResults($globalStats, $dryRun);

            // Log des résultats
            Log::info('Catégorisation automatique terminée', $globalStats);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Erreur: '.$e->getMessage());
            Log::error('Erreur catégorisation automatique', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Récupérer les utilisateurs à traiter
     */
    private function getUsers($userId)
    {
        if ($userId) {
            return User::where('id', $userId)->get();
        }

        return User::all();
    }

    /**
     * Traiter un utilisateur
     */
    private function processUser(User $user, bool $dryRun, bool $force, $limit): array
    {
        $stats = [
            'total' => 0,
            'categorized' => 0,
            'failed' => 0,
            'skipped' => 0,
        ];

        // Récupérer les transactions à traiter
        $query = $user->transactions();

        if (! $force) {
            $query->whereNull('category_id');
        }

        if ($limit) {
            $query->limit((int) $limit);
        }

        $transactions = $query->orderBy('transaction_date', 'desc')->get();
        $stats['total'] = $transactions->count();

        // Aucune transaction à traiter
        if ($stats['total'] === 0) {
            return $stats;
        }

        // Traiter chaque transaction
        foreach ($transactions as $transaction) {
            try {
                // Si déjà catégorisée et pas en mode force
                if ($transaction->category_id && ! $force) {
                    $stats['skipped']++;

                    continue;
                }

                // Catégoriser
                $category = $this->categorizationService->categorize($transaction);

                if ($category) {
                    if (! $dryRun) {
                        $transaction->update([
                            'category_id' => $category->id,
                            'status' => Transaction::STATUS_COMPLETED,
                            'auto_categorized' => true,
                        ]);
                    }
                    $stats['categorized']++;
                } else {
                    $stats['failed']++;
                }

            } catch (\Exception $e) {
                $stats['failed']++;
                Log::error('Erreur catégorisation transaction', [
                    'transaction_id' => $transaction->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * Afficher les résultats
     */
    private function displayResults(array $stats, bool $dryRun): void
    {
        $this->info('═══════════════════════════════════════════');
        $this->info('📊 RÉSULTATS DE LA CATÉGORISATION');
        $this->info('═══════════════════════════════════════════');
        $this->newLine();

        // Tableau des résultats
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Utilisateurs traités', $stats['total_users']],
                ['Transactions analysées', $stats['total_transactions']],
                ['✅ Catégorisées', "<fg=green>{$stats['total_categorized']}</>"],
                ['❌ Échecs', "<fg=red>{$stats['total_failed']}</>"],
                ['⏭️  Ignorées', "<fg=yellow>{$stats['total_skipped']}</>"],
            ]
        );

        $this->newLine();

        // Taux de succès
        if ($stats['total_transactions'] > 0) {
            $successRate = round(($stats['total_categorized'] / $stats['total_transactions']) * 100, 2);

            if ($successRate >= 80) {
                $this->info("🎯 Taux de succès: {$successRate}% - Excellent!");
            } elseif ($successRate >= 60) {
                $this->comment("📈 Taux de succès: {$successRate}% - Bien");
            } else {
                $this->warn("⚠️  Taux de succès: {$successRate}% - À améliorer");
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('⚠️  AUCUNE MODIFICATION N\'A ÉTÉ SAUVEGARDÉE (mode dry-run)');
        }

        $this->newLine();
    }
}
