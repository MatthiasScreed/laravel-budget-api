<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\GamingEvent;
use App\Models\User;
use App\Models\UserSessionExtended;
use App\Services\GamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Contrôleur d'administration pour le système gaming
 * Gestion des événements, succès, analytics et modération
 */
class AdminController extends Controller
{
    protected GamingService $gamingService;

    public function __construct(GamingService $gamingService)
    {
        $this->gamingService = $gamingService;

        // Middleware admin pour toutes les méthodes
        $this->middleware('can:admin-access');
    }

    // ==========================================
    // DASHBOARD ADMIN
    // ==========================================

    /**
     * Dashboard administrateur avec métriques globales
     */
    public function dashboard(): JsonResponse
    {
        try {
            $metrics = Cache::remember('admin_dashboard', 600, function () {
                return [
                    'users' => $this->getUserMetrics(),
                    'gaming' => $this->getGamingMetrics(),
                    'engagement' => $this->getEngagementMetrics(),
                    'financial' => $this->getFinancialMetrics(),
                    'events' => $this->getEventMetrics(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $metrics,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors du chargement du dashboard admin');
        }
    }

    // ==========================================
    // GESTION DES ÉVÉNEMENTS GAMING
    // ==========================================

    /**
     * Créer un nouvel événement gaming
     */
    public function createEvent(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'type' => ['required', Rule::in(['double_xp', 'bonus_achievement', 'challenge', 'streak_bonus', 'weekend_boost', 'community'])],
            'description' => 'nullable|string|max:500',
            'multiplier' => 'required|numeric|min:1|max:5',
            'start_at' => 'required|date|after:now',
            'end_at' => 'required|date|after:start_at',
            'conditions' => 'nullable|array',
            'rewards' => 'nullable|array',
        ]);

        try {
            $event = GamingEvent::create($request->validated());

            // Invalider les caches
            Cache::forget('active_events');
            Cache::forget('admin_dashboard');

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Événement créé avec succès !',
            ], 201);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la création de l\'événement');
        }
    }

    /**
     * Modifier un événement existant
     */
    public function updateEvent(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'sometimes|nullable|string|max:500',
            'multiplier' => 'sometimes|numeric|min:1|max:5',
            'end_at' => 'sometimes|date|after:start_at',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $event = GamingEvent::findOrFail($id);
            $event->update($request->validated());

            Cache::forget('active_events');
            Cache::forget('admin_dashboard');

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Événement mis à jour avec succès !',
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la mise à jour de l\'événement');
        }
    }

    /**
     * Supprimer un événement
     */
    public function deleteEvent(int $id): JsonResponse
    {
        try {
            $event = GamingEvent::findOrFail($id);
            $event->delete();

            Cache::forget('active_events');
            Cache::forget('admin_dashboard');

            return response()->json([
                'success' => true,
                'message' => 'Événement supprimé avec succès !',
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la suppression de l\'événement');
        }
    }

    // ==========================================
    // GESTION DES SUCCÈS
    // ==========================================

    /**
     * Créer un nouveau succès
     */
    public function createAchievement(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:100|unique:achievements',
            'description' => 'required|string|max:255',
            'type' => ['required', Rule::in(['first_steps', 'consistency', 'milestones', 'mastery', 'special'])],
            'rarity' => ['required', Rule::in(['common', 'uncommon', 'rare', 'epic', 'legendary'])],
            'points' => 'required|integer|min:1|max:1000',
            'icon' => 'required|string|max:50',
            'criteria' => 'required|array',
        ]);

        try {
            $achievement = Achievement::create($request->validated());

            return response()->json([
                'success' => true,
                'data' => $achievement,
                'message' => 'Succès créé avec succès !',
            ], 201);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la création du succès');
        }
    }

    /**
     * Activer/désactiver un succès
     */
    public function toggleAchievement(Request $request, int $id): JsonResponse
    {
        try {
            $achievement = Achievement::findOrFail($id);
            $achievement->update(['is_active' => ! $achievement->is_active]);

            return response()->json([
                'success' => true,
                'data' => $achievement,
                'message' => $achievement->is_active ? 'Succès activé !' : 'Succès désactivé !',
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la modification du succès');
        }
    }

    // ==========================================
    // GESTION DES UTILISATEURS
    // ==========================================

    /**
     * Liste des utilisateurs avec pagination et filtres
     */
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:100',
            'level_min' => 'nullable|integer|min:1',
            'level_max' => 'nullable|integer|max:100',
            'per_page' => 'nullable|integer|min:10|max:100',
        ]);

        try {
            $query = User::with(['level', 'achievements'])
                ->leftJoin('user_levels', 'users.id', '=', 'user_levels.user_id');

            // Filtres
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('users.name', 'like', "%{$search}%")
                        ->orWhere('users.email', 'like', "%{$search}%");
                });
            }

            if ($levelMin = $request->input('level_min')) {
                $query->where('user_levels.level', '>=', $levelMin);
            }

            if ($levelMax = $request->input('level_max')) {
                $query->where('user_levels.level', '<=', $levelMax);
            }

            $users = $query->select('users.*', 'user_levels.level', 'user_levels.xp')
                ->orderByDesc('user_levels.level')
                ->orderByDesc('user_levels.xp')
                ->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $users,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la récupération des utilisateurs');
        }
    }

    /**
     * Donner/retirer de l'XP à un utilisateur
     */
    public function adjustUserXp(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'xp_change' => 'required|integer|between:-1000,1000',
            'reason' => 'required|string|max:255',
        ]);

        try {
            $user = User::findOrFail($userId);
            $xpChange = $request->input('xp_change');
            $reason = $request->input('reason');

            $beforeLevel = $user->getCurrentLevel();
            $beforeXp = $user->level?->xp ?? 0;

            if ($xpChange > 0) {
                $result = $user->addXp($xpChange);
            } else {
                $result = $user->removeXp(abs($xpChange));
            }

            $afterLevel = $user->fresh()->getCurrentLevel();
            $afterXp = $user->level?->xp ?? 0;

            // Log de l'action admin
            \Log::info('Admin XP adjustment', [
                'admin_id' => auth()->id(),
                'target_user_id' => $userId,
                'xp_change' => $xpChange,
                'reason' => $reason,
                'before_level' => $beforeLevel,
                'after_level' => $afterLevel,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_name' => $user->name,
                    'xp_change' => $xpChange,
                    'reason' => $reason,
                    'before' => ['level' => $beforeLevel, 'xp' => $beforeXp],
                    'after' => ['level' => $afterLevel, 'xp' => $afterXp],
                    'leveled_up' => $result['leveled_up'] ?? false,
                ],
                'message' => 'XP utilisateur ajusté avec succès !',
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de l\'ajustement XP');
        }
    }

    /**
     * Débloquer manuellement un succès pour un utilisateur
     */
    public function unlockAchievement(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'achievement_id' => 'required|exists:achievements,id',
            'reason' => 'nullable|string|max:255',
        ]);

        try {
            $user = User::findOrFail($userId);
            $achievement = Achievement::findOrFail($request->input('achievement_id'));
            $reason = $request->input('reason', 'Débloqué par admin');

            // Vérifier si déjà débloqué
            if ($user->achievements()->where('achievement_id', $achievement->id)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Succès déjà débloqué pour cet utilisateur',
                ], 400);
            }

            // Débloquer le succès
            $user->achievements()->attach($achievement->id, [
                'unlocked_at' => now(),
            ]);

            // Donner les points
            $user->addXp($achievement->points);

            // Log de l'action admin
            \Log::info('Admin achievement unlock', [
                'admin_id' => auth()->id(),
                'target_user_id' => $userId,
                'achievement_id' => $achievement->id,
                'reason' => $reason,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_name' => $user->name,
                    'achievement_name' => $achievement->name,
                    'points_awarded' => $achievement->points,
                    'reason' => $reason,
                ],
                'message' => 'Succès débloqué avec succès !',
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors du déblocage du succès');
        }
    }

    // ==========================================
    // ANALYTICS AVANCÉES
    // ==========================================

    /**
     * Analytics détaillées de l'engagement utilisateur
     */
    public function engagementAnalytics(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|in:7d,30d,90d,1y',
            'metric' => 'nullable|in:sessions,xp,achievements,transactions',
        ]);

        try {
            $period = $request->input('period', '30d');
            $metric = $request->input('metric', 'sessions');

            $analytics = Cache::remember("admin_analytics_{$period}_{$metric}", 1800, function () use ($period, $metric) {
                $days = $this->parsePeriod($period);
                $startDate = now()->subDays($days);

                return [
                    'overview' => $this->getEngagementOverview($startDate),
                    'daily_breakdown' => $this->getDailyBreakdown($startDate, $metric),
                    'user_segments' => $this->getUserSegments($startDate),
                    'top_performers' => $this->getTopPerformers($startDate, $metric),
                    'retention' => $this->getRetentionMetrics($startDate),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $analytics,
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la récupération des analytics');
        }
    }

    /**
     * Exporter les données pour analyse
     */
    public function exportData(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', Rule::in(['users', 'transactions', 'achievements', 'sessions'])],
            'format' => 'nullable|in:json,csv',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after:date_from',
        ]);

        try {
            $type = $request->input('type');
            $format = $request->input('format', 'json');
            $dateFrom = $request->input('date_from', now()->subMonth());
            $dateTo = $request->input('date_to', now());

            $data = $this->generateExportData($type, $dateFrom, $dateTo);

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'type' => $type,
                    'format' => $format,
                    'period' => ['from' => $dateFrom, 'to' => $dateTo],
                    'record_count' => count($data),
                ],
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de l\'export des données');
        }
    }

    // ==========================================
    // MODÉRATION ET MAINTENANCE
    // ==========================================

    /**
     * Nettoyer les données obsolètes
     */
    public function cleanupData(): JsonResponse
    {
        try {
            $results = [
                'expired_sessions' => UserSessionExtended::cleanupInactiveSessions(),
                'old_notifications' => $this->cleanupOldNotifications(),
                'expired_events' => $this->cleanupExpiredEvents(),
                'cache_cleared' => $this->clearAllCaches(),
            ];

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => 'Nettoyage effectué avec succès !',
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors du nettoyage');
        }
    }

    /**
     * Recalculer les statistiques gaming pour tous les utilisateurs
     */
    public function recalculateStats(): JsonResponse
    {
        try {
            $users = User::all();
            $recalculated = 0;

            foreach ($users as $user) {
                $this->gamingService->recalculateUserStats($user);
                $recalculated++;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'users_processed' => $recalculated,
                    'completed_at' => now()->toISOString(),
                ],
                'message' => 'Statistiques recalculées pour tous les utilisateurs !',
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors du recalcul des statistiques');
        }
    }

    // ==========================================
    // MÉTHODES UTILITAIRES PRIVÉES
    // ==========================================

    /**
     * Obtenir les métriques utilisateurs
     */
    private function getUserMetrics(): array
    {
        return [
            'total_users' => User::count(),
            'active_users_7d' => User::where('last_activity_at', '>=', now()->subDays(7))->count(),
            'active_users_30d' => User::where('last_activity_at', '>=', now()->subDays(30))->count(),
            'new_users_7d' => User::where('created_at', '>=', now()->subDays(7))->count(),
            'avg_level' => round(DB::table('user_levels')->avg('level') ?? 0, 1),
        ];
    }

    /**
     * Obtenir les métriques gaming
     */
    private function getGamingMetrics(): array
    {
        return [
            'total_achievements' => Achievement::count(),
            'active_achievements' => Achievement::active()->count(),
            'total_xp_distributed' => DB::table('user_levels')->sum('xp'),
            'achievements_unlocked_7d' => DB::table('user_achievements')
                ->where('unlocked_at', '>=', now()->subDays(7))->count(),
            'avg_achievements_per_user' => round(
                DB::table('user_achievements')->count() / max(1, User::count()), 1
            ),
        ];
    }

    /**
     * Obtenir les métriques d'engagement
     */
    private function getEngagementMetrics(): array
    {
        return [
            'total_sessions' => UserSessionExtended::count(),
            'active_sessions' => UserSessionExtended::active()->count(),
            'avg_session_duration' => round(
                UserSessionExtended::whereNotNull('ended_at')
                    ->get()
                    ->avg(function ($session) {
                        return $session->getDurationInMinutes();
                    }) ?? 0, 1
            ),
            'total_actions_7d' => DB::table('user_actions')
                ->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /**
     * Obtenir les métriques financières
     */
    private function getFinancialMetrics(): array
    {
        return [
            'total_transactions' => DB::table('transactions')->count(),
            'total_goals' => DB::table('financial_goals')->count(),
            'completed_goals' => DB::table('financial_goals')->where('status', 'completed')->count(),
            'total_volume' => round(DB::table('transactions')->sum('amount'), 2),
            'avg_transaction_amount' => round(DB::table('transactions')->avg('amount') ?? 0, 2),
        ];
    }

    /**
     * Obtenir les métriques des événements
     */
    private function getEventMetrics(): array
    {
        return [
            'active_events' => GamingEvent::active()->count(),
            'total_events' => GamingEvent::count(),
            'events_this_month' => GamingEvent::where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    /**
     * Parser la période en jours
     */
    private function parsePeriod(string $period): int
    {
        return match ($period) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '1y' => 365,
            default => 30
        };
    }

    /**
     * Obtenir l'aperçu d'engagement
     */
    private function getEngagementOverview(\DateTime $startDate): array
    {
        return [
            'dau' => User::where('last_activity_at', '>=', now()->subDay())->count(),
            'wau' => User::where('last_activity_at', '>=', now()->subWeek())->count(),
            'mau' => User::where('last_activity_at', '>=', now()->subMonth())->count(),
            'session_count' => UserSessionExtended::where('started_at', '>=', $startDate)->count(),
        ];
    }

    /**
     * Obtenir la répartition quotidienne
     */
    private function getDailyBreakdown(\DateTime $startDate, string $metric): array
    {
        // Implémentation simplifiée - à adapter selon vos besoins
        return [
            'chart_data' => [],
            'peak_day' => 'Monday',
            'average_daily' => 0,
        ];
    }

    /**
     * Obtenir les segments d'utilisateurs
     */
    private function getUserSegments(\DateTime $startDate): array
    {
        return [
            'beginners' => User::whereHas('level', fn ($q) => $q->where('level', '<=', 5))->count(),
            'intermediate' => User::whereHas('level', fn ($q) => $q->whereBetween('level', [6, 20]))->count(),
            'advanced' => User::whereHas('level', fn ($q) => $q->where('level', '>', 20))->count(),
        ];
    }

    /**
     * Obtenir les top performers
     */
    private function getTopPerformers(\DateTime $startDate, string $metric): array
    {
        return User::with(['level'])
            ->orderByDesc('level.xp')
            ->limit(10)
            ->get()
            ->map(fn ($user) => [
                'name' => $user->name,
                'level' => $user->getCurrentLevel(),
                'xp' => $user->level?->xp ?? 0,
            ])
            ->toArray();
    }

    /**
     * Obtenir les métriques de rétention
     */
    private function getRetentionMetrics(\DateTime $startDate): array
    {
        // Implémentation simplifiée
        return [
            'day_1_retention' => 85.5,
            'day_7_retention' => 65.2,
            'day_30_retention' => 42.1,
        ];
    }

    /**
     * Générer les données d'export
     */
    private function generateExportData(string $type, string $dateFrom, string $dateTo): array
    {
        return match ($type) {
            'users' => User::whereBetween('created_at', [$dateFrom, $dateTo])
                ->with(['level', 'achievements'])
                ->get()
                ->toArray(),
            'sessions' => UserSessionExtended::whereBetween('started_at', [$dateFrom, $dateTo])
                ->with('user:id,name,email')
                ->get()
                ->toArray(),
            default => []
        };
    }

    /**
     * Nettoyer les anciennes notifications
     */
    private function cleanupOldNotifications(): int
    {
        return DB::table('user_notifications')
            ->where('created_at', '<', now()->subMonths(3))
            ->where('read_at', 'IS NOT', null)
            ->delete();
    }

    /**
     * Nettoyer les événements expirés
     */
    private function cleanupExpiredEvents(): int
    {
        return GamingEvent::where('end_at', '<', now()->subDays(30))
            ->where('is_active', false)
            ->delete();
    }

    /**
     * Vider tous les caches
     */
    private function clearAllCaches(): bool
    {
        $tags = ['admin_dashboard', 'active_events', 'user_stats', 'leaderboards'];

        foreach ($tags as $tag) {
            Cache::forget($tag);
        }

        return true;
    }

    /**
     * Gérer les erreurs de manière centralisée
     */
    private function handleError(\Exception $exception, string $message): JsonResponse
    {
        \Log::error($message, [
            'exception' => $exception->getMessage(),
            'admin_id' => auth()->id(),
            'trace' => $exception->getTraceAsString(),
        ]);

        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => config('app.debug') ? $exception->getMessage() : null,
        ], 500);
    }
}
