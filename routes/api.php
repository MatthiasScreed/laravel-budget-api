<?php

use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\FinancialGoalController;
use App\Http\Controllers\Api\GoalContributionController;
use App\Http\Controllers\Api\StreakController;
use App\Http\Controllers\Api\SuggestionController;
use App\Http\Controllers\Api\TransactionController;

// ==========================================
// GAMING CONTROLLERS
// ==========================================
use App\Http\Controllers\Api\GamingController;
use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\UserLevelController;
use App\Http\Controllers\Api\GamingActionController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ==========================================
// ROUTES PUBLIQUES
// ==========================================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// ==========================================
// ROUTES PROTÉGÉES
// ==========================================
Route::middleware('auth:sanctum')->group(function () {

    // ==========================================
    // AUTHENTIFICATION ET PROFIL
    // ==========================================
    Route::prefix('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/upload-avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('/delete-account', [AuthController::class, 'deleteAccount']);

        // Gestion des sessions
        Route::get('/sessions', [AuthController::class, 'sessions']);
        Route::delete('/sessions/{sessionId}', [AuthController::class, 'revokeSession']);
    });

    // ==========================================
    // RESOURCES FINANCIÈRES AVEC PAGINATION
    // ==========================================
    Route::apiResource('transactions', TransactionController::class);
    Route::get('transactions/{transaction}/statistics', [TransactionController::class, 'statistics']);

    Route::apiResource('financial-goals', FinancialGoalController::class);
    Route::post('financial-goals/{financialGoal}/contributions', [FinancialGoalController::class, 'addContribution']);
    Route::get('financial-goals/statistics', [FinancialGoalController::class, 'statistics']);
    Route::patch('financial-goals/{financialGoal}/toggle-status', [FinancialGoalController::class, 'toggleStatus']);

    Route::apiResource('categories', CategoryController::class);
    Route::get('categories/statistics', [CategoryController::class, 'statistics']);
    Route::patch('categories/{category}/toggle-active', [CategoryController::class, 'toggleActive']);

    Route::apiResource('suggestions', SuggestionController::class);
    Route::apiResource('goal-contributions', GoalContributionController::class);

    // Routes additionnelles spécifiques
    Route::get('financial-goals/{goal}/contributions', [GoalContributionController::class, 'getByGoal']);
    Route::get('categories/{category}/transactions', [TransactionController::class, 'getByCategory']);

    // ==========================================
    // ANALYTICS ET RAPPORTS
    // ==========================================
    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('/dashboard', [AnalyticsController::class, 'dashboard']);
        Route::get('/monthly-report', [AnalyticsController::class, 'monthlyReport']);
        Route::get('/yearly-report', [AnalyticsController::class, 'yearlyReport']);
        Route::get('/category-breakdown', [AnalyticsController::class, 'categoryBreakdown']);
        Route::get('/spending-trends', [AnalyticsController::class, 'spendingTrends']);
        Route::get('/budget-analysis', [AnalyticsController::class, 'budgetAnalysis']);
    });

    // ==========================================
    // DASHBOARD GÉNÉRAL AMÉLIORÉ
    // ==========================================
    Route::get('dashboard/stats', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'financial' => [
                    'balance' => $user->transactions()->selectRaw('
                        SUM(CASE WHEN type = "income" THEN amount ELSE -amount END) as balance
                    ')->value('balance') ?? 0,
                    'monthly_income' => $user->transactions()
                        ->where('type', 'income')
                        ->whereMonth('transaction_date', now()->month)
                        ->sum('amount'),
                    'monthly_expenses' => $user->transactions()
                        ->where('type', 'expense')
                        ->whereMonth('transaction_date', now()->month)
                        ->sum('amount'),
                    'total_transactions' => $user->transactions()->count(),
                ],
                'goals' => [
                    'total_goals' => $user->financialGoals()->count(),
                    'active_goals' => $user->financialGoals()->where('status', 'active')->count(),
                    'completed_goals' => $user->financialGoals()->where('status', 'completed')->count(),
                    'total_saved' => $user->financialGoals()->sum('current_amount'),
                    'total_target' => $user->financialGoals()->sum('target_amount'),
                ],
                'gaming' => [
                    'level' => $user->level?->level ?? 1,
                    'total_xp' => $user->level?->total_xp ?? 0,
                    'achievements_count' => $user->achievements()->count(),
                    'active_streaks' => $user->streaks()->where('is_active', true)->count(),
                ],
                'recent_activity' => [
                    'recent_transactions' => $user->transactions()
                        ->with('category')
                        ->latest()
                        ->limit(5)
                        ->get(),
                    'recent_achievements' => $user->achievements()
                        ->wherePivot('unlocked_at', '>=', now()->subDays(7))
                        ->latest('user_achievements.unlocked_at')
                        ->limit(3)
                        ->get(),
                ]
            ],
            'message' => 'Dashboard récupéré avec succès'
        ]);
    });

    // ==========================================
    // SYSTÈME GAMING
    // ==========================================
    Route::prefix('gaming')->name('gaming.')->group(function () {
        // Stats et Dashboard Gaming
        Route::get('/stats', [GamingController::class, 'stats']);
        Route::get('/dashboard', [GamingController::class, 'dashboard']);
        Route::post('/check-achievements', [GamingController::class, 'checkAchievements']);

        // Achievements (Succès)
        Route::prefix('achievements')->name('achievements.')->group(function () {
            Route::get('/', [AchievementController::class, 'index']);
            Route::get('/available', [AchievementController::class, 'available']);
            Route::get('/unlocked', [AchievementController::class, 'unlocked']);
            Route::get('/{achievement}', [AchievementController::class, 'show']);
            Route::post('/check', [AchievementController::class, 'checkAndUnlock']);
        });

        // Niveaux et XP
        Route::prefix('level')->name('level.')->group(function () {
            Route::get('/', [UserLevelController::class, 'show']);
            Route::get('/progress', [UserLevelController::class, 'progress']);
            Route::get('/leaderboard', [UserLevelController::class, 'leaderboard']);
        });

        // Actions utilisateur (pour déclencher XP/achievements)
        Route::prefix('actions')->name('actions.')->group(function () {
            Route::post('/transaction-created', [GamingActionController::class, 'transactionCreated']);
            Route::post('/goal-achieved', [GamingActionController::class, 'goalAchieved']);
            Route::post('/category-created', [GamingActionController::class, 'categoryCreated']);
            Route::post('/add-xp', [GamingActionController::class, 'addXp']);
        });
    });

    // ==========================================
    // STREAKS ROUTES AVEC PAGINATION
    // ==========================================
    Route::prefix('streaks')->name('streaks.')->group(function () {
        Route::get('/', [StreakController::class, 'index']);
        Route::get('/leaderboard', [StreakController::class, 'leaderboard']);
        Route::get('/check-expired', [StreakController::class, 'checkExpired']);
        Route::get('/{type}', [StreakController::class, 'show']);
        Route::post('/{type}/trigger', [StreakController::class, 'trigger']);
        Route::post('/{type}/claim-bonus', [StreakController::class, 'claimBonus']);
        Route::post('/{type}/reactivate', [StreakController::class, 'reactivate']);
    });

    // ==========================================
    // ROUTES DE RECHERCHE AVANCÉE
    // ==========================================
    Route::prefix('search')->group(function () {
        Route::get('/transactions', function (Request $request) {
            $query = Auth::user()->transactions()->with('category');

            // Recherche globale
            if ($request->filled('q')) {
                $searchTerm = $request->q;
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('description', 'like', "%{$searchTerm}%")
                        ->orWhere('reference', 'like', "%{$searchTerm}%")
                        ->orWhereHas('category', function ($categoryQuery) use ($searchTerm) {
                            $categoryQuery->where('name', 'like', "%{$searchTerm}%");
                        });
                });
            }

            $results = $query->latest()->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $results->items(),
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                ],
                'message' => 'Recherche de transactions effectuée'
            ]);
        });

        Route::get('/categories', function (Request $request) {
            $query = Auth::user()->categories();

            if ($request->filled('q')) {
                $query->where('name', 'like', "%{$request->q}%");
            }

            $results = $query->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $results->items(),
                'pagination' => [
                    'current_page' => $results->currentPage(),
                    'per_page' => $results->perPage(),
                    'total' => $results->total(),
                    'last_page' => $results->lastPage(),
                ],
                'message' => 'Recherche de catégories effectuée'
            ]);
        });
    });

    // ==========================================
    // EXPORT DE DONNÉES
    // ==========================================
    Route::prefix('export')->group(function () {
        Route::get('/transactions', function (Request $request) {
            // Cette route nécessitera Laravel Excel
            return response()->json([
                'success' => false,
                'message' => 'Export de transactions - À implémenter avec Laravel Excel'
            ]);
        });

        Route::get('/goals', function (Request $request) {
            return response()->json([
                'success' => false,
                'message' => 'Export d\'objectifs - À implémenter avec Laravel Excel'
            ]);
        });
    });

    // ==========================================
    // NOTIFICATIONS
    // ==========================================
    Route::prefix('notifications')->group(function () {
        Route::get('/', function (Request $request) {
            $notifications = $request->user()
                ->notifications()
                ->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $notifications->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                ],
                'unread_count' => $request->user()->unreadNotifications()->count(),
                'message' => 'Notifications récupérées avec succès'
            ]);
        });

        Route::post('/{id}/read', function (Request $request, $id) {
            $notification = $request->user()->notifications()->find($id);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification non trouvée'
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Notification marquée comme lue'
            ]);
        });

        Route::post('/read-all', function (Request $request) {
            $request->user()->unreadNotifications->markAsRead();

            return response()->json([
                'success' => true,
                'message' => 'Toutes les notifications marquées comme lues'
            ]);
        });
    });
});

// ==========================================
// ROUTES ADMIN (optionnel pour plus tard)
// ==========================================
Route::prefix('admin')->middleware(['auth:sanctum', 'admin'])->name('admin.')->group(function () {
    // Gaming Admin
    Route::prefix('gaming')->name('gaming.')->group(function () {
        Route::get('/stats', function () {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_users' => \App\Models\User::count(),
                    'total_achievements' => \App\Models\Achievement::count(),
                    'total_xp_distributed' => \App\Models\UserLevel::sum('total_xp'),
                    'avg_level' => round(\App\Models\UserLevel::avg('level'), 2),
                    'achievement_unlock_stats' => \DB::table('user_achievements')
                        ->select('achievement_id', \DB::raw('count(*) as unlock_count'))
                        ->groupBy('achievement_id')
                        ->get()
                ],
                'message' => 'Statistiques admin gaming récupérées'
            ]);
        });

        Route::get('/users/{user}/gaming-stats', function (\App\Models\User $user) {
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user->only(['id', 'name', 'email']),
                    'gaming_stats' => $user->getGamingStats(),
                    'achievements' => $user->achievements()->get(),
                    'level_details' => $user->level?->getDetailedStats()
                ]
            ]);
        });
    });
});

// ==========================================
// ROUTE DE SANTÉ DE L'API
// ==========================================
Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'timestamp' => now(),
        'version' => '1.0.0',
        'services' => [
            'database' => \DB::connection()->getPdo() ? 'OK' : 'ERROR',
            'gaming_system' => \App\Models\Achievement::count() > 0 ? 'OK' : 'NO_ACHIEVEMENTS',
            'cache' => \Cache::store()->getStore() ? 'OK' : 'ERROR'
        ],
        'environment' => app()->environment()
    ]);
});

// ==========================================
// DOCUMENTATION API (pour le développement)
// ==========================================
Route::get('/docs', function () {
    return response()->json([
        'api_version' => '1.0.0',
        'documentation' => 'https://documenter.getpostman.com/view/YOUR_COLLECTION_ID',
        'endpoints' => [
            'auth' => [
                'POST /api/auth/register' => 'Inscription utilisateur',
                'POST /api/auth/login' => 'Connexion utilisateur',
                'GET /api/auth/user' => 'Informations utilisateur',
                'PUT /api/auth/profile' => 'Mise à jour profil',
                'POST /api/auth/logout' => 'Déconnexion',
            ],
            'transactions' => [
                'GET /api/transactions' => 'Liste paginée des transactions',
                'POST /api/transactions' => 'Créer une transaction',
                'GET /api/transactions/{id}' => 'Détails d\'une transaction',
                'PUT /api/transactions/{id}' => 'Modifier une transaction',
                'DELETE /api/transactions/{id}' => 'Supprimer une transaction',
            ],
            'financial_goals' => [
                'GET /api/financial-goals' => 'Liste paginée des objectifs',
                'POST /api/financial-goals' => 'Créer un objectif',
                'GET /api/financial-goals/{id}' => 'Détails d\'un objectif',
                'PUT /api/financial-goals/{id}' => 'Modifier un objectif',
                'DELETE /api/financial-goals/{id}' => 'Supprimer un objectif',
            ],
            'analytics' => [
                'GET /api/analytics/dashboard' => 'Dashboard analytique complet',
                'GET /api/analytics/monthly-report' => 'Rapport mensuel',
                'GET /api/analytics/yearly-report' => 'Rapport annuel',
                'GET /api/analytics/category-breakdown' => 'Analyse par catégories',
            ],
            'gaming' => [
                'GET /api/gaming/dashboard' => 'Dashboard gaming complet',
                'GET /api/gaming/achievements' => 'Liste des succès',
                'GET /api/gaming/level' => 'Informations de niveau',
            ]
        ],
        'authentication' => 'Bearer token required (Sanctum)',
        'response_format' => [
            'success' => 'boolean',
            'data' => 'object|array',
            'message' => 'string',
            'pagination' => 'object (when applicable)'
        ],
        'pagination_params' => [
            'page' => 'Page number (default: 1)',
            'per_page' => 'Items per page (default: 15, max: 100)',
            'search' => 'Global search term',
            'sort_by' => 'Sort column (default: created_at)',
            'sort_direction' => 'Sort direction: asc|desc (default: desc)'
        ]
    ]);
});

// ==========================================
// ROUTE DE FALLBACK POUR 404 API
// ==========================================
Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint non trouvé',
        'available_endpoints' => '/api/docs'
    ], 404);
});
