<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    /**
     * Health check de l'API
     *
     * @return JsonResponse
     */
    public function health(): JsonResponse
    {
        $services = [
            'database' => $this->checkDatabase(),
            'gaming_system' => $this->checkGamingSystem()
        ];

        $overallStatus = in_array('ERROR', $services) ? 'ERROR' : 'OK';

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'services' => $services,
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env')
        ]);
    }

    /**
     * Documentation de l'API
     *
     * @return JsonResponse
     */
    public function docs(): JsonResponse
    {
        return response()->json([
            'api_version' => '1.0.0',
            'name' => 'Budget Gaming API',
            'description' => 'API de gestion de budget avec système de gamification',
            'base_url' => config('app.url') . '/api',
            'authentication' => [
                'type' => 'Bearer Token (Sanctum)',
                'header' => 'Authorization: Bearer {token}',
                'login_endpoint' => '/auth/login',
                'register_endpoint' => '/auth/register'
            ],
            'response_format' => [
                'success_format' => [
                    'success' => true,
                    'data' => '{}',
                    'message' => 'string'
                ],
                'error_format' => [
                    'success' => false,
                    'message' => 'string',
                    'errors' => '{}'
                ]
            ],
            'endpoints' => [
                'authentication' => [
                    'POST /auth/register' => 'Créer un compte',
                    'POST /auth/login' => 'Se connecter',
                    'POST /auth/logout' => 'Se déconnecter',
                    'GET /auth/user' => 'Profil utilisateur'
                ],
                'transactions' => [
                    'GET /transactions' => 'Lister les transactions',
                    'POST /transactions' => 'Créer une transaction',
                    'GET /transactions/{id}' => 'Détails d\'une transaction',
                    'PUT /transactions/{id}' => 'Modifier une transaction',
                    'DELETE /transactions/{id}' => 'Supprimer une transaction'
                ],
                'categories' => [
                    'GET /categories' => 'Lister les catégories',
                    'POST /categories' => 'Créer une catégorie',
                    'GET /categories/{id}' => 'Détails d\'une catégorie',
                    'PUT /categories/{id}' => 'Modifier une catégorie',
                    'DELETE /categories/{id}' => 'Supprimer une catégorie'
                ],
                'financial_goals' => [
                    'GET /financial-goals' => 'Lister les objectifs financiers',
                    'POST /financial-goals' => 'Créer un objectif',
                    'GET /financial-goals/{id}' => 'Détails d\'un objectif',
                    'PUT /financial-goals/{id}' => 'Modifier un objectif',
                    'DELETE /financial-goals/{id}' => 'Supprimer un objectif'
                ],
                'gaming' => [
                    'GET /gaming/stats' => 'Statistiques gaming utilisateur',
                    'GET /gaming/dashboard' => 'Dashboard gaming complet',
                    'GET /gaming/achievements' => 'Liste des succès',
                    'GET /gaming/achievements/unlocked' => 'Succès débloqués',
                    'POST /gaming/check-achievements' => 'Vérifier les nouveaux succès',
                    'GET /gaming/level' => 'Informations de niveau',
                    'POST /gaming/actions/add-xp' => 'Ajouter XP manuellement (debug)'
                ],
                'dashboard' => [
                    'GET /dashboard/stats' => 'Statistiques générales du tableau de bord'
                ],
                'utilities' => [
                    'GET /health' => 'Health check de l\'API',
                    'GET /docs' => 'Documentation de l\'API'
                ]
            ]
        ]);
    }

    /**
     * Vérifier le statut de la base de données
     *
     * @return string
     */
    private function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();

            // Test simple de requête
            $result = DB::select('SELECT 1 as test');

            return ($result && $result[0]->test === 1) ? 'OK' : 'ERROR';
        } catch (\Exception $e) {
            return 'ERROR';
        }
    }

    /**
     * Vérifier le statut du système gaming
     *
     * @return string
     */
    private function checkGamingSystem(): string
    {
        try {
            // 🔧 VÉRIFIER QUE LA TABLE EXISTE D'ABORD
            if (!Schema::hasTable('achievements')) {
                return 'TABLE_NOT_EXISTS';
            }

            // Vérifier que la table achievements existe et a du contenu
            $achievementCount = Achievement::count();

            if ($achievementCount === 0) {
                return 'NO_ACHIEVEMENTS';
            }

            // Vérifier qu'au moins un achievement est actif
            $activeAchievements = Achievement::where('is_active', true)->count();

            if ($activeAchievements === 0) {
                return 'NO_ACTIVE_ACHIEVEMENTS';
            }

            return 'OK';
        } catch (\Exception $e) {
            return 'ERROR';
        }
    }
}
