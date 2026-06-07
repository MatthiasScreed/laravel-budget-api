<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Notifications\WelcomeNotification;
use App\Services\GamingService;
use App\Services\StreakService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleUserRegistered implements ShouldQueue
{
    use InteractsWithQueue;

    protected GamingService $gamingService;
    protected StreakService $streakService;

    public function __construct(GamingService $gamingService, StreakService $streakService)
    {
        $this->gamingService = $gamingService;
        $this->streakService = $streakService;
    }

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        try {
            // 1. Ajouter l'XP de bienvenue
            $this->gamingService->addExperience($user, 100, 'registration_bonus');

            // 2. Débloquer le succès de première inscription
            $welcomeAchievement = \App\Models\Achievement::where('key', 'welcome_aboard')->first();
            if ($welcomeAchievement) {
                $user->achievements()->attach($welcomeAchievement->id, [
                    'unlocked_at' => now(),
                    'progress'    => 100,
                ]);
            }

            // 3. Initialiser les streaks de base
            $this->initializeUserStreaks($user);

            // 4. 🧊 Freeze de bienvenue (1 offert)
            $this->streakService->awardFreeze($user, 'welcome_gift');

            // 5. Envoyer la notification + email de bienvenue
            // WelcomeNotification gère mail + database en un seul appel
            $user->notify(new WelcomeNotification);

            // 7. Log pour analytics
            \Log::info('Nouvel utilisateur enregistré', [
                'user_id'           => $user->id,
                'email'             => $user->email,
                'currency'          => $user->currency,
                'language'          => $user->language,
                'streak_freezes'    => 1,
                'registration_date' => now(),
            ]);

        } catch (\Exception $e) {
            \Log::error("Erreur lors du traitement de l'inscription", [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function initializeUserStreaks($user): void
    {
        $basicStreaks = [
            'daily_login' => [
                'type'               => 'daily_login',
                'name'               => 'Connexion quotidienne',
                'description'        => 'Se connecter chaque jour',
                'current_count'      => 1,
                'best_count'         => 1,
                'is_active'          => true,
                'last_activity_date' => now(),
            ],
            'daily_transaction' => [
                'type'               => 'daily_transaction',
                'name'               => 'Transaction quotidienne',
                'description'        => 'Enregistrer au moins une transaction par jour',
                'current_count'      => 0,
                'best_count'         => 0,
                'is_active'          => true,
                'last_activity_date' => null,
            ],
        ];

        foreach ($basicStreaks as $streakData) {
            $streakData['user_id'] = $user->id;
            \App\Models\Streak::create($streakData);
        }
    }

    public function failed(UserRegistered $event, \Throwable $exception): void
    {
        \Log::error('Échec du traitement UserRegistered', [
            'user_id' => $event->user->id,
            'error'   => $exception->getMessage(),
        ]);
    }
}
