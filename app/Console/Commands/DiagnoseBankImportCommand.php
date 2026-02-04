<?php

namespace App\Console\Commands;

use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Commande de diagnostic et réimportation des transactions bancaires
 *
 * Usage:
 *   php artisan bank:diagnose                    # Diagnostic complet
 *   php artisan bank:diagnose --user=1           # Pour un utilisateur spécifique
 *   php artisan bank:diagnose --reimport         # Forcer réimportation
 *   php artisan bank:diagnose --convert          # Convertir bank_transactions -> transactions
 */
class DiagnoseBankImportCommand extends Command
{
    protected $signature = 'bank:diagnose
                            {--user= : ID utilisateur spécifique}
                            {--reimport : Forcer la réimportation (supprime les doublons)}
                            {--convert : Convertir les bank_transactions en transactions}
                            {--dry-run : Simulation sans modifications}';

    protected $description = 'Diagnostiquer et corriger les imports bancaires Bridge';

    public function handle(): int
    {
        $this->info('🔍 Diagnostic des imports bancaires Bridge');
        $this->newLine();

        $userId = $this->option('user');
        $reimport = $this->option('reimport');
        $convert = $this->option('convert');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🧪 Mode simulation - aucune modification ne sera effectuée');
        }

        // 1. État des connexions
        $this->showConnectionsStatus($userId);

        // 2. État des transactions bancaires
        $this->showBankTransactionsStatus($userId);

        // 3. État des transactions converties
        $this->showConvertedTransactionsStatus($userId);

        // 4. Réimportation si demandée
        if ($reimport) {
            $this->reimportTransactions($userId, $dryRun);
        }

        // 5. Conversion si demandée
        if ($convert) {
            $this->convertBankTransactions($userId, $dryRun);
        }

        return Command::SUCCESS;
    }

    /**
     * Afficher l'état des connexions bancaires
     */
    protected function showConnectionsStatus(?int $userId): void
    {
        $this->info('📊 État des connexions bancaires:');

        $query = BankConnection::query();
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $connections = $query->get();

        if ($connections->isEmpty()) {
            $this->warn('  Aucune connexion trouvée');
            return;
        }

        $this->table(
            ['ID', 'User', 'Banque', 'Status', 'Provider ID', 'Dernière sync', 'Transactions'],
            $connections->map(fn($c) => [
                $c->id,
                $c->user_id,
                $c->bank_name ?? 'N/A',
                $c->status,
                $c->provider_connection_id ?? 'N/A',
                $c->last_synced_at?->format('Y-m-d H:i') ?? 'Jamais',
                BankTransaction::where('bank_connection_id', $c->id)->count(),
            ])
        );
    }

    /**
     * Afficher l'état des transactions bancaires
     */
    protected function showBankTransactionsStatus(?int $userId): void
    {
        $this->newLine();
        $this->info('💳 État des transactions bancaires (bank_transactions):');

        $query = BankTransaction::query();
        if ($userId) {
            $query->whereHas('bankConnection', fn($q) => $q->where('user_id', $userId));
        }

        $total = $query->count();
        $byStatus = $query->clone()
            ->select('processing_status', DB::raw('count(*) as count'))
            ->groupBy('processing_status')
            ->pluck('count', 'processing_status')
            ->toArray();

        $this->line("  Total: {$total}");

        foreach ($byStatus as $status => $count) {
            $emoji = match($status) {
                'imported' => '📥',
                'categorized' => '🏷️',
                'converted' => '✅',
                'ignored' => '🚫',
                'duplicate' => '🔄',
                default => '❓'
            };
            $this->line("  {$emoji} {$status}: {$count}");
        }

        // Transactions non converties
        $notConverted = $query->clone()
            ->whereNotIn('processing_status', ['converted', 'ignored', 'duplicate'])
            ->count();

        if ($notConverted > 0) {
            $this->warn("  ⚠️ {$notConverted} transactions en attente de conversion");
        }
    }

    /**
     * Afficher l'état des transactions converties
     */
    protected function showConvertedTransactionsStatus(?int $userId): void
    {
        $this->newLine();
        $this->info('📦 État des transactions utilisateur (transactions):');

        $query = Transaction::query();
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $total = $query->count();
        $fromBridge = $query->clone()->whereNotNull('external_id')->count();
        $manual = $total - $fromBridge;

        $this->line("  Total: {$total}");
        $this->line("  🏦 Depuis Bridge: {$fromBridge}");
        $this->line("  ✏️ Manuelles: {$manual}");

        // Par type
        $byType = $query->clone()
            ->select('type', DB::raw('count(*) as count'), DB::raw('sum(amount) as total'))
            ->groupBy('type')
            ->get();

        foreach ($byType as $row) {
            $emoji = $row->type === 'income' ? '💚' : '❤️';
            $this->line("  {$emoji} {$row->type}: {$row->count} (Total: " . number_format($row->total, 2) . "€)");
        }
    }

    /**
     * Forcer la réimportation des transactions
     */
    protected function reimportTransactions(?int $userId, bool $dryRun): void
    {
        $this->newLine();
        $this->warn('🔄 Réimportation des transactions...');

        if (!$this->confirm('Cela va supprimer les bank_transactions existantes et les réimporter. Continuer?')) {
            return;
        }

        $query = BankConnection::where('status', 'active');
        if ($userId) {
            $query->where('user_id', $userId);
        }

        $connections = $query->get();

        foreach ($connections as $connection) {
            $this->line("  📡 Connexion #{$connection->id} ({$connection->bank_name})");

            if ($dryRun) {
                $this->line("    [DRY-RUN] Suppression de " . BankTransaction::where('bank_connection_id', $connection->id)->count() . " transactions");
                continue;
            }

            // Supprimer les anciennes transactions bancaires
            $deleted = BankTransaction::where('bank_connection_id', $connection->id)->delete();
            $this->line("    🗑️ {$deleted} transactions supprimées");

            // Lancer le job de sync
            try {
                dispatch(new \App\Jobs\SyncBankTransactionsJob($connection));
                $this->line("    ✅ Job de sync lancé");
            } catch (\Exception $e) {
                $this->error("    ❌ Erreur: " . $e->getMessage());
            }
        }
    }

    /**
     * Convertir les bank_transactions en transactions utilisateur
     */
    protected function convertBankTransactions(?int $userId, bool $dryRun): void
    {
        $this->newLine();
        $this->info('🔄 Conversion des transactions bancaires...');

        $query = BankTransaction::query()
            ->whereIn('processing_status', ['imported', 'categorized'])
            ->where('converted_transaction_id', null);

        if ($userId) {
            $query->whereHas('bankConnection', fn($q) => $q->where('user_id', $userId));
        }

        $toConvert = $query->get();

        $this->line("  📊 {$toConvert->count()} transactions à convertir");

        if ($toConvert->isEmpty()) {
            $this->info('  ✅ Aucune transaction à convertir');
            return;
        }

        $converted = 0;
        $errors = 0;

        foreach ($toConvert as $bankTx) {
            if ($dryRun) {
                $this->line("  [DRY-RUN] Convertirait: {$bankTx->description} ({$bankTx->amount}€)");
                $converted++;
                continue;
            }

            try {
                $transaction = $this->createTransactionFromBankTx($bankTx);

                // Marquer comme convertie
                $bankTx->update([
                    'processing_status' => 'converted',
                    'converted_transaction_id' => $transaction->id,
                ]);

                $converted++;
            } catch (\Exception $e) {
                $errors++;
                Log::error("Erreur conversion bank_tx #{$bankTx->id}: " . $e->getMessage());
            }
        }

        $this->info("  ✅ {$converted} transactions converties");
        if ($errors > 0) {
            $this->warn("  ⚠️ {$errors} erreurs");
        }
    }

    /**
     * Créer une transaction utilisateur depuis une transaction bancaire
     */
    protected function createTransactionFromBankTx(BankTransaction $bankTx): Transaction
    {
        $connection = $bankTx->bankConnection;
        $userId = $connection->user_id;

        // Déterminer le type (income ou expense)
        $amount = abs($bankTx->amount);
        $type = $bankTx->amount >= 0 ? 'income' : 'expense';

        return Transaction::create([
            'user_id' => $userId,
            'external_id' => $bankTx->external_id,
            'amount' => $amount,
            'description' => $bankTx->description ?? $bankTx->formatted_description ?? 'Transaction importée',
            'type' => $type,
            'transaction_date' => $bankTx->transaction_date,
            'category_id' => $bankTx->suggested_category_id,
            'status' => 'completed',
            'source' => 'bank_import',
            'metadata' => [
                'bank_connection_id' => $connection->id,
                'bank_transaction_id' => $bankTx->id,
                'original_amount' => $bankTx->amount,
                'merchant_name' => $bankTx->merchant_name,
                'imported_at' => now()->toISOString(),
            ],
        ]);
    }
}
