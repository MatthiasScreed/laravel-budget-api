<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\BankIntegrationService;
use App\Services\TransactionCategorizationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Transaction Controller - VERSION OPTIMISÉE
 *
 * Fonctionnalités :
 * - CRUD transactions
 * - Catégorisation intelligente avec apprentissage
 * - Synchronisation Bridge API avec batch processing
 * - Export CSV
 * - Actions en masse
 */
class TransactionController extends Controller
{
    protected TransactionCategorizationService $categorizationService;

    protected BankIntegrationService $bankService;

    public function __construct(
        TransactionCategorizationService $categorizationService,
        BankIntegrationService $bankService
    ) {
        $this->categorizationService = $categorizationService;
        $this->bankService = $bankService;
    }

    // ==========================================
    // CRUD BASIQUE
    // ==========================================

    /**
     * Liste des transactions avec filtres et pagination
     */
    public function index(Request $request)
    {
        $query = Transaction::with(['category', 'bankConnection'])
            ->where('user_id', auth()->id());

        // Filtres
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('date_from')) {
            $query->where('transaction_date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('transaction_date', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $query->where('description', 'LIKE', '%'.$request->search.'%');
        }

        if ($request->has('is_recurring')) {
            $query->where('is_recurring', $request->is_recurring);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Tri
        $sortBy = $request->get('sort_by', 'transaction_date');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions->items(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
                'has_more_pages' => $transactions->hasMorePages(),
            ],
        ]);
    }

    /**
     * Créer une transaction
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'transaction_date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
            'is_recurring' => 'boolean',
            'recurrence_frequency' => 'nullable|in:daily,weekly,monthly,yearly',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = Transaction::create([
            'user_id' => auth()->id(),
            'type' => $request->type,
            'amount' => $request->amount,
            'description' => $request->description,
            'transaction_date' => $request->transaction_date,
            'category_id' => $request->category_id,
            'is_recurring' => $request->get('is_recurring', false),
            'recurrence_frequency' => $request->recurrence_frequency,
            'tags' => $request->tags,
            'status' => 'completed',
        ]);

        // Gaming: Attribuer XP
        $xp = $request->type === 'income' ? 15 : 10;

        if (method_exists(auth()->user(), 'addXp')) {
            auth()->user()->addXp($xp, 'transaction_created');
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction créée avec succès',
            'data' => $transaction->load('category'),
            'xp_gained' => $xp,
        ], 201);
    }

    /**
     * Afficher une transaction
     */
    public function show($id)
    {
        $transaction = Transaction::with(['category', 'bankConnection'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $transaction,
        ]);
    }

    /**
     * Mettre à jour une transaction
     */
    public function update(Request $request, $id)
    {
        $transaction = Transaction::where('user_id', auth()->id())
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|in:income,expense',
            'amount' => 'sometimes|numeric|min:0.01',
            'description' => 'sometimes|string|max:255',
            'transaction_date' => 'sometimes|date',
            'category_id' => 'nullable|exists:categories,id',
            'is_recurring' => 'boolean',
            'tags' => 'nullable|array',
            'status' => 'sometimes|in:pending,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        // ✅ Détecter si la catégorie a changé (apprentissage)
        $categoryChanged = $request->has('category_id') &&
            $request->category_id != $transaction->category_id;

        $transaction->update($request->all());

        // ✅ APPRENTISSAGE : Si catégorie changée manuellement
        if ($categoryChanged && $request->category_id) {
            try {
                $this->categorizationService->learnFromCorrection($transaction);

                Log::info('✅ Apprentissage effectué', [
                    'transaction_id' => $transaction->id,
                    'new_category' => $request->category_id,
                ]);
            } catch (\Exception $e) {
                Log::error('❌ Erreur apprentissage', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction mise à jour',
            'data' => $transaction->load('category'),
        ]);
    }

    /**
     * Supprimer une transaction
     */
    public function destroy($id)
    {
        $transaction = Transaction::where('user_id', auth()->id())
            ->findOrFail($id);

        $transaction->delete();

        return response()->json([
            'success' => true,
            'message' => 'Transaction supprimée',
        ]);
    }

    // ==========================================
    // SYNCHRONISATION BRIDGE API
    // ==========================================

    /**
     * ✅ Synchroniser les transactions depuis Bridge
     */
    public function sync(Request $request)
    {
        try {
            $batch = $this->bankService->syncTransactions($request->user());

            return response()->json([
                'success' => true,
                'message' => 'Synchronisation lancée',
                'batch_id' => $batch->id,
                'status' => 'processing',
                'total_jobs' => $batch->totalJobs,
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur sync', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * ✅ Vérifier le statut d'une synchronisation
     */
    public function syncStatus(string $batchId)
    {
        $status = $this->bankService->getBatchStatus($batchId);

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    // ==========================================
    // CATÉGORISATION INTELLIGENTE
    // ==========================================

    /**
     * ✅ Catégoriser une transaction manuellement
     * AVEC APPRENTISSAGE AUTOMATIQUE
     */
    public function categorize(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $transaction = Transaction::where('user_id', auth()->id())
            ->findOrFail($id);

        $transaction->update([
            'category_id' => $request->category_id,
            'status' => 'completed',
        ]);

        // ✅ APPRENTISSAGE : Apprendre de cette correction
        try {
            $this->categorizationService->learnFromCorrection($transaction);

            Log::info('✅ Pattern utilisateur enregistré', [
                'transaction_id' => $transaction->id,
                'category_id' => $request->category_id,
                'description' => $transaction->description,
            ]);
        } catch (\Exception $e) {
            Log::error('❌ Erreur apprentissage', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Gaming: XP pour catégorisation
        $xp = 0;
        if (method_exists(auth()->user(), 'addXp')) {
            $xp = 5;
            auth()->user()->addXp($xp, 'transaction_categorized');
        }

        return response()->json([
            'success' => true,
            'message' => 'Transaction catégorisée et pattern enregistré',
            'data' => $transaction->load('category'),
            'xp_gained' => $xp,
        ]);
    }

    /**
     * ✅ Catégorisation automatique (IA) - UNE transaction
     */
    public function autoCategorize($id)
    {
        $transaction = Transaction::where('user_id', auth()->id())
            ->findOrFail($id);

        if ($transaction->category_id) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction déjà catégorisée',
            ], 400);
        }

        $category = $this->categorizationService->categorize($transaction);

        if ($category) {
            $transaction->update([
                'category_id' => $category->id,
                'status' => 'completed',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction catégorisée automatiquement',
                'data' => $transaction->load('category'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Impossible de catégoriser automatiquement',
        ], 400);
    }

    /**
     * ✅ Catégoriser toutes les transactions en attente
     */
    public function autoCategorizeAll()
    {
        $uncategorized = Transaction::where('user_id', auth()->id())
            ->whereNull('category_id')
            ->get();

        if ($uncategorized->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Aucune transaction à catégoriser',
                'data' => [
                    'categorized' => 0,
                    'total' => 0,
                ],
                'xp_gained' => 0,
            ]);
        }

        $categorized = 0;

        foreach ($uncategorized as $transaction) {
            try {
                $category = $this->categorizationService->categorize($transaction);

                if ($category) {
                    $transaction->update([
                        'category_id' => $category->id,
                        'status' => 'completed',
                    ]);
                    $categorized++;
                }
            } catch (\Exception $e) {
                Log::error('❌ Erreur catégorisation', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Gaming: XP pour auto-catégorisation
        $xpGained = 0;
        if ($categorized > 0 && method_exists(auth()->user(), 'addXp')) {
            $xpGained = $categorized * 3;
            auth()->user()->addXp($xpGained, 'auto_categorization');
        }

        return response()->json([
            'success' => true,
            'message' => "$categorized transaction(s) catégorisée(s)",
            'data' => [
                'categorized' => $categorized,
                'total' => $uncategorized->count(),
                'failed' => $uncategorized->count() - $categorized,
            ],
            'xp_gained' => $xpGained,
        ]);
    }

    /**
     * ✅ Obtenir suggestions de catégorisation
     */
    public function suggestions(Transaction $transaction)
    {
        // Vérifier que la transaction appartient à l'utilisateur
        if ($transaction->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Accès non autorisé',
            ], 403);
        }

        $suggestions = $this->categorizationService->getSuggestions($transaction);

        return response()->json([
            'success' => true,
            'data' => [
                'suggestions' => $suggestions,
            ],
        ]);
    }

    /**
     * ✅ Suggérer une catégorie pour une nouvelle transaction
     */
    public function suggestCategory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Créer une transaction temporaire pour l'analyse
        $tempTransaction = new Transaction([
            'user_id' => auth()->id(),
            'description' => $request->description,
            'amount' => abs($request->amount),
            'type' => $request->amount < 0 ? 'expense' : 'income',
            'transaction_date' => now(),
        ]);

        // Obtenir suggestions via le service
        $suggestions = $this->categorizationService->getSuggestions($tempTransaction);

        return response()->json([
            'success' => true,
            'data' => [
                'suggestions' => $suggestions,
            ],
        ]);
    }

    /**
     * ✅ Analyser qualité de catégorisation
     */
    public function quality(Request $request)
    {
        $quality = $this->categorizationService->analyzeQuality($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => $quality,
        ]);
    }

    // ==========================================
    // STATISTIQUES
    // ==========================================

    /**
     * Statistiques globales (avec cache)
     */
    public function stats(Request $request)
    {
        $userId = auth()->id();
        $cacheKey = "user_stats:{$userId}";

        // Cache de 5 minutes
        $stats = Cache::remember($cacheKey, 300, function () use ($userId) {
            // Stats globales
            $data = [
                'total_transactions' => Transaction::where('user_id', $userId)->count(),
                'total_income' => Transaction::where('user_id', $userId)
                    ->where('type', 'income')
                    ->where('status', 'completed')
                    ->sum('amount'),
                'total_expenses' => Transaction::where('user_id', $userId)
                    ->where('type', 'expense')
                    ->where('status', 'completed')
                    ->sum('amount'),
            ];

            $data['balance'] = $data['total_income'] - $data['total_expenses'];

            // Stats du mois en cours
            $currentMonth = now()->startOfMonth();

            $data['monthly_income'] = Transaction::where('user_id', $userId)
                ->where('type', 'income')
                ->where('status', 'completed')
                ->where('transaction_date', '>=', $currentMonth)
                ->sum('amount');

            $data['monthly_expenses'] = Transaction::where('user_id', $userId)
                ->where('type', 'expense')
                ->where('status', 'completed')
                ->where('transaction_date', '>=', $currentMonth)
                ->sum('amount');

            $data['monthly_balance'] = $data['monthly_income'] - $data['monthly_expenses'];

            // Stats des transactions en attente
            $data['pending_count'] = Transaction::where('user_id', $userId)
                ->whereNull('category_id')
                ->count();

            // Catégories les plus utilisées
            $data['top_categories'] = Transaction::where('user_id', $userId)
                ->whereNotNull('category_id')
                ->with('category')
                ->select('category_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
                ->groupBy('category_id')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'category' => $item->category,
                        'count' => $item->count,
                        'total' => $item->total,
                    ];
                });

            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Transactions en attente de catégorisation
     */
    public function pending()
    {
        $pending = Transaction::with('bankConnection')
            ->where('user_id', auth()->id())
            ->whereNull('category_id')
            ->orderBy('transaction_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $pending,
            'count' => $pending->count(),
        ]);
    }

    // ==========================================
    // RECHERCHE ET EXPORT
    // ==========================================

    /**
     * Recherche de transactions
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = $request->query;

        $transactions = Transaction::with(['category', 'bankConnection'])
            ->where('user_id', auth()->id())
            ->where(function ($q) use ($query) {
                $q->where('description', 'LIKE', "%{$query}%")
                    ->orWhere('reference', 'LIKE', "%{$query}%")
                    ->orWhereHas('category', function ($cat) use ($query) {
                        $cat->where('name', 'LIKE', "%{$query}%");
                    });
            })
            ->orderBy('transaction_date', 'desc')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'count' => $transactions->count(),
        ]);
    }

    /**
     * Export CSV
     */
    public function exportCsv(Request $request)
    {
        $transactions = Transaction::with('category')
            ->where('user_id', auth()->id())
            ->orderBy('transaction_date', 'desc')
            ->get();

        $filename = 'transactions_'.now()->format('Y-m-d').'.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function () use ($transactions) {
            $file = fopen('php://output', 'w');

            // En-têtes
            fputcsv($file, [
                'Date', 'Type', 'Montant', 'Description',
                'Catégorie', 'Récurrente', 'Statut',
            ]);

            // Données
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->transaction_date,
                    $transaction->type,
                    $transaction->amount,
                    $transaction->description,
                    $transaction->category?->name ?? 'Non catégorisée',
                    $transaction->is_recurring ? 'Oui' : 'Non',
                    $transaction->status,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    // ==========================================
    // ACTIONS EN MASSE
    // ==========================================

    /**
     * Actions en masse - Catégoriser
     */
    public function bulkCategorize(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
            'category_id' => 'required|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $transactions = Transaction::where('user_id', auth()->id())
            ->whereIn('id', $request->transaction_ids)
            ->get();

        $updated = 0;

        foreach ($transactions as $transaction) {
            $transaction->update([
                'category_id' => $request->category_id,
                'status' => 'completed',
            ]);

            // ✅ APPRENTISSAGE pour chaque transaction
            try {
                $this->categorizationService->learnFromCorrection($transaction);
            } catch (\Exception $e) {
                Log::warning('Erreur apprentissage bulk', [
                    'transaction_id' => $transaction->id,
                ]);
            }

            $updated++;
        }

        // Gaming: XP
        $xpGained = 0;
        if ($updated > 0 && method_exists(auth()->user(), 'addXp')) {
            $xpGained = $updated * 3;
            auth()->user()->addXp($xpGained, 'bulk_categorization');
        }

        return response()->json([
            'success' => true,
            'message' => "$updated transaction(s) catégorisée(s)",
            'data' => ['updated' => $updated],
            'xp_gained' => $xpGained,
        ]);
    }

    /**
     * Actions en masse - Supprimer
     */
    public function bulkDelete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $deleted = Transaction::where('user_id', auth()->id())
            ->whereIn('id', $request->transaction_ids)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "$deleted transaction(s) supprimée(s)",
            'data' => ['deleted' => $deleted],
        ]);
    }

    /**
     * Actions en masse - Rendre récurrente
     */
    public function bulkRecurring(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
            'is_recurring' => 'required|boolean',
            'recurrence_frequency' => 'required_if:is_recurring,true|in:daily,weekly,monthly,yearly',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = ['is_recurring' => $request->is_recurring];

        if ($request->is_recurring) {
            $updateData['recurrence_frequency'] = $request->recurrence_frequency;
        }

        $updated = Transaction::where('user_id', auth()->id())
            ->whereIn('id', $request->transaction_ids)
            ->update($updateData);

        return response()->json([
            'success' => true,
            'message' => "$updated transaction(s) mise(s) à jour",
            'data' => ['updated' => $updated],
        ]);
    }
}
