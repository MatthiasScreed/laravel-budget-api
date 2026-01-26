<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserAction;
use App\Models\UserNotification;
use App\Services\EngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EngagementController extends Controller
{
    protected EngagementService $engagementService;

    public function __construct(EngagementService $engagementService)
    {
        $this->engagementService = $engagementService;
    }

    /**
     * Tracker une action utilisateur (appelé depuis le frontend)
     */
    public function trackAction(Request $request): \Illuminate\Http\JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action_type' => 'required|string|max:50',
            'context' => 'nullable|string|max:100',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $result = $this->engagementService->trackUserAction(
            $user,
            $request->action_type,
            $request->context,
            $request->metadata
        );

        return response()->json([
            'success' => true,
            'message' => $result['action_tracked'] ? 'Action trackée avec succès' : 'Erreur de tracking',
            'data' => $result,
        ]);
    }

    /**
     * Obtenir les stats d'engagement de l'utilisateur connecté
     */
    public function getStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $stats = $this->engagementService->getEngagementStats($user);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Obtenir l'historique des actions récentes
     */
    public function getRecentActions(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 20);
        $limit = min(100, max(1, (int) $limit)); // Entre 1 et 100

        $actions = UserAction::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($action) {
                return [
                    'id' => $action->id,
                    'action_type' => $action->action_type,
                    'context' => $action->context,
                    'xp_gained' => $action->xp_gained,
                    'timestamp' => $action->created_at,
                    'description' => $this->getActionDescription($action->action_type),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $actions,
        ]);
    }

    /**
     * Obtenir le classement des utilisateurs
     */
    public function getLeaderboard(Request $request): JsonResponse
    {
        $type = $request->query('type', 'xp'); // 'xp', 'level', 'weekly_xp'
        $limit = min(50, max(10, (int) $request->query('limit', 20)));

        $query = \App\Models\User::join('user_levels', 'users.id', '=', 'user_levels.user_id')
            ->select('users.name', 'users.id', 'user_levels.level', 'user_levels.total_xp');

        switch ($type) {
            case 'level':
                $query->orderBy('user_levels.level', 'desc')
                    ->orderBy('user_levels.total_xp', 'desc');
                break;
            case 'weekly_xp':
                $weekAgo = now()->subWeek();
                $query->join('user_actions', 'users.id', '=', 'user_actions.user_id')
                    ->where('user_actions.created_at', '>=', $weekAgo)
                    ->selectRaw('SUM(user_actions.xp_gained) as weekly_xp')
                    ->groupBy('users.id', 'users.name', 'user_levels.level', 'user_levels.total_xp')
                    ->orderBy('weekly_xp', 'desc');
                break;
            default: // xp
                $query->orderBy('user_levels.total_xp', 'desc');
                break;
        }

        $leaderboard = $query->limit($limit)->get();

        // Ajouter le rang
        $leaderboard = $leaderboard->map(function ($user, $index) {
            return [
                'rank' => $index + 1,
                'user_id' => $user->id,
                'name' => $user->name,
                'level' => $user->level,
                'total_xp' => $user->total_xp,
                'weekly_xp' => $user->weekly_xp ?? null,
            ];
        });

        // Trouver la position de l'utilisateur connecté
        $currentUser = $request->user();
        $userPosition = $this->findUserPosition($currentUser->id, $type);

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $leaderboard,
                'user_position' => $userPosition,
                'type' => $type,
                'total_users' => \App\Models\User::whereHas('level')->count(),
            ],
        ]);
    }

    /**
     * Marquer une notification comme lue
     */
    public function markNotificationAsRead(Request $request, int $notificationId): JsonResponse
    {
        $notification = UserNotification::where('id', $notificationId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification non trouvée',
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue',
        ]);
    }

    /**
     * Obtenir les notifications récentes de l'utilisateur
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $limit = min(50, max(5, (int) $request->query('limit', 20)));
        $unreadOnly = $request->query('unread_only', false);

        $query = UserNotification::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        if ($unreadOnly) {
            $query->unread();
        }

        $notifications = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'notifications' => $notifications,
                'unread_count' => UserNotification::where('user_id', $request->user()->id)
                    ->unread()->count(),
            ],
        ]);
    }

    // Méthodes utilitaires privées
    private function getActionDescription(string $actionType): string
    {
        return match ($actionType) {
            'page_view' => 'Page consultée',
            'button_click' => 'Bouton cliqué',
            'transaction_add' => 'Transaction ajoutée',
            'goal_create' => 'Objectif créé',
            'goal_contribute' => 'Contribution effectuée',
            'achievement_view' => 'Achievements consultés',
            'share_success' => 'Succès partagé',
            'daily_login' => 'Connexion quotidienne',
            default => 'Action effectuée'
        };
    }

    private function findUserPosition(int $userId, string $type): ?array
    {
        // Logique pour trouver la position exacte de l'utilisateur dans le classement
        $query = \App\Models\User::join('user_levels', 'users.id', '=', 'user_levels.user_id');

        switch ($type) {
            case 'level':
                $userLevel = \App\Models\User::find($userId)->level;
                $betterUsers = $query->where(function ($q) use ($userLevel) {
                    $q->where('user_levels.level', '>', $userLevel->level)
                        ->orWhere(function ($q2) use ($userLevel) {
                            $q2->where('user_levels.level', $userLevel->level)
                                ->where('user_levels.total_xp', '>', $userLevel->total_xp);
                        });
                })->count();
                break;
            default:
                $userXp = \App\Models\User::find($userId)->level->total_xp;
                $betterUsers = $query->where('user_levels.total_xp', '>', $userXp)->count();
                break;
        }

        return [
            'rank' => $betterUsers + 1,
            'percentile' => $this->calculatePercentile($betterUsers + 1),
        ];
    }

    private function calculatePercentile(int $rank): float
    {
        $totalUsers = \App\Models\User::whereHas('level')->count();

        return $totalUsers > 0 ? round((($totalUsers - $rank) / $totalUsers) * 100, 1) : 0;
    }
}
