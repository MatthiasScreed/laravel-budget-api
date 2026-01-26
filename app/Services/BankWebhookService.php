<?php

namespace App\Services;

use App\Jobs\SyncBankTransactionsJob;
use App\Models\BankConnection;
use Illuminate\Support\Facades\Log;

class BankWebhookService
{
    private BankIntegrationService $bankService;

    public function __construct(BankIntegrationService $bankService)
    {
        $this->bankService = $bankService;
    }

    /**
     * Traiter un webhook
     */
    public function processWebhook(array $payload): array
    {
        Log::info('📥 Webhook reçu', [
            'type' => $payload['type'] ?? 'unknown',
        ]);

        try {
            $result = $this->dispatchWebhookEvent($payload);

            Log::info('✅ Webhook traité', [
                'type' => $payload['type'] ?? 'unknown',
                'success' => $result['success'],
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('❌ Erreur webhook', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * ✅ Vérifier signature - CORRIGÉ
     */
    public function verifySignature(string $payload, ?string $signature): bool
    {
        if (! $signature) {
            Log::warning('⚠️ Webhook sans signature');

            return config('app.env') === 'local'; // Accepter en dev
        }

        $secret = config('banking.bridge.webhook_secret');

        if (! $secret) {
            Log::error('❌ BRIDGE_WEBHOOK_SECRET non configuré');

            return config('app.env') === 'local';
        }

        // Bridge utilise le format: v1=HASH
        if (preg_match('/^v1=([a-fA-F0-9]+)$/', $signature, $matches)) {
            $providedHash = $matches[1];
            $computedHash = strtoupper(hash_hmac('sha256', $payload, $secret));

            return hash_equals($computedHash, strtoupper($providedHash));
        }

        // Format simple (ancien)
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Router les événements
     */
    private function dispatchWebhookEvent(array $payload): array
    {
        $eventType = $payload['type'] ?? 'unknown';

        return match ($eventType) {
            'item.created' => $this->handleItemCreated($payload),
            'item.updated', 'item.refreshed' => $this->handleItemUpdated($payload),
            'item.error' => $this->handleItemError($payload),
            'transaction.created', 'transaction.updated' => $this->handleTransactionEvent($payload),
            default => ['success' => true, 'ignored' => true]
        };
    }

    private function handleItemCreated(array $payload): array
    {
        Log::info('🆕 Item créé', $payload['content'] ?? []);

        // En général, item.created arrive APRÈS le callback utilisateur
        // donc la connexion existe déjà

        return ['success' => true, 'action' => 'item_created'];
    }

    private function handleItemUpdated(array $payload): array
    {
        $itemId = $payload['content']['id'] ?? null;

        if (! $itemId) {
            return ['success' => true, 'ignored' => true];
        }

        $connection = BankConnection::where('connection_id', $itemId)->first();

        if ($connection) {
            SyncBankTransactionsJob::dispatch($connection);

            Log::info('🔄 Sync déclenchée', [
                'connection_id' => $connection->id,
            ]);
        }

        return ['success' => true, 'sync_dispatched' => true];
    }

    private function handleItemError(array $payload): array
    {
        $itemId = $payload['content']['id'] ?? null;
        $error = $payload['content']['error'] ?? 'Unknown error';

        if ($itemId) {
            $connection = BankConnection::where('connection_id', $itemId)->first();

            if ($connection) {
                $connection->update([
                    'status' => BankConnection::STATUS_ERROR,
                    'error_message' => $error,
                ]);
            }
        }

        return ['success' => true, 'error_recorded' => true];
    }

    private function handleTransactionEvent(array $payload): array
    {
        $itemId = $payload['content']['item_id'] ?? $payload['content']['id'] ?? null;

        if ($itemId) {
            $connection = BankConnection::where('connection_id', $itemId)->first();

            if ($connection) {
                SyncBankTransactionsJob::dispatch($connection, 7);
            }
        }

        return ['success' => true, 'sync_dispatched' => true];
    }
}
