<?php

namespace App\Console\Commands;

use App\Models\BankTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GamingService;
use App\Services\TransactionCategorizationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Commande pour convertir les bank_transactions en transactions + catégorisation
 *
 * Usage:
 * php artisan bank:convert-all-transactions
 * php artisan bank:convert-all-transactions --user=1
 * php artisan bank:convert-all-transactions --dry-run
 * php artisan bank:convert-all-transactions --limit=50
 */
class ConvertBankTransactions extends Command
{
    /**
     * Signature de la commande
     */
    protected $signature = 'bank:convert-all-transactions
                            {--user= : ID de l\'utilisateur spécifique}
                            {--dry-run : Simuler sans sauvegarder}
                            {--limit= : Limiter le nombre de conversions}
                            {--force : Reconvertir même si déjà converti}';

    /**
     * Description de la commande
     */
    protected $description = 'Convertir les bank_transactions en transactions et les catégoriser automatiquement';

    /**
     * Services
     */
    protected TransactionCategorizationService $categorizationService;

    protected ?GamingService $gamingService;

    /**
     * Constructeur
     */
    public function __construct(TransactionCategorizationService $categorizationService)
    {
        parent::__construct();
        $this->categorizationService = $categorizationService;

        // Gaming service optionnel
        try {
            $this->gamingService = app(GamingService::class);
        } catch (\Exception $e) {
            $this->gamingService = null;
        }
    }

    /**
     * Exécuter la commande
     */
    public function handle(): int
    {
        $this->info('🔄 Conversion des transactions Bridge');
        $this->newLine();

        // Options
        $userId = $this->option('user');
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $limit = $this->option('limit');

        if ($dryRun) {
            $this->warn('⚠️  Mode DRY-RUN - Aucune modification ne sera sauvegardée');
        }

        if ($force) {
            $this->warn('⚠️  Mode FORCE - Reconversion des transactions déjà converties');
        }

        try {
            // Récupérer les bank_transactions à convertir
            $bankTransactions = $this->getBankTransactions($userId, $force, $limit);

            if ($bankTransactions->isEmpty()) {
                $this->info('✅ Aucune transaction à convertir');

                return Command::SUCCESS;
            }

            $this->info("📊 {$bankTransactions->count()} transaction(s) à convertir");
            $this->newLine();

            // Demander confirmation si pas en dry-run
            if (! $dryRun && ! $this->confirm('Continuer la conversion ?', true)) {
                $this->warn('❌ Conversion annulée');

                return Command::SUCCESS;
            }

            // Statistiques
            $stats = [
                'total' => $bankTransactions->count(),
                'converted' => 0,
                'categorized' => 0,
                'failed' => 0,
                'skipped' => 0,
                'xp_total' => 0,
            ];

            // Barre de progression
            $progressBar = $this->output->createProgressBar($bankTransactions->count());
            $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');

            // Convertir chaque transaction
            foreach ($bankTransactions as $bankTx) {
                $progressBar->setMessage('Conversion: '.substr($bankTx->description, 0, 30));

                $result = $this->convertTransaction($bankTx, $dryRun);

                $stats['converted'] += $result['converted'];
                $stats['categorized'] += $result['categorized'];
                $stats['failed'] += $result['failed'];
                $stats['skipped'] += $result['skipped'];
                $stats['xp_total'] += $result['xp_gained'];

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Afficher les résultats
            $this->displayResults($stats, $dryRun);

            // Log
            Log::info('Conversion bank_transactions terminée', $stats);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Erreur: '.$e->getMessage());
            Log::error('Erreur conversion bank_transactions', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Récupérer les bank_transactions à convertir
     */
    private function getBankTransactions($userId, bool $force, $limit)
    {
        $query = BankTransaction::with('bankConnection.user');

        // Filtrer par utilisateur si spécifié
        if ($userId) {
            $query->whereHas('bankConnection', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            });
        }

        // Ne pas prendre les déjà converties (sauf si force)
        if (! $force) {
            $query->whereNull('converted_transaction_id');
        }

        // Limiter si spécifié
        if ($limit) {
            $query->limit((int) $limit);
        }

        return $query->orderBy('transaction_date', 'desc')->get();
    }

    /**
     * Convertir une bank_transaction en transaction
     */
    private function convertTransaction(BankTransaction $bankTx, bool $dryRun): array
    {
        $result = [
            'converted' => 0,
            'categorized' => 0,
            'failed' => 0,
            'skipped' => 0,
            'xp_gained' => 0,
        ];

        try {
            // Vérifier si déjà convertie
            if ($bankTx->converted_transaction_id && ! $dryRun) {
                $result['skipped'] = 1;

                return $result;
            }

            // Récupérer l'utilisateur
            $user = $bankTx->bankConnection->user;

            if (! $user) {
                $result['failed'] = 1;

                return $result;
            }

            // Déterminer le type et le montant
            $amount = abs($bankTx->amount);
            $type = $bankTx->amount < 0 ? 'expense' : 'income';

            if (! $dryRun) {
                DB::beginTransaction();

                // Créer la transaction
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'bank_connection_id' => $bankTx->bank_connection_id,
                    'external_transaction_id' => $bankTx->external_transaction_id,
                    'bridge_transaction_id' => $bankTx->external_transaction_id,
                    'type' => $type,
                    'amount' => $amount,
                    'description' => $bankTx->description ?? 'Transaction importée',
                    'transaction_date' => $bankTx->transaction_date,
                    'status' => 'pending',
                    'source' => 'bridge',
                    'is_from_bridge' => true,
                    'auto_imported' => true,
                    'metadata' => json_encode([
                        'merchant_name' => $bankTx->merchant_name,
                        'merchant_category' => $bankTx->merchant_category,
                        'original_description' => $bankTx->formatted_description,
                    ]),
                ]);

                $result['converted'] = 1;

                // Catégoriser automatiquement
                $category = $this->categorizationService->categorize($transaction);

                if ($category) {
                    $transaction->update([
                        'category_id' => $category->id,
                        'status' => 'completed',
                        'auto_categorized' => true,
                    ]);
                    $result['categorized'] = 1;
                    $result['xp_gained'] += 3; // XP pour catégorisation
                }

                // Marquer la bank_transaction comme convertie
                $bankTx->update([
                    'converted_transaction_id' => $transaction->id,
                    'processing_status' => 'converted',
                ]);

                // Gaming XP
                if ($this->gamingService) {
                    try {
                        $this->gamingService->addExperience($user, 5, 'transaction_imported');
                        $result['xp_gained'] += 5;
                    } catch (\Exception $e) {
                        // XP non critique, on continue
                    }
                }

                DB::commit();
            } else {
                // Dry-run : simuler
                $result['converted'] = 1;

                // Simuler la catégorisation
                $tempTransaction = new Transaction([
                    'user_id' => $user->id,
                    'description' => $bankTx->description,
                    'amount' => $amount,
                    'type' => $type,
                ]);

                $category = $this->categorizationService->categorize($tempTransaction);
                if ($category) {
                    $result['categorized'] = 1;
                    $result['xp_gained'] = 8; // 5 + 3
                } else {
                    $result['xp_gained'] = 5;
                }
            }

        } catch (\Exception $e) {
            if (! $dryRun) {
                DB::rollBack();
            }

            $result['failed'] = 1;

            Log::error('Erreur conversion bank_transaction', [
                'bank_transaction_id' => $bankTx->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $result;
    }

    /**
     * Afficher les résultats
     */
    private function displayResults(array $stats, bool $dryRun): void
    {
        $this->info('═══════════════════════════════════════════');
        $this->info('📊 RÉSULTATS DE LA CONVERSION');
        $this->info('═══════════════════════════════════════════');
        $this->newLine();

        // Tableau des résultats
        $this->table(
            ['Métrique', 'Valeur'],
            [
                ['Total analysées', $stats['total']],
                ['✅ Converties', "<fg=green>{$stats['converted']}</>"],
                ['🎯 Catégorisées', "<fg=cyan>{$stats['categorized']}</>"],
                ['❌ Échecs', "<fg=red>{$stats['failed']}</>"],
                ['⏭️  Ignorées', "<fg=yellow>{$stats['skipped']}</>"],
                ['🎮 XP total', "<fg=magenta>{$stats['xp_total']}</>"],
            ]
        );

        $this->newLine();

        // Taux de succès
        if ($stats['total'] > 0) {
            $conversionRate = round(($stats['converted'] / $stats['total']) * 100, 2);

            if ($stats['converted'] > 0) {
                $categorizationRate = round(($stats['categorized'] / $stats['converted']) * 100, 2);

                if ($conversionRate >= 95) {
                    $this->info("🎯 Taux de conversion: {$conversionRate}% - Parfait!");
                } elseif ($conversionRate >= 80) {
                    $this->comment("📈 Taux de conversion: {$conversionRate}% - Bien");
                } else {
                    $this->warn("⚠️  Taux de conversion: {$conversionRate}% - À vérifier");
                }

                if ($categorizationRate >= 70) {
                    $this->info("🏆 Taux de catégorisation: {$categorizationRate}% - Excellent!");
                } elseif ($categorizationRate >= 50) {
                    $this->comment("📊 Taux de catégorisation: {$categorizationRate}% - Correct");
                } else {
                    $this->warn("⚠️  Taux de catégorisation: {$categorizationRate}% - Bas");
                }
            }
        }

        if ($dryRun) {
            $this->newLine();
            $this->warn('⚠️  AUCUNE MODIFICATION N\'A ÉTÉ SAUVEGARDÉE (mode dry-run)');
        } else {
            $this->newLine();
            $this->info('✅ Toutes les modifications ont été sauvegardées');
        }

        $this->newLine();

        // Prochaines étapes
        if (! $dryRun && $stats['converted'] > 0) {
            $this->info('💡 Prochaines étapes:');
            $this->line('   • Vérifier les transactions dans l\'interface');
            $this->line('   • Corriger manuellement les catégories si besoin');
            $this->line('   • Relancer pour les nouvelles transactions: php artisan bank:convert-all-transactions');
        }
    }
}
