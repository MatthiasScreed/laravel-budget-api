<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\AdminBroadcastMail;
use App\Models\Achievement;
use App\Models\GamingEvent;
use App\Models\User;
use App\Models\UserSessionExtended;
use App\Services\GamingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Contrôleur d'administration pour le système gaming
 */
class AdminController extends Controller
{
    protected GamingService $gamingService;

    public function __construct(GamingService $gamingService)
    {
        $this->gamingService = $gamingService;
    }

    // ==========================================
    // DASHBOARD ADMIN
    // ==========================================

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

            return response()->json(['success' => true, 'data' => $metrics]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors du chargement du dashboard admin');
        }
    }

    // ==========================================
    // GESTION DES UTILISATEURS
    // ==========================================

    /**
     * Liste des utilisateurs avec pagination
     * FIX: xp → total_xp (colonne réelle dans user_levels)
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

            // FIX: "xp" → "total_xp" (colonne réelle dans la migration)
            $users = $query->select('users.*', 'user_levels.level', 'user_levels.total_xp')
                ->orderByDesc('user_levels.level')
                ->orderByDesc('user_levels.total_xp')
                ->paginate($request->input('per_page', 20));

            return response()->json(['success' => true, 'data' => $users]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la récupération des utilisateurs');
        }
    }

    public function listUsers(Request $request): JsonResponse
    {
        return $this->users($request);
    }

    public function showUser(User $user): JsonResponse
    {
        try {
            $user->load(['level', 'achievements']);

            return response()->json(['success' => true, 'data' => $user]);
        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur lors de la récupération de l\'utilisateur');
        }
    }

    public function deleteUser(User $user): JsonResponse
    {
        try {
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas supprimer votre propre compte',
                ], 400);
            }

            $userName = $user->name;
            $userId = $user->id;
            $user->delete();

            \Log::warning('Admin deleted user', [
                'admin_id' => auth()->id(),
                'deleted_user_id' => $userId,
                'deleted_user_name' => $userName,
            ]);

            return response()->json(['success' => true, 'message' => "Utilisateur {$userName} supprimé"]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur suppression utilisateur');
        }
    }

    public function toggleAdmin(User $user): JsonResponse
    {
        try {
            if ($user->id === auth()->id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous ne pouvez pas modifier vos propres droits admin',
                ], 400);
            }

            $user->update(['is_admin' => ! $user->is_admin]);

            return response()->json([
                'success' => true,
                'data' => ['is_admin' => $user->is_admin],
                'message' => $user->is_admin ? 'Droits admin accordés' : 'Droits admin retirés',
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur toggle admin');
        }
    }

    // ==========================================
    // STATS
    // ==========================================

    public function usersEngagement(Request $request): JsonResponse
    {
        try {
            $data = [
                'total_users' => User::count(),
                'active_today' => User::where('last_activity_at', '>=', now()->startOfDay())->count(),
                'active_week' => User::where('last_activity_at', '>=', now()->subDays(7))->count(),
                'active_month' => User::where('last_activity_at', '>=', now()->subDays(30))->count(),
                'new_this_week' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'avg_level' => round(DB::table('user_levels')->avg('level') ?? 1, 1),
                // FIX: xp → total_xp
                'total_xp_distributed' => DB::table('user_levels')->sum('total_xp'),
                'total_achievements_unlocked' => DB::table('user_achievements')->count(),
            ];

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur engagement stats');
        }
    }

    public function systemStats(): JsonResponse
    {
        try {
            $data = [
                'users' => [
                    'total' => User::count(),
                    'admins' => User::where('is_admin', true)->count(),
                ],
                'transactions' => [
                    'total' => DB::table('transactions')->count(),
                    'this_month' => DB::table('transactions')
                        ->whereMonth('created_at', now()->month)->count(),
                ],
                'goals' => [
                    'total' => DB::table('financial_goals')->count(),
                    'active' => DB::table('financial_goals')->where('status', 'active')->count(),
                    'completed' => DB::table('financial_goals')->where('status', 'completed')->count(),
                ],
                'gaming' => [
                    // FIX: xp → total_xp
                    'total_xp' => DB::table('user_levels')->sum('total_xp'),
                    'achievements_unlocked' => DB::table('user_achievements')->count(),
                    'active_streaks' => DB::table('streaks')->where('is_active', true)->count(),
                ],
                'insights' => [
                    'total' => DB::table('financial_insights')->count(),
                    'unread' => DB::table('financial_insights')->where('is_read', false)->count(),
                ],
            ];

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur system stats');
        }
    }

    public function broadcastNotification(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:100',
            'message' => 'required|string|max:500',
            'type' => 'nullable|in:info,success,warning,error',
        ]);

        try {
            $title = $request->input('title');
            $message = $request->input('message');
            $type = $request->input('type', 'info');

            $users = User::all();
            $count = 0;
            $failed = 0;

            foreach ($users as $user) {
                DB::table('user_notifications')->insert([
                    'user_id'    => $user->id,
                    'title'      => $title,
                    'body'       => $message,
                    'type'       => $type,
                    'channel'    => 'email',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $count++;

                $this->queueBroadcastEmail($user, $title, $message, $type, $failed);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'users_notified' => $count,
                    'emails_queued' => $count - $failed,
                    'emails_failed' => $failed,
                ],
                'message' => "Notification envoyée à {$count} utilisateurs ({$failed} email(s) en échec)",
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur broadcast notification');
        }
    }

    private function queueBroadcastEmail(
        User $user,
        string $title,
        string $message,
        string $type,
        int &$failed
    ): void {
        try {
            Mail::to($user->email)
                ->queue(new AdminBroadcastMail($title, $message, $type));
        } catch (\Exception $e) {
            $failed++;
            Log::error('Failed to queue broadcast email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function activityLogs(Request $request): JsonResponse
    {
        try {
            $limit = min($request->input('limit', 50), 200);

            $recentTransactions = DB::table('transactions')
                ->join('users', 'transactions.user_id', '=', 'users.id')
                ->orderByDesc('transactions.created_at')
                ->limit($limit)
                ->select('users.name as user_name', 'transactions.type',
                    'transactions.amount', 'transactions.description',
                    'transactions.created_at')
                ->get()
                ->map(fn ($t) => [
                    'type' => 'transaction',
                    'user' => $t->user_name,
                    'action' => "Transaction {$t->type}: {$t->amount}€",
                    'description' => $t->description,
                    'created_at' => $t->created_at,
                ]);

            $recentGoals = DB::table('financial_goals')
                ->join('users', 'financial_goals.user_id', '=', 'users.id')
                ->orderByDesc('financial_goals.created_at')
                ->limit($limit)
                ->select('users.name as user_name', 'financial_goals.name',
                    'financial_goals.target_amount', 'financial_goals.created_at')
                ->get()
                ->map(fn ($g) => [
                    'type' => 'goal',
                    'user' => $g->user_name,
                    'action' => "Objectif créé: {$g->name}",
                    'description' => "{$g->target_amount}€",
                    'created_at' => $g->created_at,
                ]);

            $recentAchievements = DB::table('user_achievements')
                ->join('users', 'user_achievements.user_id', '=', 'users.id')
                ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
                ->orderByDesc('user_achievements.unlocked_at')
                ->limit($limit)
                ->select('users.name as user_name', 'achievements.name as achievement_name',
                    'user_achievements.unlocked_at as created_at')
                ->get()
                ->map(fn ($a) => [
                    'type' => 'achievement',
                    'user' => $a->user_name,
                    'action' => "Achievement: {$a->achievement_name}",
                    'description' => null,
                    'created_at' => $a->created_at,
                ]);

            $allLogs = collect()
                ->merge($recentTransactions)
                ->merge($recentGoals)
                ->merge($recentAchievements)
                ->sortByDesc('created_at')
                ->take($limit)
                ->values();

            return response()->json(['success' => true, 'data' => $allLogs]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur logs activité');
        }
    }

    public function notifyUser(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'title'   => 'required|string|max:100',
            'message' => 'required|string|max:500',
            'type'    => 'nullable|in:info,success,warning,error',
        ]);

        try {
            // Notification in-app
            DB::table('user_notifications')->insert([
                'user_id'    => $user->id,
                'title'      => $request->input('title'),
                'message'    => $request->input('message'),
                'type'       => $request->input('type', 'info'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Email
            \Mail::to($user->email)->queue(
                new \App\Mail\AdminBroadcastMail(
                    $request->input('title'),
                    $request->input('message'),
                    $request->input('type', 'info'),
                    $user->name
                )
            );

            \Log::info('Admin notified user', [
                'admin_id' => auth()->id(),
                'user_id'  => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => "Notification envoyée à {$user->name}",
            ]);

        } catch (\Exception $e) {
            return $this->handleError($e, 'Erreur envoi notification individuelle');
        }
    }

    // ==========================================
    // MÉTHODES PRIVÉES
    // ==========================================

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

    private function getGamingMetrics(): array
    {
        return [
            'total_achievements' => Achievement::count(),
            'active_achievements' => Achievement::where('is_active', true)->count(),
            // FIX: xp → total_xp
            'total_xp_distributed' => DB::table('user_levels')->sum('total_xp'),
            'achievements_unlocked_7d' => DB::table('user_achievements')
                ->where('unlocked_at', '>=', now()->subDays(7))->count(),
            'avg_achievements_per_user' => round(
                DB::table('user_achievements')->count() / max(1, User::count()), 1
            ),
        ];
    }

    private function getEngagementMetrics(): array
    {
        return [
            'total_sessions' => UserSessionExtended::count(),
            'active_sessions' => UserSessionExtended::active()->count(),
            'avg_session_duration' => round(
                UserSessionExtended::whereNotNull('ended_at')
                    ->get()
                    ->avg(fn ($s) => $s->getDurationInMinutes()) ?? 0,
                1
            ),
        ];
    }

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

    private function getEventMetrics(): array
    {
        try {
            return [
                'active_events' => GamingEvent::where('is_active', true)->count(),
                'total_events' => GamingEvent::count(),
                'events_this_month' => GamingEvent::where('created_at', '>=', now()->startOfMonth())->count(),
            ];
        } catch (\Exception $e) {
            return ['active_events' => 0, 'total_events' => 0, 'events_this_month' => 0];
        }
    }

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
