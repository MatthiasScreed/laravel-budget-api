<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Category;
use App\Services\BudgetService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class TransactionController extends Controller
{
    use ApiResponseTrait;

    protected BudgetService $budgetService;

    public function __construct(BudgetService $budgetService)
    {
        $this->budgetService = $budgetService;
    }

    /**
     * Display a listing of transactions with advanced filters and pagination
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Auth::user()->transactions()->with(['category']);

        // Filtres avancés
        $this->applyTransactionFilters($query, $request);

        // Recherche et pagination
        $searchColumns = ['description', 'reference'];
        $transactions = $this->applyPaginationAndFilters($query, $request, $searchColumns);

        // Ajouter des métadonnées utiles
        $metadata = $this->getTransactionMetadata($request);

        return response()->json([
            'success' => true,
            'message' => 'Transactions récupérées avec succès',
            'data' => $transactions->items(),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'last_page' => $transactions->lastPage(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
                'has_more_pages' => $transactions->hasMorePages()
            ],
            'metadata' => $metadata
        ]);
    }

    /**
     * Store a newly created transaction
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:categories,id',
            'type' => 'required|in:income,expense',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
            'transaction_date' => 'required|date',
            'payment_method' => 'nullable|string|max:50',
            'reference' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $user = $request->user();

        try {
            // 🎮 UTILISER LE BUDGET SERVICE POUR L'XP ET GAMING
            $transaction = $this->budgetService->createTransaction($user, $data);

            // Calculer l'XP gagné pour l'afficher
            $xpGained = $this->budgetService->calculateTransactionXp($transaction);

            // Stats gaming mises à jour
            $gamingStats = $user->fresh()->getGamingStats();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $transaction->id,
                    'amount' => $transaction->amount,
                    'description' => $transaction->description,
                    'type' => $transaction->type,
                    'transaction_date' => $transaction->transaction_date,
                    'status' => $transaction->status,
                    'category' => [
                        'id' => $transaction->category->id,
                        'name' => $transaction->category->name,
                        'type' => $transaction->category->type,
                    ],
                    // 🎮 DONNÉES GAMING AJOUTÉES
                    'gaming' => [
                        'xp_gained' => $xpGained,
                        'total_xp' => $gamingStats['level_info']['total_xp'],
                        'current_level' => $gamingStats['level_info']['current_level']
                    ]
                ],
                'message' => 'Transaction créée avec succès (+' . $xpGained . ' XP!)'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified transaction
     *
     * @param Transaction $transaction
     * @return JsonResponse
     */
    public function show(Transaction $transaction): JsonResponse
    {
        if (!$this->userOwnsTransaction($transaction)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cette transaction');
        }

        $transaction->load(['category', 'tags', 'goalContributions.financialGoal']);

        return $this->successResponse($transaction, 'Transaction récupérée avec succès');
    }

    /**
     * Update the specified transaction
     *
     * @param Request $request
     * @param Transaction $transaction
     * @return JsonResponse
     */
    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        if (!$this->userOwnsTransaction($transaction)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cette transaction');
        }

        $data = $request->validate([
            'category_id' => 'exists:categories,id',
            'type' => 'in:income,expense',
            'amount' => 'numeric|min:0.01',
            'description' => 'string|max:255',
            'transaction_date' => 'date',
            'payment_method' => 'nullable|string|max:100',
            'reference' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50'
        ]);

        // Vérifier la catégorie si changée
        if (isset($data['category_id'])) {
            $category = Category::where('id', $data['category_id'])
                ->where('user_id', Auth::id())
                ->first();

            if (!$category) {
                return $this->notFoundResponse('Catégorie non trouvée');
            }

            if (isset($data['type']) && $data['type'] !== $category->type) {
                return $this->errorResponse('Le type de transaction ne correspond pas au type de catégorie');
            }
        }

        try {
            DB::beginTransaction();

            $transaction->update($data);

            // Mettre à jour les tags si fournis
            if (isset($data['tags'])) {
                $transaction->syncTags($data['tags']);
            }

            DB::commit();

            $transaction->load(['category', 'tags']);

            return $this->updatedResponse($transaction, 'Transaction mise à jour avec succès');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Erreur lors de la mise à jour: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Remove the specified transaction
     *
     * @param Transaction $transaction
     * @return JsonResponse
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        if (!$this->userOwnsTransaction($transaction)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cette transaction');
        }

        try {
            DB::beginTransaction();

            // Supprimer les contributions aux objectifs liées
            $transaction->goalContributions()->delete();

            // Supprimer les tags
            $transaction->tags()->detach();

            $transaction->delete();

            DB::commit();

            return $this->deletedResponse('Transaction supprimée avec succès');

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Erreur lors de la suppression: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get transactions by category
     *
     * @param Request $request
     * @param Category $category
     * @return JsonResponse
     */
    public function getByCategory(Request $request, Category $category): JsonResponse
    {
        if (!$this->userOwnsCategory($category)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cette catégorie');
        }

        $query = $category->transactions();

        $this->applyTransactionFilters($query, $request);

        $transactions = $this->applyPaginationAndFilters($query, $request, ['description', 'reference']);

        return $this->paginatedResponse($transactions, "Transactions de la catégorie '{$category->name}' récupérées avec succès");
    }

    /**
     * Get transaction statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = Auth::user();
        $period = $request->get('period', 'month'); // month, year, all

        $query = $user->transactions();

        // Appliquer la période
        switch ($period) {
            case 'month':
                $query->whereMonth('transaction_date', now()->month)
                    ->whereYear('transaction_date', now()->year);
                break;
            case 'year':
                $query->whereYear('transaction_date', now()->year);
                break;
            // 'all' = pas de filtre
        }

        $stats = [
            'total_transactions' => $query->count(),
            'total_income' => $query->where('type', 'income')->sum('amount'),
            'total_expenses' => $query->where('type', 'expense')->sum('amount'),
            'average_transaction' => round($query->avg('amount'), 2),
            'largest_transaction' => $query->max('amount'),
            'smallest_transaction' => $query->min('amount'),
            'by_category' => $user->categories()
                ->withSum(['transactions' => function ($q) use ($period) {
                    $this->applyPeriodFilter($q, $period);
                }], 'amount')
                ->withCount(['transactions' => function ($q) use ($period) {
                    $this->applyPeriodFilter($q, $period);
                }])
                ->get(['id', 'name', 'type'])
                ->map(function ($category) {
                    return [
                        'category' => $category->only(['id', 'name', 'type']),
                        'total_amount' => $category->transactions_sum_amount ?? 0,
                        'transactions_count' => $category->transactions_count ?? 0
                    ];
                }),
            'by_month' => $user->transactions()
                ->selectRaw('YEAR(transaction_date) as year, MONTH(transaction_date) as month, type, SUM(amount) as total')
                ->groupBy('year', 'month', 'type')
                ->orderBy('year', 'desc')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get()
        ];

        $stats['balance'] = $stats['total_income'] - $stats['total_expenses'];

        return $this->successResponse($stats, 'Statistiques des transactions récupérées avec succès');
    }

    /**
     * Apply transaction-specific filters
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     */
    private function applyTransactionFilters($query, Request $request): void
    {
        // Filtre par type
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Filtre par catégorie
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtres de dates
        if ($request->filled('date_from')) {
            $query->whereDate('transaction_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('transaction_date', '<=', $request->date_to);
        }

        // Filtres de montants
        if ($request->filled('min_amount')) {
            $query->where('amount', '>=', $request->min_amount);
        }

        if ($request->filled('max_amount')) {
            $query->where('amount', '<=', $request->max_amount);
        }

        // Filtre par méthode de paiement
        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Filtre par statut
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtre par tags
        if ($request->filled('tags')) {
            $tags = is_array($request->tags) ? $request->tags : [$request->tags];
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('name', $tags);
            });
        }
    }

    /**
     * Get transaction metadata for the current filters
     *
     * @param Request $request
     * @return array
     */
    private function getTransactionMetadata(Request $request): array
    {
        $user = Auth::user();
        $baseQuery = $user->transactions();

        $this->applyTransactionFilters($baseQuery, $request);

        return [
            'total_amount' => $baseQuery->sum('amount'),
            'total_income' => $baseQuery->where('type', 'income')->sum('amount'),
            'total_expenses' => $baseQuery->where('type', 'expense')->sum('amount'),
            'average_amount' => round($baseQuery->avg('amount'), 2),
            'categories_used' => $baseQuery->distinct('category_id')->count(),
            'date_range' => [
                'earliest' => $baseQuery->min('transaction_date'),
                'latest' => $baseQuery->max('transaction_date')
            ]
        ];
    }

    /**
     * Apply period filter to query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $period
     */
    private function applyPeriodFilter($query, string $period): void
    {
        switch ($period) {
            case 'month':
                $query->whereMonth('transaction_date', now()->month)
                    ->whereYear('transaction_date', now()->year);
                break;
            case 'year':
                $query->whereYear('transaction_date', now()->year);
                break;
        }
    }

    /**
     * Check if the authenticated user owns the transaction
     *
     * @param Transaction $transaction
     * @return bool
     */
    private function userOwnsTransaction(Transaction $transaction): bool
    {
        return $transaction->user_id === Auth::id();
    }

    /**
     * Check if the authenticated user owns the category
     *
     * @param Category $category
     * @return bool
     */
    private function userOwnsCategory(Category $category): bool
    {
        return $category->user_id === Auth::id();
    }
}
