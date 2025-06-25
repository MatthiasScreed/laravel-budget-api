<?php

namespace App\Listeners;

use App\Events\AchievementUnlocked;
use App\Services\GamingService;
use App\Notifications\AchievementUnlockedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleAchievementUnlocked implements ShouldQueue
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
    public function handle(AchievementUnlocked $event): void
    {
        $user = $event->user;
        $achievement = $event->achievement;

        try {
            // 1. Les XP du succès sont déjà ajoutés lors du déblocage
            // Ici on ajoute un bonus selon la rareté
            $rarityBonus = $this->getRarityBonus($achievement->rarity);
            if ($rarityBonus > 0) {
                $this->gamingService->addExperience($user, $rarityBonus, 'rarity_bonus');
            }

            // 2. Notification push/database
            $user->notify(new AchievementUnlockedNotification($achievement));

            // 3. Vérifier si déblocage de ce succès permet d'autres succès
            // (ex: "Collectionneur" pour avoir débloqué 10 succès)
            $this->checkAchievementMilestones($user);

            // 4. Social features - partage automatique si succès rare
            if (in_array($achievement->rarity, ['epic', 'legendary'])) {
                $this->handleRareAchievementShare($user, $achievement);
            }

            \Log::info("Succès débloqué - Notifications envoyées", [
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'achievement_name' => $achievement->name,
                'rarity' => $achievement->rarity,
                'points' => $achievement->points,
                'rarity_bonus' => $rarityBonus
            ]);

        } catch (\Exception $e) {
            \Log::error("Erreur lors du traitement du déblocage de succès", [
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get rarity bonus XP
     */
    private function getRarityBonus($rarity): int
    {
        return match($rarity) {
            'common' => 0,
            'uncommon' => 25,
            'rare' => 50,
            'epic' => 100,
            'legendary' => 250,
            default => 0
        };
    }

    /**
     * Check achievement collection milestones
     */
    private function checkAchievementMilestones($user): void
    {
        $achievementCount = $user->achievements()->count();

        $milestones = [5, 10, 25, 50, 100];

        if (in_array($achievementCount, $milestones)) {
            $bonusXp = $achievementCount * 10;
            $this->gamingService->addExperience($user, $bonusXp, 'achievement_collector');
        }
    }

    /**
     * Handle rare achievement sharing
     */
    private function handleRareAchievementShare($user, $achievement): void
    {
        // Ici vous pourriez intégrer avec des APIs sociales
        // ou envoyer des notifications aux amis, etc.
        \Log::info("Succès rare débloqué - Partage potentiel", [
            'user_id' => $user->id,
            'achievement_name' => $achievement->name,
            'rarity' => $achievement->rarity
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(AchievementUnlocked $event, \Throwable $exception): void
    {
        \Log::error("Échec du traitement AchievementUnlocked", [
            'user_id' => $event->user->id,
            'achievement_id' => $event->achievement->id,
            'error' => $exception->getMessage()
        ]);
    }
}

