<?php

// routes/api.php - VERSION COMPLÈTE AVEC TOUS LES ENDPOINTS

use App\Http\Controllers\Api\AchievementController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankController;
use App\Http\Controllers\Api\BankWebhookController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EngagementController;
use App\Http\Controllers\Api\FinancialGoalController;
use App\Http\Controllers\Api\FinancialInsightController;
use App\Http\Controllers\Api\GamingActionController;
use App\Http\Controllers\Api\GamingController;
use App\Http\Controllers\Api\GoalContributionController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ProgressiveGamingController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectionController;
use App\Http\Controllers\Api\StreakController;
use App\Http\Controllers\Api\SuggestionController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserLevelController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - CoinQuest
|--------------------------------------------------------------------------
*/

// ==========================================
// ROUTES PUBLIQUES (NO AUTH)
// ==========================================

Route::options('/{any}', fn () => response('', 200))
    ->where('any', '.*')
    ->name('cors.preflight');

Route::get('/', function () {
    return response()->json([
        'success' => true,
        'app' => config('app.name', 'CoinQuest API'),
        'version' => config('app.version', '1.0.0'),
        'timestamp' => now()->toISOString(),
        'endpoints' => [
            'health' => '/api/health',
            'docs' => '/api/docs',
            'auth' => '/api/auth/*',
            'dashboard' => '/api/dashboard',
            'banking' => '/api/bank/*',
            'gaming' => '/api/gaming/*',
        ],
    ]);
})->name('api.root');

Route::get('/ping', fn () => response()->json([
    'pong' => true,
    'timestamp' => now()->toISOString(),
]))->name('api.ping');

Route::get('/health', [HealthController::class, 'health'])
    ->name('api.health');

// ==========================================
// AUTHENTICATION (PUBLIC)
// ==========================================

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
});

// ==========================================
// 🏦 BANKING - WEBHOOKS & CALLBACKS (PUBLIC)
// ==========================================

Route::post('/webhooks/bridge', [BankWebhookController::class, 'handleWebhook'])
    ->name('webhooks.bridge');

Route::match(['get', 'post'], '/bank/callback', [BankController::class, 'callback'])
    ->middleware('throttle:60,1')
    ->name('bank.callback');

// ==========================================
// ROUTES PROTÉGÉES (AUTH REQUIRED)
// ==========================================

Route::middleware(['auth:sanctum'])->group(function () {

    // ==========================================
    // AUTH USER (PROTECTED)
    // ==========================================

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::get('/user', [AuthController::class, 'user'])->name('user');
        Route::get('/me', [AuthController::class, 'user'])->name('me');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::put('/profile', [AuthController::class, 'updateProfile'])->name('update-profile');
        Route::put('/password', [AuthController::class, 'updatePassword'])->name('update-password');
    });

    // ==========================================
    // 📊 DASHBOARD (PROTECTED) - COMPLÉTÉ
    // ==========================================

    Route::prefix('dashboard')->name('dashboard.')->group(function () {
        // ✅ Endpoint principal - toutes les données
        Route::get('/', [DashboardController::class, 'index'])->name('index');

        // ✅ Métriques uniquement
        Route::get('/metrics', [DashboardController::class, 'getMetrics'])->name('metrics');

        // ✅ Rafraîchir les métriques
        Route::post('/metrics/refresh', [DashboardController::class, 'refreshMetrics'])->name('metrics.refresh');

        // Endpoints existants
        Route::get('/stats', [DashboardController::class, 'getStats'])->name('stats');
        Route::get('/savings-capacity', [DashboardController::class, 'getSavingsCapacity'])->name('savings-capacity');
        Route::get('/goal-distribution', [DashboardController::class, 'getGoalDistribution'])->name('goal-distribution');
        Route::get('/gaming', [DashboardController::class, 'getGamingDashboard'])->name('gaming');
        Route::get('/suggestions', [DashboardController::class, 'getSuggestions'])->name('suggestions');
        Route::post('/refresh', [DashboardController::class, 'refreshAll'])->name('refresh');
        Route::post('/check-achievements', [DashboardController::class, 'checkAchievements'])->name('check-achievements');
    });

    // ==========================================
    // 🏦 BANKING (PROTECTED)
    // ==========================================

    Route::prefix('bank')->name('bank.')->group(function () {
        Route::get('/providers', [BankController::class, 'listProviders'])->name('providers');
        Route::get('/health', [BankController::class, 'healthCheck'])->name('health');
        Route::post('/initiate', [BankController::class, 'initiate'])->name('initiate');

        Route::get('/connections', [BankController::class, 'index'])->name('connections.index');
        Route::get('/connections/{connection}', [BankController::class, 'show'])->name('connections.show');
        Route::get('/connections/{connection}/transactions', [BankController::class, 'getTransactions'])->name('connections.transactions');
        Route::post('/connections/{connection}/sync', [BankController::class, 'sync'])->name('connections.sync');
        Route::delete('/connections/{connection}', [BankController::class, 'destroy'])->name('connections.destroy');
        Route::post('/sync-all', [BankController::class, 'syncAll'])->name('sync-all');

        Route::get('/pending-transactions', [BankController::class, 'pendingTransactions'])->name('transactions.pending');
        Route::post('/transactions/{bankTx}/convert', [BankController::class, 'convertTransaction'])->name('transactions.convert');
        Route::post('/transactions/{bankTx}/ignore', [BankController::class, 'ignoreTransaction'])->name('transactions.ignore');
        Route::get('/stats', [BankController::class, 'getStats'])->name('stats');
    });

    // ==========================================
    // 💰 TRANSACTIONS (PROTECTED) - COMPLÉTÉ
    // ==========================================

    Route::prefix('transactions')->name('transactions.')->group(function () {
        // ✅ Statistiques
        Route::get('stats', [TransactionController::class, 'stats'])->name('stats');

        // ✅ Transactions récentes (NOUVEAU)
        Route::get('recent', [TransactionController::class, 'recent'])->name('recent');

        // ✅ Transactions en attente
        Route::get('pending', [TransactionController::class, 'pending'])->name('pending');

        // ✅ Synchronisation Bridge
        Route::post('sync', [TransactionController::class, 'sync'])->name('sync');
        Route::get('sync/{batchId}/status', [TransactionController::class, 'syncStatus'])->name('sync.status');

        // ✅ Catégorisation automatique
        Route::post('auto-categorize', [TransactionController::class, 'autoCategorizeAll'])->name('auto-categorize');

        // ✅ Suggestions
        Route::post('suggest-category', [TransactionController::class, 'suggestCategory'])->name('suggest-category');

        // ✅ Qualité de catégorisation
        Route::get('quality', [TransactionController::class, 'quality'])->name('quality');

        // ✅ Recherche
        Route::get('search', [TransactionController::class, 'search'])->name('search');

        // ✅ Export
        Route::get('export/csv', [TransactionController::class, 'exportCsv'])->name('export.csv');

        // ✅ Actions en masse
        Route::post('bulk/categorize', [TransactionController::class, 'bulkCategorize'])->name('bulk.categorize');
        Route::post('bulk/delete', [TransactionController::class, 'bulkDelete'])->name('bulk.delete');
        Route::post('bulk/recurring', [TransactionController::class, 'bulkRecurring'])->name('bulk.recurring');

        // ✅ Transactions par période (NOUVEAU)
        Route::get('period', [TransactionController::class, 'getByPeriod'])->name('period');

        // ✅ Transactions par catégorie (NOUVEAU)
        Route::get('category/{categoryId}', [TransactionController::class, 'getByCategory'])->name('by-category');
    });

    // ✅ CRUD de base
    Route::apiResource('transactions', TransactionController::class);

    // ✅ Routes avec {id}
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::put('{id}/categorize', [TransactionController::class, 'categorize'])->name('categorize');
        Route::post('{id}/auto-categorize', [TransactionController::class, 'autoCategorize'])->name('auto-categorize-single');
        Route::get('{transaction}/suggestions', [TransactionController::class, 'suggestions'])->name('suggestions');
    });

    // ==========================================
    // 🏷️ CATÉGORIES (PROTECTED)
    // ==========================================

    Route::apiResource('categories', CategoryController::class);

    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('stats', [CategoryController::class, 'stats'])->name('stats');
        Route::patch('{category}/toggle-active', [CategoryController::class, 'toggleActive'])->name('toggle-active');
    });

    // ==========================================
    // 🎯 OBJECTIFS FINANCIERS (PROTECTED)
    // ==========================================

    Route::apiResource('financial-goals', FinancialGoalController::class);

    // ✅ Objectifs actifs (NOUVEAU)
    Route::get('goals/active', [FinancialGoalController::class, 'active'])->name('financial-goals.active');

    Route::apiResource('goal-contributions', GoalContributionController::class);

    // ==========================================
    // 📦 PROJECTS - PROJETS COMPLEXES (PROTECTED)
    // ==========================================

    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('templates', [ProjectController::class, 'getTemplates'])->name('templates');

        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::get('{project}', [ProjectController::class, 'show'])->name('show');
        Route::put('{project}', [ProjectController::class, 'update'])->name('update');
        Route::delete('{project}', [ProjectController::class, 'destroy'])->name('destroy');

        Route::post('from-template', [ProjectController::class, 'createFromTemplate'])->name('from-template');

        Route::post('{project}/start', [ProjectController::class, 'start'])->name('start');
        Route::post('{project}/pause', [ProjectController::class, 'pause'])->name('pause');
        Route::post('{project}/complete', [ProjectController::class, 'complete'])->name('complete');
        Route::post('{project}/cancel', [ProjectController::class, 'cancel'])->name('cancel');

        Route::get('{project}/milestones', [ProjectController::class, 'milestones'])->name('milestones');
        Route::post('{project}/milestones/{milestone}/complete', [ProjectController::class, 'completeMilestone'])->name('milestone.complete');
    });

    // ==========================================
    // 🔮 PROJECTIONS & IA (PROTECTED) - COMPLÉTÉ
    // ==========================================

    Route::prefix('projections')->name('projections.')->group(function () {
        // ✅ Toutes les projections
        Route::get('/', [ProjectionController::class, 'index'])->name('index');

        // ✅ Insights IA (NOUVEAU)
        Route::get('/insights', [ProjectionController::class, 'insights'])->name('insights');

        // ✅ Rafraîchir les projections (NOUVEAU)
        Route::post('/refresh', [ProjectionController::class, 'refresh'])->name('refresh');

        // ✅ Projection par période (NOUVEAU)
        Route::get('/{period}', [ProjectionController::class, 'getByPeriod'])
            ->where('period', '3months|6months|12months')
            ->name('by-period');
    });

    // ==========================================
    // 🎮 GAMING (PROTECTED)
    // ==========================================

    Route::prefix('gaming')->name('gaming.')->group(function () {
        Route::get('stats', [GamingController::class, 'stats'])->name('stats');
        Route::get('dashboard', [GamingController::class, 'dashboard'])->name('dashboard');
        Route::get('player', [GamingController::class, 'getPlayerData'])->name('player');
        Route::put('player', [GamingController::class, 'updatePlayer'])->name('player.update');

        // 🏆 ACHIEVEMENTS
        Route::prefix('achievements')->name('achievements.')->group(function () {
            Route::get('/', [AchievementController::class, 'index'])->name('index');
            Route::get('recent', [AchievementController::class, 'recent'])->name('recent');
            Route::get('user', [AchievementController::class, 'getUserAchievements'])->name('user');
            Route::post('check', [AchievementController::class, 'checkAchievements'])->name('check');
            Route::post('{achievement}/unlock', [AchievementController::class, 'unlock'])->name('unlock');
        });

        Route::get('user-achievements', [AchievementController::class, 'getUserAchievements'])->name('user-achievements');

        // 📊 LEVEL & XP
        Route::prefix('level')->name('level.')->group(function () {
            Route::get('/', [UserLevelController::class, 'show'])->name('show');
            Route::post('xp', [UserLevelController::class, 'addXP'])->name('add');
            Route::get('history', [UserLevelController::class, 'getXPEvents'])->name('history');
            Route::get('rewards', [UserLevelController::class, 'getLevelRewards'])->name('rewards');
            Route::post('rewards/{level}/claim', [UserLevelController::class, 'claimRewards'])->name('rewards.claim');
        });

        // 🎯 ACTIONS
        Route::post('actions', [GamingActionController::class, 'store'])->name('actions.store');
        Route::get('actions/recent', [GamingActionController::class, 'recent'])->name('actions.recent');

        // 🔥 STREAKS
        Route::prefix('streaks')->name('streaks.')->group(function () {
            Route::get('/', [StreakController::class, 'index'])->name('index');
            Route::get('all', [StreakController::class, 'index'])->name('all');
            Route::post('update', [StreakController::class, 'updateStreak'])->name('update');
            Route::post('{type}/update', [StreakController::class, 'updateStreakByType'])->name('update-by-type');
            Route::get('{type}', [StreakController::class, 'show'])->name('show');
            Route::post('{type}/trigger', [StreakController::class, 'trigger'])->name('trigger');
        });

        // 🏅 LEADERBOARD
        Route::get('leaderboard', [GamingController::class, 'getLeaderboard'])->name('leaderboard');
        Route::get('ranking', [GamingController::class, 'getUserRanking'])->name('ranking');
    });

    // ==========================================
    // 📈 ENGAGEMENT (PROTECTED)
    // ==========================================

    Route::prefix('engagement')->name('engagement.')->group(function () {
        Route::post('track', [EngagementController::class, 'trackAction'])->name('track');
        Route::get('stats', [EngagementController::class, 'getStats'])->name('stats');
        Route::get('recent-actions', [EngagementController::class, 'getRecentActions'])->name('recent');
        Route::get('leaderboard', [EngagementController::class, 'getLeaderboard'])->name('leaderboard');
        Route::get('notifications', [EngagementController::class, 'getNotifications'])->name('notifications.index');
        Route::put('notifications/{id}/read', [EngagementController::class, 'markNotificationAsRead'])->name('notifications.read');
        Route::post('notifications/{id}/dismiss', [EngagementController::class, 'dismissNotification'])->name('notifications.dismiss');
    });

    // ==========================================
    // 📊 ANALYTICS (PROTECTED)
    // ==========================================

    Route::prefix('analytics')->name('analytics.')->group(function () {
        Route::get('dashboard', [AnalyticsController::class, 'dashboard'])->name('dashboard');
        Route::get('transactions-summary', [AnalyticsController::class, 'transactionsSummary'])->name('transactions');
        Route::get('goals-progress', [AnalyticsController::class, 'goalsProgress'])->name('goals');
        Route::get('spending-trends', [AnalyticsController::class, 'spendingTrends'])->name('spending');
        Route::get('category-breakdown', [AnalyticsController::class, 'categoryBreakdown'])->name('categories');
    });

    // ==========================================
    // 💡 SUGGESTIONS (PROTECTED)
    // ==========================================

    Route::get('suggestions', [SuggestionController::class, 'index'])->name('suggestions.index');
});

// Progressive Gaming System
Route::middleware('auth:sanctum')->prefix('gaming')->group(function () {

    // Configuration & Profil
    Route::get('/config', [ProgressiveGamingController::class, 'getConfig']);
    Route::get('/profile', [ProgressiveGamingController::class, 'getProfile']);
    Route::put('/preferences', [ProgressiveGamingController::class, 'updatePreferences']);

    // Dashboard & Données
    Route::get('/dashboard', [ProgressiveGamingController::class, 'getDashboard']);
    Route::get('/encouragement', [ProgressiveGamingController::class, 'getDailyEncouragement']);

    // Milestones
    Route::get('/milestones', [ProgressiveGamingController::class, 'getMilestones']);
    Route::post('/milestones/{id}/claim', [ProgressiveGamingController::class, 'claimMilestoneReward']);

    // Événements & Tracking
    Route::post('/event', [ProgressiveGamingController::class, 'processEvent']);
    Route::post('/interaction', [ProgressiveGamingController::class, 'recordInteraction']);
    Route::post('/feedback/{id}/dismiss', [ProgressiveGamingController::class, 'dismissFeedback']);

    // Progression
    Route::get('/progress', [ProgressiveGamingController::class, 'getProgress']);

    // Admin/Debug
    Route::post('/recalculate', [ProgressiveGamingController::class, 'recalculateProfile']);

    Route::get('/gaming/config', [ProgressiveGamingController::class, 'getConfig']);
});


Route::middleware('auth:sanctum')->prefix('insights')->group(function () {
    // Liste paginée avec filtres
    Route::get(
        '/',
        [FinancialInsightController::class, 'index']
    );

    // Résumé / compteurs
    Route::get(
        '/summary',
        [FinancialInsightController::class, 'summary']
    );

    // Générer de nouveaux insights
    Route::post(
        '/generate',
        [FinancialInsightController::class, 'generate']
    );

    // Tout marquer comme lu
    Route::post(
        '/read-all',
        [FinancialInsightController::class, 'markAllAsRead']
    );

    // Détail d'un insight
    Route::get(
        '/{id}',
        [FinancialInsightController::class, 'show']
    );

    // Marquer comme lu
    Route::patch(
        '/{id}/read',
        [FinancialInsightController::class, 'markAsRead']
    );

    // Marquer action effectuée
    Route::patch(
        '/{id}/act',
        [FinancialInsightController::class, 'markAsActed']
    );

    // Rejeter
    Route::patch(
        '/{id}/dismiss',
        [FinancialInsightController::class, 'dismiss']
    );

    // Supprimer
    Route::delete(
        '/{id}',
        [FinancialInsightController::class, 'destroy']
    );
});


// ==========================================
// 🛡️ ADMIN ROUTES (PROTECTED + ADMIN)
// ==========================================

Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('users-engagement', [AdminController::class, 'usersEngagement'])->name('users-engagement');
        Route::get('system-stats', [AdminController::class, 'systemStats'])->name('system-stats');
        Route::post('broadcast-notification', [AdminController::class, 'broadcastNotification'])->name('broadcast');
        Route::get('users', [AdminController::class, 'listUsers'])->name('users');
        Route::delete('users/{user}', [AdminController::class, 'deleteUser'])->name('users.delete');
    });

// ==========================================
// 🚨 FALLBACK 404
// ==========================================

Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Route API non trouvée',
        'error' => 'Endpoint inexistant',
    ], 404);
});

Route::get('/temp-clear-insights', function () {
    $count = \App\Models\FinancialInsight::truncate();
    return 'Insights supprimés';
});

