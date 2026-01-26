<?php

namespace App\Jobs;

use App\Models\BankConnection;
use App\Models\User;
use App\Services\GamingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessBridgeWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue;
    use Queueable, SerializesModels;

    public string $eventType;

    public array $content;

    public array $rawData;

    /**
     * Timeout du job (max 5 min)
     */
    public int $timeout = 300;

    /**
     * Nombre de tentatives
     */
    public int $tries = 3;

    /**
     * Ne pas timeout le job
     */
    public int $maxExceptions = 3;

    public function __construct(
        string $eventType,
        array $content,
        array $rawData = []
    ) {
        $this->eventType = $eventType;
        $this->content = $content;
        $this->rawData = $rawData;
    }

    /**
     * Exécuter le job
     */
    public function handle(): void
    {
        Log::info('🔄 Processing Bridge webhook', [
            'event_type' => $this->eventType,
            'item_id' => $this->content['item_id'] ?? null,
            'user_uuid' => $this->content['user_uuid'] ?? null,
        ]);

        try {
            match ($this->eventType) {
                'item.created' => $this->handleItemCreated(),
                'item.updated' => $this->handleItemUpdated(),
                'item.refreshed' => $this->handleItemRefreshed(),
                'item.deleted' => $this->handleItemDeleted(),
                'item.error' => $this->handleItemError(),
                default => $this->handleUnknownEvent()
            };

            Log::info('✅ Webhook traité avec succès', [
                'event_type' => $this->eventType,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur traitement webhook', [
                'event_type' => $this->eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Item créé (connexion réussie)
     */
    private function handleItemCreated(): void
    {
        $itemId = $this->content['item_id'] ?? null;
        $userUuid = $this->content['user_uuid'] ?? null;

        if (! $itemId || ! $userUuid) {
            Log::warning('⚠️ item.created incomplet', [
                'content' => $this->content,
            ]);

            return;
        }

        // Trouver le user Laravel
        $user = User::where('bridge_user_uuid', $userUuid)
            ->first();

        if (! $user) {
            Log::error('❌ User non trouvé', [
                'bridge_user_uuid' => $userUuid,
            ]);

            return;
        }

        // Créer BankConnection
        $connection = $this->createBankConnection(
            $user,
            $itemId
        );

        // XP Reward
        $gamingService = app(GamingService::class);
        $gamingService->addXP(
            $user,
            100,
            'bank_connected'
        );

        // Lancer sync des transactions
        SyncBankTransactionsJob::dispatch($connection);

        Log::info('✅ Connexion créée via webhook', [
            'connection_id' => $connection->id,
            'user_id' => $user->id,
            'bank' => $connection->bank_name,
        ]);
    }

    /**
     * Item mis à jour
     */
    private function handleItemUpdated(): void
    {
        $itemId = $this->content['item_id'] ?? null;

        if (! $itemId) {
            return;
        }

        $connection = BankConnection::where(
            'provider_connection_id',
            $itemId
        )->first();

        if ($connection) {
            $connection->update([
                'status' => BankConnection::STATUS_ACTIVE,
                'last_sync_at' => now(),
            ]);

            Log::info('✅ Connexion mise à jour', [
                'connection_id' => $connection->id,
            ]);
        }
    }

    /**
     * Item rafraîchi (nouvelles transactions)
     */
    private function handleItemRefreshed(array $webhookData): void
    {
        try {
            $content = $webhookData['content'] ?? [];
            $itemId = $content['item_id'] ?? null;
            $statusCode = $content['status_code'] ?? null;
            $statusInfo = $content['status_code_info'] ?? null;

            if (! $itemId) {
                Log::warning('⚠️ Webhook sans item_id');

                return;
            }

            $connection = BankConnection::where('provider_connection_id', (string) $itemId)->first();

            if (! $connection) {
                Log::warning('⚠️ Connexion non trouvée', ['item_id' => $itemId]);

                return;
            }

            // ✅ Vérifier si c'est OK
            $isOk = in_array($statusCode, [0, '0', null], true)
                || strtolower($statusInfo ?? '') === 'ok';

            if ($isOk) {
                // ✅ FINALISER la connexion (récupérer détails banque)
                try {
                    $user = $connection->user;
                    $token = $this->getBridgeToken($user->bridge_user_uuid);

                    // Récupérer item details
                    $itemResponse = Http::timeout(30)
                        ->withHeaders([
                            'Client-Id' => config('services.bridge.client_id'),
                            'Client-Secret' => config('services.bridge.client_secret'),
                            'Bridge-Version' => '2025-01-15',
                            'Authorization' => "Bearer $token",
                        ])
                        ->get("https://api.bridgeapi.io/v3/aggregation/items/{$itemId}");

                    if ($itemResponse->successful()) {
                        $itemData = $itemResponse->json();

                        // Mettre à jour avec les vraies infos
                        $connection->update([
                            'bank_name' => $itemData['bank']['name'] ?? 'Unknown Bank',
                            'bank_logo_url' => $itemData['bank']['logo_url'] ?? null,
                            'status' => BankConnection::STATUS_ACTIVE,
                            'is_active' => true,
                            'metadata' => array_merge($connection->metadata ?? [], [
                                'bank_id' => $itemData['bank']['id'] ?? null,
                                'finalized_at' => now()->toISOString(),
                            ]),
                        ]);

                        Log::info('✅ Connexion finalisée', [
                            'connection_id' => $connection->id,
                            'bank_name' => $connection->bank_name,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('❌ Erreur finalisation connexion', [
                        'error' => $e->getMessage(),
                    ]);
                }

                // ⏱️ Attendre 2 secondes puis sync
                sleep(2);
                SyncBankTransactionsJob::dispatch($connection);

                $this->gamingService->addExperience($connection->user, 5, 'auto_sync_webhook');

            } else {
                // ❌ Erreur
                Log::error('❌ Webhook refresh error', [
                    'item_id' => $itemId,
                    'status_code' => $statusCode,
                    'status_info' => $statusInfo,
                ]);

                $connection->update([
                    'status' => BankConnection::STATUS_ERROR,
                    'last_error' => $statusInfo ?? 'Bridge refresh failed',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('❌ Erreur webhook', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtenir token Bridge
     */
    private function getBridgeToken(string $userUuid): string
    {
        $response = Http::timeout(30)
            ->retry(2, 100)
            ->withHeaders([
                'Client-Id' => config('services.bridge.client_id'),
                'Client-Secret' => config('services.bridge.client_secret'),
                'Bridge-Version' => '2025-01-15',
                'Content-Type' => 'application/json',
            ])->post(
                'https://api.bridgeapi.io/v3/aggregation/authorization/token',
                ['user_uuid' => $userUuid]
            );

        if (! $response->successful()) {
            throw new \Exception(
                'Erreur token: '.$response->body()
            );
        }

        return $response->json()['access_token'];
    }

    /**
     * En cas d'échec du job
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('❌ Job ProcessBridgeWebhook échoué', [
            'event_type' => $this->eventType,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
