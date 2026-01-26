<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check de l'API
     */
    public function health(): JsonResponse
    {
        try {
            $startTime = microtime(true);

            $services = [
                'database' => $this->testDatabase(),
                'cache' => $this->testCache(),
                'gaming' => $this->testGamingSystem(),
                'api' => true,
            ];

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            // ✅ RÉPONSE SIMPLE - Le middleware HandleCors ajoute les headers
            return response()->json([
                'success' => true,
                'message' => 'CoinQuest API is running',
                'timestamp' => now()->toISOString(),
                'environment' => app()->environment(),
                'version' => '1.0.0',
                'response_time_ms' => $responseTime,
                'services' => $services,
                'server_info' => [
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'API health check failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Service temporarily unavailable',
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * Test de connexion base de données
     */
    private function testDatabase(): bool
    {
        try {
            DB::connection()->getPdo();
            DB::select('SELECT 1');

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test du système de cache
     */
    private function testCache(): bool
    {
        try {
            $testKey = 'health_test_'.time();
            Cache::put($testKey, 'ok', 60);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            return $value === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Test du système gaming
     */
    private function testGamingSystem(): bool
    {
        try {
            $tables = ['user_levels', 'achievements', 'gaming_actions'];
            foreach ($tables as $table) {
                if (! DB::getSchemaBuilder()->hasTable($table)) {
                    return false;
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
