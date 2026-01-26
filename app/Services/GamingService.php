<?php

namespace App\Services;

use App\Events\AchievementUnlocked;
use App\Events\LevelUp;
use App\Events\StreakUpdated;
use App\Models\Achievement;
use App\Models\Challenge;
use App\Models\Streak;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GamingService
{
    /**
     * Ajouter de l'XP et gérer les montées de niveau
     *
     * @param  User  $user  Utilisateur concerné
     * @param  int  $xp  Points d'expérience à ajouter
     * @param  string  $source  Source des points (transaction, goal, etc.)
     * @return array Résultat de l'ajout d'XP
     */
    public function addExperience(User $user, int $xp, string $source = 'manual'): array
    {
        // ✅ PROTECTION : Ne pas ajouter de bonus XP pendant qu'on ajoute déjà de l'XP
        if ($source === 'level_bonus') {
            Log::warning("Tentative d'ajout de level_bonus ignorée pour éviter boucle infinie");

            return ['leveled_up' => false];
        }

        Log::info("Adding {$xp} XP to user {$user->id} from {$source}");

        $result = $user->addXp($xp);

        if ($result['leveled_up']) {
            $this->handleLevelUp($user, $result);
        }

        // Vérifier les nouveaux succès après gain d'XP
        $this->checkAchievements($user);

        return $result;
    }

    /**
     * Gérer la montée de niveau
     *
     * @param  User  $user  Utilisateur qui monte de niveau
     * @param  array  $levelData  Données du niveau
     */
    protected function handleLevelUp(User $user, array $levelData): void
    {
        Log::info("User {$user->id} leveled up to level {$levelData['new_level']}");

        // Déclencher l'événement de montée de niveau
        event(new LevelUp($user, $levelData));

        // ❌ SUPPRESSION DU BONUS XP QUI CAUSAIT LA BOUCLE INFINIE
        // Le bonus XP devrait être géré ailleurs, pas ici !

        // Bonus XP en fonction du niveau atteint
        // $bonusXp = $this->calculateLevelBonus($levelData['new_level']);
        // if ($bonusXp > 0) {
        //     $user->addXp($bonusXp); // ❌ CETTE LIGNE CAUSAIT LA BOUCLE
        // }
    }

    /**
     * Calculer le bonus d'XP pour un niveau
     *
     * ⚠️ DÉSACTIVÉ temporairement pour éviter la boucle infinie
     *
     * @param  int  $level  Niveau atteint
     * @return int Bonus d'XP
     */
    protected function calculateLevelBonus(int $level): int
    {
        // ❌ Désactivé pour éviter la boucle infinie
        return 0;

        // Version originale (à réactiver avec prudence) :
        // return match(true) {
        //     $level % 25 === 0 => 500,  // Bonus spécial tous les 25 niveaux
        //     $level % 10 === 0 => 200,  // Bonus tous les 10 niveaux
        //     $level % 5 === 0 => 50,    // Petit bonus tous les 5 niveaux
        //     default => 0
        // };
    }

    /**
     * Vérifier et débloquer les succès pour un utilisateur
     *
     * @param  User  $user  Utilisateur à vérifier
     * @return Collection Succès débloqués
     */
    public function checkAchievements(User $user): Collection
    {
        $cacheKey = "user_achievements_check_{$user->id}";

        // Éviter de vérifier trop souvent (cache 1 minute)
        if (Cache::has($cacheKey)) {
            return collect();
        }

        Cache::put($cacheKey, true, 60);

        $unlockedAchievements = collect($user->checkAndUnlockAchievements());

        $unlockedAchievements->each(function ($achievement) use ($user) {
            $this->handleAchievementUnlock($user, $achievement);
        });

        return $unlockedAchievements;
    }

    /**
     * Gérer le déblocage d'un succès
     *
     * @param  User  $user  Utilisateur qui débloque le succès
     * @param  Achievement  $achievement  Succès débloqué
     */
    protected function handleAchievementUnlock(User $user, Achievement $achievement): void
    {
        Log::info("User {$user->id} unlocked achievement: {$achievement->name}");

        // Déclencher l'événement de déblocage
        event(new AchievementUnlocked($user, $achievement));

        // XP déjà ajouté dans Achievement::unlockFor()
    }

    /**
     * Mettre à jour une série pour un utilisateur
     *
     * @param  User  $user  Utilisateur concerné
     * @param  string  $streakType  Type de série
     * @return bool Série mise à jour avec succès
     */
    public function updateStreak(User $user, string $streakType): bool
    {
        $streak = $user->streaks()->where('type', $streakType)->first();

        if (! $streak) {
            $streak = $user->streaks()->create([
                'type' => $streakType,
                'current_count' => 0,
                'best_count' => 0,
                'last_activity_date' => now(),
            ]);
        }

        $today = now()->toDateString();
        $lastActivityDate = $streak->last_activity_date ?
            $streak->last_activity_date->toDateString() : null;

        // Vérifier si c'est un nouveau jour
        if ($lastActivityDate !== $today) {
            // Vérifier si la série continue (hier) ou se remet à zéro
            $yesterday = now()->subDay()->toDateString();

            if ($lastActivityDate === $yesterday) {
                // Série continue - incrémenter
                $streak->increment('current_count');
            } else {
                // Série cassée - recommencer à 1
                $streak->update(['current_count' => 1]);
            }

            // Mettre à jour la meilleure série si nécessaire
            if ($streak->current_count > $streak->best_count) {
                $streak->update(['best_count' => $streak->current_count]);
            }

            // Mettre à jour la date de dernière activité
            $streak->update(['last_activity_date' => now()]);

            event(new StreakUpdated($user, $streak));

            // XP bonus pour les séries importantes
            $bonusXp = $this->calculateStreakBonus($streak);
            if ($bonusXp > 0) {
                $this->addExperience($user, $bonusXp, 'streak_bonus');
            }

            return true;
        }

        return false; // Pas de mise à jour (déjà fait aujourd'hui)
    }

    /**
     * Calculer le bonus XP pour une série
     *
     * @param  Streak  $streak  Série concernée
     * @return int Bonus d'XP
     */
    protected function calculateStreakBonus(Streak $streak): int
    {
        $count = $streak->current_count;

        return match (true) {
            $count >= 100 => 1000,  // Série centenaire
            $count >= 50 => 500,    // Série de 50
            $count >= 30 => 200,    // Série de 30
            $count >= 14 => 100,    // Série de 2 semaines
            $count >= 7 => 50,      // Série d'une semaine
            $count === 3 => 10,     // Début de série
            default => 0
        };
    }

    /**
     * Faire participer un utilisateur à un défi
     *
     * @param  User  $user  Utilisateur participant
     * @param  Challenge  $challenge  Défi à rejoindre
     * @return bool Participation réussie
     */
    public function joinChallenge(User $user, Challenge $challenge): bool
    {
        if (! $challenge->isAvailable()) {
            return false;
        }

        $joined = $challenge->addParticipant($user);

        if ($joined) {
            Log::info("User {$user->id} joined challenge: {$challenge->name}");
        }

        return $joined;
    }

    /**
     * Mettre à jour la progression d'un défi
     *
     * @param  User  $user  Utilisateur concerné
     * @param  Challenge  $challenge  Défi à mettre à jour
     * @param  float  $progress  Progression (0-100)
     * @param  array  $data  Données additionnelles
     */
    public function updateChallengeProgress(
        User $user,
        Challenge $challenge,
        float $progress,
        array $data = []
    ): void {
        $challenge->updateUserProgress($user, $progress, $data);

        // Bonus XP pour progression
        if ($progress >= 100) {
            $bonusXp = $this->calculateChallengeCompletionBonus($challenge);
            if ($bonusXp > 0) {
                $this->addExperience($user, $bonusXp, 'challenge_completion');
            }
        }
    }

    /**
     * Calculer le bonus de fin de défi
     *
     * @param  Challenge  $challenge  Défi terminé
     * @return int Bonus d'XP
     */
    protected function calculateChallengeCompletionBonus(Challenge $challenge): int
    {
        return match ($challenge->difficulty) {
            'expert' => 300,
            'hard' => 200,
            'medium' => 100,
            'easy' => 50,
            default => 0
        };
    }

    /**
     * Obtenir le tableau de bord gaming d'un utilisateur
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Données du tableau de bord
     */
    public function getDashboard(User $user): array
    {
        return Cache::remember("gaming_dashboard_{$user->id}", 300, function () use ($user) {
            return [
                'level_info' => $this->getLevelInfo($user),
                'recent_achievements' => $this->getRecentAchievements($user),
                'active_streaks' => $this->getActiveStreaks($user),
                'available_challenges' => $this->getAvailableChallenges($user),
                'leaderboard_position' => $this->getLeaderboardPosition($user),
                'next_rewards' => $this->getNextRewards($user),
            ];
        });
    }

    /**
     * Obtenir les informations de niveau
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Informations de niveau
     */
    protected function getLevelInfo(User $user): array
    {
        $level = $user->level;

        return [
            'current_level' => $level?->level ?? 1,
            'total_xp' => $level?->total_xp ?? 0,
            'progress_percentage' => $level?->getProgressPercentage() ?? 0,
            'title' => $user->getTitle(),
            'xp_to_next_level' => $level?->next_level_xp ?? 100,
        ];
    }

    /**
     * Obtenir les succès récents
     *
     * @param  User  $user  Utilisateur concerné
     * @return Collection Succès récents
     */
    protected function getRecentAchievements(User $user): Collection
    {
        return $user->getRecentAchievements(5);
    }

    /**
     * Obtenir les séries actives
     *
     * @param  User  $user  Utilisateur concerné
     * @return Collection Séries actives
     */
    protected function getActiveStreaks(User $user): Collection
    {
        return $user->streaks()->active()->get();
    }

    /**
     * Obtenir les défis disponibles
     *
     * @param  User  $user  Utilisateur concerné
     * @return Collection Défis disponibles
     */
    protected function getAvailableChallenges(User $user): Collection
    {
        return Challenge::active()
            ->whereDoesntHave('users', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->limit(5)
            ->get();
    }

    /**
     * Obtenir la position dans le leaderboard
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Position et informations
     */
    protected function getLeaderboardPosition(User $user): array
    {
        // Implémentation simplifiée
        return [
            'position' => 1,
            'total_users' => User::count(),
            'xp_difference_to_next' => 0,
        ];
    }

    /**
     * Obtenir les prochaines récompenses
     *
     * @param  User  $user  Utilisateur concerné
     * @return array Prochaines récompenses
     */
    protected function getNextRewards(User $user): array
    {
        return [
            'next_level_reward' => 'Nouveau titre débloqué',
            'achievements_close' => Achievement::active()
                ->whereNotIn('id', $user->achievements()->pluck('achievement_id'))
                ->limit(3)
                ->get(),
        ];
    }

    public function handleBankSync(User $user, int $transactionsImported): void
    {
        // XP pour sync
        $this->addExperience($user, min(50, $transactionsImported * 2), 'bank_sync');

        // Vérifier achievements
        $this->checkAchievements($user);

        // Streak pour syncs régulières
        $this->updateStreak($user, 'bank_sync');
    }

    public function handleBankEvent(User $user, string $event, array $data = []): void
    {
        $xpMap = [
            'bank_connection' => 100,
            'first_sync' => 50,
            'auto_sync' => 20,
            'manual_sync' => 10,
            'process_transaction' => 5,
        ];

        $xp = $xpMap[$event] ?? 0;
        if ($xp > 0) {
            $this->addExperience($user, $xp, $event);
        }

        $this->checkAchievements($user);
    }
}
