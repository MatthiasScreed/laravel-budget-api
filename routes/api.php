<?php

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
use Illuminate\Support\Facades\Route;

// ==========================================
// ROUTES PUBLIQUES
// ==========================================
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    // ✅ ROUTES DE GESTION DES MOTS DE PASSE (MANQUANTES)
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});


// ==========================================
// ROUTES PROTÉGÉES
// ==========================================
Route::middleware('auth:sanctum')->group( function () {

    // ==========================================
    // AUTHENTIFICATION
    // ==========================================
    Route::prefix('auth')->group(function () {
        Route::get('/user', [AuthController::class, 'user']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-all', [AuthController::class, 'logoutAll']);

        // 📝 Profil (optionnel)
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);

        // 📋 Gestion des sessions
        Route::get('/sessions', [AuthController::class, 'sessions']);
        Route::delete('/sessions/{sessionId}', [AuthController::class, 'revokeSession']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        // Révoquer une session spécifique

    });

    // 🔥 NOUVELLES ROUTES STREAK
    Route::prefix('streaks')->name('streaks.')->group(function () {
        Route::get('/', [StreakController::class, 'index'])->name('index');
        Route::post('/{streakType}/claim-bonus', [StreakController::class, 'claimBonus'])->name('claim');
        Route::get('/leaderboard', [StreakController::class, 'leaderboard'])->name('leaderboard');
    });

    // ==========================================
    // RESOURCES FINANCIÈRES
    // ==========================================
    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('financial-goals', FinancialGoalController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('suggestions', SuggestionController::class);
    Route::apiResource('goal-contributions', GoalContributionController::class);

    // Routes additionnelles spécifiques
    Route::get('financial-goals/{goal}/contributions', [GoalContributionController::class, 'getByGoal']);
    Route::get('categories/{category}/transactions', [TransactionController::class, 'getByCategory']);

    // ==========================================
    // DASHBOARD GÉNÉRAL
    // ==========================================
    Route::get('dashboard/stats', function (Request $request) {
        $user = $request->user();
        $financialStats = $user->getFinancialStats();
        $gamingStats = $user->getGamingStats();

        return response()->json([
            'success' => true,
            'data' => [
                'financial' => $financialStats,
                'gaming' => $gamingStats,
                'summary' => [
                    'total_balance' => $financialStats['total_balance'],
                    'current_level' => $gamingStats['level_info']['current_level'],
                    'total_xp' => $gamingStats['level_info']['total_xp'],
                    'achievements_count' => $gamingStats['achievements_count']
                ]
            ],
            'message' => 'Statistiques dashboard récupérées avec succès'
        ]);
    });

    // ==========================================
    // SYSTÈME GAMING
    // ==========================================
    Route::prefix('gaming')->name('gaming.')->group(function () {
        // Stats et Dashboard Gaming
        Route::get('/stats', [GamingController::class, 'stats'])->name('stats');
        Route::get('/dashboard', [GamingController::class, 'dashboard'])->name('dashboard');
        Route::post('/check-achievements', [GamingController::class, 'checkAchievements'])->name('check.achievements');

        // Achievements (Succès)
        Route::prefix('achievements')->name('achievements.')->group(function () {
            Route::get('/', [AchievementController::class, 'index'])->name('index');
            Route::get('/available', [AchievementController::class, 'available'])->name('available');
            Route::get('/unlocked', [AchievementController::class, 'unlocked'])->name('unlocked');
            Route::get('/{achievement}', [AchievementController::class, 'show'])->name('show');
            Route::post('/check', [AchievementController::class, 'checkAndUnlock'])->name('check');
        });

        // Niveaux et XP
        Route::prefix('level')->name('level.')->group(function () {
            Route::get('/', [UserLevelController::class, 'show'])->name('show');
            Route::get('/progress', [UserLevelController::class, 'progress'])->name('progress');
            Route::get('/leaderboard', [UserLevelController::class, 'leaderboard'])->name('leaderboard');
        });


        // Actions utilisateur (pour déclencher XP/achievements)
        Route::prefix('actions')->name('actions.')->group(function () {
            Route::post('/transaction-created', [GamingActionController::class, 'transactionCreated'])->name('transaction.created');
            Route::post('/goal-achieved', [GamingActionController::class, 'goalAchieved'])->name('goal.achieved');
            Route::post('/category-created', [GamingActionController::class, 'categoryCreated'])->name('category.created');
            Route::post('/add-xp', [GamingActionController::class, 'addXp'])->name('add.xp'); // Debug/Admin
        });

        // ==========================================
        // WEBHOOKS INTÉGRÉS (Auto-trigger gaming)
        // ==========================================
        Route::prefix('webhooks')->name('webhooks.')->group(function () {
            // Ces routes seront appelées automatiquement par les événements Laravel
            Route::post('/transaction-created/{transaction}', function (Request $request, $transaction) {
                // Auto-trigger gaming actions
                return app(GamingActionController::class)->transactionCreated(
                    new \App\Http\Requests\Gaming\TransactionCreatedRequest(['transaction_id' => $transaction])
                );
            })->name('transaction.created');

            Route::post('/goal-completed/{goal}', function (Request $request, $goal) {
                return app(GamingActionController::class)->goalAchieved(
                    new \App\Http\Requests\Gaming\GoalAchievedRequest(['goal_id' => $goal])
                );
            })->name('goal.completed');
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
        'services' => [
            'database' => \DB::connection()->getPdo() ? 'OK' : 'ERROR',
            'gaming_system' => \App\Models\Achievement::count() > 0 ? 'OK' : 'NO_ACHIEVEMENTS'
        ]
    ]);
});

// ==========================================
// DOCUMENTATION API (pour le développement)
// ==========================================
Route::get('/docs/gaming', function () {
    return response()->json([
        'endpoints' => [
            'gaming' => [
                'GET /api/gaming/stats' => 'Statistiques gaming utilisateur',
                'GET /api/gaming/dashboard' => 'Dashboard gaming complet',
                'POST /api/gaming/check-achievements' => 'Vérifier nouveaux succès',
            ],
            'achievements' => [
                'GET /api/gaming/achievements' => 'Liste tous les succès',
                'GET /api/gaming/achievements/available' => 'Succès disponibles',
                'GET /api/gaming/achievements/unlocked' => 'Succès débloqués',
                'GET /api/gaming/achievements/{id}' => 'Détails d\'un succès',
            ],
            'levels' => [
                'GET /api/gaming/level' => 'Infos niveau utilisateur',
                'GET /api/gaming/level/progress' => 'Progression détaillée',
                'GET /api/gaming/level/leaderboard' => 'Classement des niveaux',
            ],
            'actions' => [
                'POST /api/gaming/actions/transaction-created' => 'Action transaction créée',
                'POST /api/gaming/actions/goal-achieved' => 'Action objectif atteint',
                'POST /api/gaming/actions/category-created' => 'Action catégorie créée',
            ]
        ],
        'authentication' => 'Bearer token required (Sanctum)',
        'response_format' => [
            'success' => 'boolean',
            'data' => 'object|array',
            'message' => 'string'
        ]
    ]);
});
