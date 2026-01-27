<?php

namespace App\Listeners;

use App\Events\CategoryCreated;
use App\Services\GamingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleCategoryCreated implements ShouldQueue
{
    use InteractsWithQueue;

    protected GamingService $gamingService;

    public function __construct(GamingService $gamingService)
    {
        $this->gamingService = $gamingService;
    }

    public function handle(CategoryCreated $event): void
    {
        $user = $event->user;
        $category = $event->category;

        try {
            // XP pour création de catégorie
            $this->gamingService->addExperience($user, 25, 'category_created');

            // Vérifier les succès liés aux catégories
            $user->checkAndUnlockAchievements();

            \Log::info('Catégorie créée - XP ajoutés', [
                'user_id' => $user->id,
                'category_id' => $category->id,
                'category_name' => $category->name,
                'xp_added' => 25,
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur lors du traitement de la création de catégorie', [
                'user_id' => $user->id,
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
