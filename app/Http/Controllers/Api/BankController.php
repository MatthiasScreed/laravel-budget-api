<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankConnection;
use App\Models\BankTransaction;
use App\Services\BankIntegrationService;
use App\Services\GamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankController extends Controller
{
    private BankIntegrationService $bankService;
    private GamingService $gamingService;

    public function __construct(
        BankIntegrationService $bankService,
        GamingService $gamingService
    ) {
        $this->bankService = $bankService;
        $this->gamingService = $gamingService;
    }

    /**
     * Lister les connexions bancaires de l'utilisateur
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $connections = $this->bankService->getUserConnectionsStatus($user);

        return response()->json([
            'success' => true,
            'data' => $connections,
            'message' => 'Connexions bancaires récupérées'
        ]);
    }

    /**
     * Initier une nouvelle connexion bancaire
     */
    public function initiate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:bridge,budget_insight,nordigen',
            'return_url' => 'nullable|url'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();
        $result = $this->bankService->initiateBankConnection($user, $request->all());

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message']
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'connect_url' => $result['connect_url'],
                'expires_at' => $result['expires_at']
            ],
            'message' => 'Connexion bancaire initiée. Suivez le lien pour autoriser.'
        ]);
    }

    /**
     * Webhook pour finaliser la connexion (appelé par Bridge)
     */
    public function webhook(Request $request): JsonResponse
    {
        // Vérifier la signature du webhook pour sécurité
        $signature = $request->header('Bridge-Signature');
        if (!$this->verifyWebhookSignature($request->getContent(), $signature)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = $request->json()->all();

        if ($payload['type'] === 'item.created') {
            $itemId = $payload['content']['id'];
            $userId = $payload['content']['user_uuid'];

            $user = \App\Models\User::find($userId);
            if ($user) {
                $connection = $this->bankService->finalizeBankConnection($user, $itemId);

                // Notification temps réel (optionnel)
                // broadcast(new BankConnectionEstablished($user, $connection));
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Déclencher synchronisation manuelle
     */
    public function sync(BankConnection $connection): JsonResponse
    {
        $this->authorize('manage-bank-connection', $connection);

        $result = $this->bankService->syncTransactions($connection);

        if ($result['success']) {
            // XP bonus pour sync manuelle
            $this->gamingService->addXP(
                $connection->user,
                10,
                'manual_sync'
            );
        }

        return response()->json($result);
    }

    /**
     * Lister les transactions bancaires non traitées
     */
    public function pendingTransactions(): JsonResponse
    {
        $user = auth()->user();

        $pending = BankTransaction::whereHas('bankConnection', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })
            ->whereIn('processing_status', [
                BankTransaction::STATUS_IMPORTED,
                BankTransaction::STATUS_CATEGORIZED
            ])
            ->with(['bankConnection', 'suggestedCategory'])
            ->orderBy('transaction_date', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $pending,
            'message' => 'Transactions en attente récupérées'
        ]);
    }

    /**
     * Convertir une transaction bancaire en transaction utilisateur
     */
    public function convertTransaction(Request $request, BankTransaction $bankTx): JsonResponse
    {
        $this->authorize('manage-bank-transaction', $bankTx);

        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string|max:255',
            'split_amounts' => 'nullable|array', // Pour diviser une transaction
            'split_amounts.*.amount' => 'required_with:split_amounts|numeric|min:0.01',
            'split_amounts.*.category_id' => 'required_with:split_amounts|exists:categories,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth()->user();

        // Si division de transaction
        if ($request->has('split_amounts')) {
            $transactions = [];
            foreach ($request->split_amounts as $split) {
                $transaction = $this->budgetService->createTransaction($user, [
                    'amount' => $split['amount'],
                    'description' => $request->description ?? $bankTx->getFormattedDescription(),
                    'type' => $bankTx->isIncome() ? 'income' : 'expense',
                    'category_id' => $split['category_id'],
                    'transaction_date' => $bankTx->transaction_date->format('Y-m-d'),
                    'source' => 'bank_import',
                    'bank_connection_id' => $bankTx->bank_connection_id
                ]);
                $transactions[] = $transaction;
            }

            $bankTx->update(['processing_status' => BankTransaction::STATUS_CONVERTED]);

        } else {
            // Conversion simple
            $transaction = $this->budgetService->createTransaction($user, [
                'amount' => $bankTx->getAbsoluteAmount(),
                'description' => $request->description ?? $bankTx->getFormattedDescription(),
                'type' => $bankTx->isIncome() ? 'income' : 'expense',
                'category_id' => $request->category_id,
                'transaction_date' => $bankTx->transaction_date->format('Y-m-d'),
                'source' => 'bank_import',
                'bank_connection_id' => $bankTx->bank_connection_id
            ]);

            $bankTx->update([
                'processing_status' => BankTransaction::STATUS_CONVERTED,
                'converted_transaction_id' => $transaction->id
            ]);

            $transactions = [$transaction];
        }

        // XP pour traitement de transaction
        $this->gamingService->addXP($user, 5, 'process_transaction');

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'message' => 'Transaction(s) convertie(s) avec succès'
        ]);
    }

    /**
     * Ignorer une transaction bancaire
     */
    public function ignoreTransaction(BankTransaction $bankTx): JsonResponse
    {
        $this->authorize('manage-bank-transaction', $bankTx);

        $bankTx->update([
            'processing_status' => BankTransaction::STATUS_IGNORED
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Transaction ignorée'
        ]);
    }

    /**
     * Supprimer une connexion bancaire
     */
    public function destroy(BankConnection $connection): JsonResponse
    {
        $this->authorize('manage-bank-connection', $connection);

        // Révocation côté Bridge API
        try {
            Http::withHeaders([
                'Client-Id' => config('banking.bridge.client_id'),
                'Client-Secret' => config('banking.bridge.client_secret')
            ])->delete("https://api.bridgeapi.io/v2/items/{$connection->connection_id}");
        } catch (\Exception $e) {
            Log::warning('Failed to revoke Bridge connection', [
                'connection_id' => $connection->connection_id,
                'error' => $e->getMessage()
            ]);
        }

        $connection->delete();

        return response()->json([
            'success' => true,
            'message' => 'Connexion bancaire supprimée'
        ]);
    }

    /**
     * Vérifier la signature du webhook
     */
    private function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        if (!$signature) return false;

        $secret = config('banking.bridge.webhook_secret');
        $computedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($computedSignature, $signature);
    }
}
