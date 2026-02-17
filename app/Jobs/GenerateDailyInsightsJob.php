<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FinancialInsightService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job : Génération quotidienne des insights financiers
 *
 * Dispatch via Scheduler (08:00) ou manuellement
 * Queue : analytics (traitement lourd)
 *
 * Usage :
 * - Scheduler: $schedule->job(new GenerateDailyInsightsJob)->dailyAt('08:00')
 * - Manuel: GenerateDailyInsightsJob::dispatch()
 * - Un user: GenerateDailyInsightsJob::dispatch($userId)
 */
class GenerateDailyInsightsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;
    public int $backoff = 60;

    private ?int $userId;

    /**
     * Créer le job
     *
     * @param int|null $userId ID user spécifique ou null pour tous
     */
    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
        $this->onQueue('analytics');
    }

    /**
     * Exécuter le job
     *
     * @param FinancialInsightService $service
     * @return void
     */
    public function handle(
        FinancialInsightService $service
    ): void {
        $users = $this->getTargetUsers();

        Log::info('Insights generation started', [
            'user_count' => $users->count(),
            'single_user' => $this->userId,
        ]);

        $stats = $this->processUsers(
            $users,
            $service
        );

        Log::info('Insights generation completed', $stats);
    }

    /**
     * Récupérer les utilisateurs ciblés
     *
     * @return \Illuminate\Support\Collection
     */
    private function getTargetUsers()
    {
        if ($this->userId) {
            return User::where('id', $this->userId)
                ->get();
        }

        // Utilisateurs actifs avec des transactions
        return User::whereHas('transactions')
            ->where('is_active', true)
            ->get();
    }

    /**
     * Traiter les utilisateurs par batch
     *
     * @param mixed $users Collection d'utilisateurs
     * @param FinancialInsightService $service
     * @return array Statistiques de traitement
     */
    private function processUsers(
        $users,
        FinancialInsightService $service
    ): array {
        $stats = [
            'processed' => 0,
            'insights_created' => 0,
            'errors' => 0,
        ];

        foreach ($users as $user) {
            $result = $this->processOneUser(
                $user,
                $service
            );

            $stats['processed']++;
            $stats['insights_created'] += $result;

            if ($result < 0) {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Générer les insights pour un utilisateur
     *
     * @param User $user Utilisateur cible
     * @param FinancialInsightService $service
     * @return int Nombre d'insights créés (-1 si erreur)
     */
    private function processOneUser(
        User $user,
        FinancialInsightService $service
    ): int {
        try {
            $insights = $service->generateInsights($user);
            return $insights->count();
        } catch (\Exception $e) {
            Log::error('Insight generation failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return -1;
        }
    }

    /**
     * Gérer l'échec du job
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('InsightsJob FAILED', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
