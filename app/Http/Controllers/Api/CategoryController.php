<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    use ApiResponseTrait;

    /**
     * Display a listing of categories with pagination and filters
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Auth::user()->categories();

        // Recherche et filtres
        $searchColumns = ['name'];
        $filterableColumns = ['type'];

        $categories = $this->applyPaginationAndFilters($query, $request, $searchColumns, $filterableColumns);

        return $this->paginatedResponse($categories, 'Catégories récupérées avec succès');
    }

    /**
     * Store a newly created category
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories')->where(function ($query) {
                    return $query->where('user_id', Auth::id());
                })
            ],
            'type' => 'required|in:income,expense',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean'
        ]);

        $data['user_id'] = Auth::id();
        $data['is_active'] = $data['is_active'] ?? true;

        $category = Category::create($data);

        // Déclencher événement gaming pour création de catégorie
        event(new \App\Events\CategoryCreated(Auth::user(), $category));

        return $this->createdResponse($category, 'Catégorie créée avec succès');
    }

    /**
     * Display the specified category
     *
     * @param Category $category
     * @return JsonResponse
     */
    public function show(Category $category): JsonResponse
    {
        if (!$this->userOwnsCategory($category)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cette catégorie');
        }

        // Charger les relations utiles
        $category->load(['transactions' => function ($query) {
            $query->latest()->limit(5);
        }]);

        // Ajouter des statistiques
        $categoryWithStats = $category->toArray();
        $categoryWithStats['stats'] = [
            'transactions_count' => $category->transactions()->count(),
            'total_amount' => $category->transactions()->sum('amount'),
            'avg_amount' => round($category->transactions()->avg('amount'), 2),
            'last_transaction_date' => $category->transactions()->latest('transaction_date')->value('transaction_date')
        ];

        return $this->successResponse($categoryWithStats, 'Catégorie récupérée avec succès');
    }

    /**
     * Update the specified category
     *
     * @param Request $request
     * @param Category $category
     * @return JsonResponse
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        if (!$this->userOwnsCategory($category)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cette catégorie');
        }

        $data = $request->validate([
            'name' => [
                'string',
                'max:255',
                Rule::unique('categories')->where(function ($query) use ($category) {
                    return $query->where('user_id', Auth::id())
                        ->where('id', '!=', $category->id);
                })
            ],
            'type' => 'in:income,expense',
            'icon' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'description' => 'nullable|string|max:500',
            'is_active' => 'boolean'
        ]);

        $category->update($data);

        return $this->updatedResponse($category, 'Catégorie mise à jour avec succès');
    }

    /**
     * Remove the specified category
     *
     * @param Category $category
     * @return JsonResponse
     */
    public function destroy(Category $category): JsonResponse
    {
        if (!$this->userOwnsCategory($category)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cette catégorie');
        }

        // Vérifier si la catégorie a des transactions
        $transactionsCount = $category->transactions()->count();

        if ($transactionsCount > 0) {
            return $this->errorResponse(
                "Impossible de supprimer cette catégorie. Elle contient {$transactionsCount} transaction(s).",
                400
            );
        }

        $category->delete();

        return $this->deletedResponse('Catégorie supprimée avec succès');
    }

    /**
     * Get categories statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = Auth::user();

        $stats = [
            'total_categories' => $user->categories()->count(),
            'income_categories' => $user->categories()->where('type', 'income')->count(),
            'expense_categories' => $user->categories()->where('type', 'expense')->count(),
            'active_categories' => $user->categories()->where('is_active', true)->count(),
            'most_used_categories' => $user->categories()
                ->withCount('transactions')
                ->orderBy('transactions_count', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'type', 'transactions_count']),
            'categories_by_amount' => $user->categories()
                ->with(['transactions' => function ($query) {
                    $query->selectRaw('category_id, SUM(amount) as total_amount')
                        ->groupBy('category_id');
                }])
                ->get()
                ->map(function ($category) {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'type' => $category->type,
                        'total_amount' => $category->transactions->sum('amount')
                    ];
                })
                ->sortByDesc('total_amount')
                ->take(5)
                ->values()
        ];

        return $this->successResponse($stats, 'Statistiques des catégories récupérées avec succès');
    }

    /**
     * Archive/Unarchive a category
     *
     * @param Category $category
     * @return JsonResponse
     */
    public function toggleActive(Category $category): JsonResponse
    {
        if (!$this->userOwnsCategory($category)) {
            return $this->unauthorizedResponse('Vous n\'avez pas accès à cette catégorie');
        }

        $category->update(['is_active' => !$category->is_active]);

        $status = $category->is_active ? 'activée' : 'archivée';

        return $this->updatedResponse($category, "Catégorie {$status} avec succès");
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
