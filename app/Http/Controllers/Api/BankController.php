<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncBankTransactionsJob;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Services\BankIntegrationService;
use App\Services\BudgetService;
use App\Services\GamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Contrôleur de gestion des connexions bancaires
 *
 * Gère le CRUD des connexions bancaires et l'intégration
 * avec Bridge API v3 via BankIntegrationService
 *
 * Standards École 42: ✅
 * - Fonctions ≤25 lignes
 * - Max 5 paramètres
 * - Commentaires clairs
 */
class BankController extends Controller
{
    private BankIntegrationService $bankService;

    private BudgetService $budgetService;

    private GamingService $gamingService;

    public function __construct(
        BankIntegrationService $bankService,
        BudgetService $budgetService,
        GamingService $gamingService
    ) {
        $this->bankService = $bankService;
        $this->budgetService = $budgetService;
        $this->gamingService = $gamingService;
    }

    /**
     * Liste toutes les connexions bancaires
     * GET /api/bank
     */
    public function index(): JsonResponse
    {
        try {
            $user = auth()->user();

            $connections = $user->bankConnections()
                ->with(['accounts' => function ($query) {
                    $query->where('is_active', true)
                        ->orderBy('account_type');
                }])
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->success($connections);

        } catch (\Exception $e) {
            return $this->error('Erreur récupération', $e);
        }
    }

    /**
     * Affiche une connexion spécifique
     * GET /api/bank/{id}
     */
    public function show(
        BankConnection $connection
    ): JsonResponse {

        if (! $this->canAccess($connection)) {
            return $this->forbidden();
        }

        return $this->success(
            $connection->load(['user', 'accounts'])
        );
    }

    /**
     * Initie une nouvelle connexion Bridge v3
     * POST /api/bank/initiate
     */
    public function initiate(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            Log::info('🔗 Initiation connexion Bridge', [
                'user_id' => $user->id,
            ]);

            // Utiliser BankIntegrationService existant
            $result = $this->bankService->initiateBankConnection(
                $user,
                $this->buildInitiateOptions($request)
            );

            if (! $result['success']) {
                return $this->error($result['message'] ?? 'Erreur');
            }

            return $this->success([
                'connect_url' => $result['connect_url'],
                'session_id' => $result['session_id'] ?? null,
            ], 'Connexion initiée');

        } catch (\Exception $e) {
            return $this->error('Erreur initiation', $e);
        }
    }

    /**
     * Callback après connexion Bridge (GET uniquement)
     * GET /api/bank/callback
     *
     * CRITIQUE: Doit être ultra-rapide (<200ms)
     * Pas d'appels HTTP, juste création connexion minimale
     */
    public function callback(
        Request $request
    ): \Illuminate\Http\RedirectResponse {

        Log::info('👤 Callback Bridge', [
            'query' => $request->query(),
        ]);

        $frontUrl = $this->getFrontendUrl();

        // Gestion erreur
        if ($request->has('error')) {
            return $this->redirectError(
                $frontUrl,
                $request->query('error')
            );
        }

        // Gestion annulation
        if ($request->query('success') === 'false') {
            return $this->redirectCancelled($frontUrl);
        }

        // Gestion succès
        if ($this->isSuccessCallback($request)) {
            return $this->handleSuccess($request, $frontUrl);
        }

        return redirect()->to($frontUrl.'/app/banking');
    }

    /**
     * Synchronise manuellement une connexion
     * POST /api/bank/{id}/sync
     */
    public function sync(
        BankConnection $connection
    ): JsonResponse {

        if (! $this->canAccess($connection)) {
            return $this->forbidden();
        }

        try {
            // Vérifications préalables
            $check = $this->checkSyncEligibility($connection);
            if ($check !== true) {
                return $check;
            }

            // Lancer sync asynchrone
            SyncBankTransactionsJob::dispatch($connection);

            $connection->update([
                'last_sync_at' => now(),
            ]);

            // XP bonus
            $this->gamingService->addExperience(
                $connection->user,
                10,
                'manual_sync'
            );

            return $this->success([
                'xp_gained' => 10,
            ], 'Synchronisation démarrée');

        } catch (\Exception $e) {
            return $this->error('Erreur sync', $e);
        }
    }

    /**
     * Synchronise toutes les connexions actives
     * POST /api/bank/sync-all
     */
    public function syncAll(): JsonResponse
    {
        $connections = $this->getActiveConnections();

        $result = $this->dispatchSyncJobs($connections);

        return $this->success($result,
            "{$result['synced']} connexions synchronisées"
        );
    }

    /**
     * Supprime une connexion bancaire
     * DELETE /api/bank/{id}
     */
    public function destroy(
        BankConnection $connection
    ): JsonResponse {

        if (! $this->canAccess($connection)) {
            return $this->forbidden();
        }

        try {
            $name = $connection->bank_name;
            $connection->delete();

            return $this->success(
                null,
                "Connexion $name supprimée"
            );

        } catch (\Exception $e) {
            return $this->error('Erreur suppression', $e);
        }
    }

    /**
     * Liste les transactions en attente de traitement
     * GET /api/bank/pending/transactions
     */
    public function pendingTransactions(
        Request $request
    ): JsonResponse {

        try {
            $query = $this->buildPendingQuery($request);
            $pending = $query->paginate(20);

            return $this->success([
                'transactions' => $pending->items(),
                'meta' => $this->getPaginationMeta($pending),
            ]);

        } catch (\Exception $e) {
            return $this->error('Erreur récupération', $e);
        }
    }

    /**
     * Convertit une transaction bancaire
     * POST /api/bank/transactions/{id}/convert
     */
    public function convertTransaction(
        Request $request,
        BankTransaction $bankTx
    ): JsonResponse {

        $validator = $this->validateConversion($request);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $user = auth()->user();

            $transaction = $this->createFromBank(
                $user,
                $bankTx,
                $request->all()
            );

            $this->markConverted($bankTx, $transaction);

            $this->gamingService->addExperience(
                $user,
                5,
                'process_transaction'
            );

            return $this->success([
                'transaction' => $transaction,
                'xp_gained' => 5,
            ]);

        } catch (\Exception $e) {
            return $this->error('Erreur conversion', $e);
        }
    }

    /**
     * Ignore une transaction bancaire
     * POST /api/bank/transactions/{id}/ignore
     */
    public function ignoreTransaction(
        BankTransaction $bankTx
    ): JsonResponse {

        try {
            $bankTx->update([
                'processing_status' => BankTransaction::STATUS_IGNORED,
            ]);

            return $this->success(
                null,
                'Transaction ignorée'
            );

        } catch (\Exception $e) {
            return $this->error('Erreur', $e);
        }
    }

    /**
     * Récupère les statistiques bancaires
     * GET /api/bank/stats
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = auth()->user();
            $stats = $this->calculateStats($user);

            return $this->success($stats);

        } catch (\Exception $e) {
            return $this->error('Erreur stats', $e);
        }
    }

    /**
     * Liste les providers bancaires disponibles
     * GET /api/bank/providers
     */
    public function listProviders(): JsonResponse
    {
        try {
            $providers = $this->getAvailableProviders();

            return $this->success([
                'providers' => $providers,
                'default' => 'bridge',
            ]);

        } catch (\Exception $e) {
            return $this->error('Erreur providers', $e);
        }
    }

    /**
     * Récupère les transactions d'une connexion
     * GET /api/bank/{id}/transactions
     */
    public function getTransactions(
        BankConnection $connection
    ): JsonResponse {

        if (! $this->canAccess($connection)) {
            return $this->forbidden();
        }

        $transactions = BankTransaction::where(
            'bank_connection_id',
            $connection->id
        )
            ->with('suggestedCategory')
            ->orderBy('transaction_date', 'desc')
            ->paginate(50);

        return $this->success([
            'transactions' => $transactions->items(),
            'meta' => $this->getPaginationMeta($transactions),
        ]);
    }

    /**
     * Récupère le statut des connexions
     * GET /api/bank/status
     */
    public function getStatus(): JsonResponse
    {
        try {
            $user = auth()->user();

            $status = $this->bankService
                ->getUserConnectionsStatus($user);

            return $this->success($status);

        } catch (\Exception $e) {
            return $this->error('Erreur status', $e);
        }
    }

    // ==========================================
    // MÉTHODES PRIVÉES (≤25 lignes chacune)
    // ==========================================

    /**
     * Vérifie accès à la connexion
     */
    private function canAccess(
        BankConnection $connection
    ): bool {
        return $connection->user_id === auth()->id();
    }

    /**
     * Build options pour initiate
     */
    private function buildInitiateOptions(
        Request $request
    ): array {
        $options = [
            'account_types' => 'payment',
        ];

        // Callback URL si fourni
        if ($request->has('callback_url')) {
            $options['callback_url'] = $request->callback_url;
        }

        // Provider ID si fourni
        if ($request->has('provider_id')) {
            $options['provider_id'] = $request->provider_id;
        }

        return $options;
    }

    /**
     * Vérifie si callback est succès
     */
    private function isSuccessCallback(Request $request): bool
    {
        return $request->query('success') === 'true'
            && $request->has('item_id')
            && $request->has('user_uuid');
    }

    /**
     * Gère callback succès
     */
    private function handleSuccess(
        Request $request,
        string $frontUrl
    ): \Illuminate\Http\RedirectResponse {

        try {
            $itemId = $request->query('item_id');
            $userUuid = $request->query('user_uuid');

            $user = $this->findUserByUuid($userUuid);

            if (! $user) {
                return $this->redirectError(
                    $frontUrl,
                    'user_not_found'
                );
            }

            $this->createPendingConnection(
                $user,
                $itemId
            );

            $this->gamingService->addExperience(
                $user,
                100,
                'bank_connected'
            );

            Log::info('✅ Callback succès traité', [
                'user_id' => $user->id,
                'item_id' => $itemId,
            ]);

        } catch (\Exception $e) {
            Log::error('❌ Erreur callback', [
                'error' => $e->getMessage(),
            ]);

            return $this->redirectError(
                $frontUrl,
                'setup_failed'
            );
        }

        return redirect()->to(
            $frontUrl.'/app/banking?success=true&pending=true'
        );
    }

    /**
     * Crée connexion en attente (sans appel HTTP)
     */
    private function createPendingConnection(
        $user,
        string $itemId
    ): BankConnection {

        return BankConnection::firstOrCreate(
            [
                'provider_connection_id' => $itemId,
                'user_id' => $user->id,
            ],
            [
                'provider' => 'bridge',
                'bank_name' => 'Initialisation...',
                'status' => BankConnection::STATUS_PENDING,
                'is_active' => false,
                'metadata' => [
                    'bridge_item_id' => $itemId,
                    'connection_date' => now()->toISOString(),
                ],
            ]
        );
    }

    /**
     * Trouve utilisateur par UUID Bridge
     */
    private function findUserByUuid(string $uuid)
    {
        return \App\Models\User::where(
            'bridge_user_uuid',
            $uuid
        )->first();
    }

    /**
     * Vérifie éligibilité pour sync
     */
    private function checkSyncEligibility(
        BankConnection $connection
    ) {
        if ($connection->status === 'expired') {
            return $this->error(
                '🔄 Connexion expirée. Reconnectez-vous.',
                null,
                410
            );
        }

        if ($connection->status === 'error') {
            return $this->error(
                '❌ Erreur connexion : '.
                ($connection->last_error ?? 'Inconnue'),
                null,
                400
            );
        }

        if ($connection->status !== 'active') {
            return $this->error(
                'Connexion non active',
                null,
                400
            );
        }

        if ($this->isSyncTooRecent($connection)) {
            return $this->error(
                'Sync en cours. Patientez.',
                null,
                429
            );
        }

        return true;
    }

    /**
     * Vérifie si sync trop récente
     */
    private function isSyncTooRecent(
        BankConnection $connection
    ): bool {
        if (! $connection->last_sync_at) {
            return false;
        }

        return $connection->last_sync_at
            ->diffInSeconds(now()) < 30;
    }

    /**
     * Récupère connexions actives
     */
    private function getActiveConnections()
    {
        return BankConnection::where('user_id', auth()->id())
            ->where('status', 'active')
            ->get();
    }

    /**
     * Dispatch jobs de sync
     */
    private function dispatchSyncJobs($connections): array
    {
        $synced = 0;
        $errors = [];

        foreach ($connections as $connection) {
            try {
                SyncBankTransactionsJob::dispatch(
                    $connection
                );
                $synced++;
            } catch (\Exception $e) {
                $errors[] = [
                    'connection_id' => $connection->id,
                    'bank_name' => $connection->bank_name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'synced' => $synced,
            'total' => $connections->count(),
            'errors' => $errors,
        ];
    }

    /**
     * Build query transactions en attente
     */
    private function buildPendingQuery(Request $request)
    {
        $user = auth()->user();

        $query = BankTransaction::whereHas(
            'bankConnection',
            fn ($q) => $q->where('user_id', $user->id)
        )
            ->whereIn('processing_status', [
                BankTransaction::STATUS_IMPORTED,
                BankTransaction::STATUS_CATEGORIZED,
            ])
            ->with(['bankConnection', 'suggestedCategory'])
            ->orderBy('transaction_date', 'desc');

        if ($request->has('confidence_min')) {
            $query->where(
                'confidence_score',
                '>=',
                $request->confidence_min
            );
        }

        return $query;
    }

    /**
     * Valide données de conversion
     */
    private function validateConversion(Request $request)
    {
        return Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string|max:255',
        ]);
    }

    /**
     * Crée transaction depuis bank transaction
     */
    private function createFromBank(
        $user,
        BankTransaction $bankTx,
        array $data
    ) {
        return $this->budgetService->createTransaction(
            $user,
            [
                'amount' => abs($bankTx->amount),
                'description' => $data['description']
                    ?? $bankTx->description,
                'type' => $bankTx->amount < 0
                    ? 'expense'
                    : 'income',
                'category_id' => $data['category_id'],
                'transaction_date' => $bankTx->transaction_date
                    ->format('Y-m-d'),
                'source' => 'bank_import',
            ]
        );
    }

    /**
     * Marque transaction comme convertie
     */
    private function markConverted(
        BankTransaction $bankTx,
        $transaction
    ): void {
        $bankTx->update([
            'processing_status' => BankTransaction::STATUS_CONVERTED,
            'converted_transaction_id' => $transaction->id,
        ]);
    }

    /**
     * Calcule statistiques bancaires
     */
    private function calculateStats($user): array
    {
        return [
            'connected_accounts' => $user->bankConnections()->count(),
            'active_connections' => $user->bankConnections()
                ->where('status', 'active')
                ->count(),
            'pending_transactions' => $this->countPendingTransactions($user),
            'total_imported' => $this->countImportedTransactions($user),
            'last_sync' => $this->getLastSyncDate($user),
        ];
    }

    /**
     * Compte transactions en attente
     */
    private function countPendingTransactions($user): int
    {
        return BankTransaction::whereHas(
            'bankConnection',
            fn ($q) => $q->where('user_id', $user->id)
        )
            ->whereIn('processing_status', [
                BankTransaction::STATUS_IMPORTED,
                BankTransaction::STATUS_CATEGORIZED,
            ])
            ->count();
    }

    /**
     * Compte transactions importées
     */
    private function countImportedTransactions($user): int
    {
        return BankTransaction::whereHas(
            'bankConnection',
            fn ($q) => $q->where('user_id', $user->id)
        )->count();
    }

    /**
     * Récupère dernière date sync
     */
    private function getLastSyncDate($user)
    {
        return $user->bankConnections()
            ->whereNotNull('last_sync_at')
            ->max('last_sync_at');
    }

    /**
     * Retourne providers disponibles
     */
    private function getAvailableProviders(): array
    {
        return [
            [
                'id' => 'bridge',
                'name' => 'Bridge',
                'logo' => '🌉',
                'enabled' => true,
                'countries' => ['FR', 'ES', 'IT', 'PT'],
                'status' => 'active',
            ],
        ];
    }

    /**
     * Récupère URL frontend
     */
    private function getFrontendUrl(): string
    {
        return config(
            'app.frontend_url',
            'http://localhost:5173'
        );
    }

    /**
     * Redirige vers frontend avec erreur
     */
    private function redirectError(
        string $frontUrl,
        string $error
    ): \Illuminate\Http\RedirectResponse {

        Log::error('❌ Callback erreur', [
            'error' => $error,
        ]);

        return redirect()->to(
            $frontUrl.'/app/banking?error='.
            urlencode($error)
        );
    }

    /**
     * Redirige après annulation
     */
    private function redirectCancelled(
        string $frontUrl
    ): \Illuminate\Http\RedirectResponse {

        Log::info('⚠️ Connexion annulée');

        return redirect()->to(
            $frontUrl.'/app/banking?cancelled=true'
        );
    }

    /**
     * Récupère métadonnées pagination
     */
    private function getPaginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    // ==========================================
    // HELPERS RÉPONSES JSON
    // ==========================================

    /**
     * Réponse succès
     */
    private function success(
        $data,
        ?string $message = null
    ): JsonResponse {

        $response = ['success' => true];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($message) {
            $response['message'] = $message;
        }

        return response()->json($response);
    }

    /**
     * Réponse erreur
     */
    private function error(
        string $message,
        ?\Exception $e = null,
        int $code = 500
    ): JsonResponse {

        if ($e) {
            Log::error($message, [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $message,
        ], $code);
    }

    /**
     * Réponse erreur validation
     */
    private function validationError(
        $validator
    ): JsonResponse {

        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    /**
     * Réponse accès interdit
     */
    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Accès non autorisé',
        ], 403);
    }
}
