<?php

namespace App\Listeners;

use App\Events\GoalCreated;
use App\Services\GamingService;
use App\Notifications\GoalCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleGoalCreated implements ShouldQueue
{
    use InteractsWithQueue;

    protected GamingService $gamingService;

    /**
     * Create the event listener.
     */
    public function __construct(GamingService $gamingService)
    {
        $this->gamingService = $gamingService;
    }

    /**
     * Handle the event.
     */
    public function handle(GoalCreated $event): void
    {
        $user = $event->user;
        $goal = $event->goal;

        try {
            // 1. Ajouter de l'XP pour la création d'objectif
            $this->gamingService->addExperience($user, 50, 'goal_created');

            // 2. Vérifier et débloquer les succès liés aux objectifs
            $user->checkAndUnlockAchievements();

            // 3. Mettre à jour la streak "goal_creation" si elle existe
            $this->gamingService->updateStreak($user, 'goal_creation');

            // 4. Envoyer une notification de félicitations
            $user->notify(new GoalCreatedNotification($goal));

            // 5. Log pour debug
            \Log::info("Objectif créé - XP ajoutés", [
                'user_id' => $user->id,
                'goal_id' => $goal->id,
                'goal_name' => $goal->name,
                'xp_added' => 50
            ]);

        } catch (\Exception $e) {
            // Log l'erreur mais ne pas faire échouer l'événement principal
            \Log::error("Erreur lors du traitement de la création d'objectif", [
                'user_id' => $user->id,
                'goal_id' => $goal->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(GoalCreated $event, \Throwable $exception): void
    {
        \Log::error("Échec du traitement GoalCreated", [
            'user_id' => $event->user->id,
            'goal_id' => $event->goal->id,
            'error' => $exception->getMessage()
        ]);
    }
}
