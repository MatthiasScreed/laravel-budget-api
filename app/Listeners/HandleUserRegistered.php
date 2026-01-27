<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Notifications\WelcomeNotification;
use App\Services\GamingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class HandleUserRegistered implements ShouldQueue
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
                    'progress' => 100,
                ]);
            }

            // 3. Initialiser les streaks de base
            $this->initializeUserStreaks($user);

            // 4. Envoyer la notification de bienvenue
            $user->notify(new WelcomeNotification);

            // 5. Envoyer un email de bienvenue (si configuré)
            if (config('app.send_welcome_emails', true)) {
                \Mail::to($user->email)->send(new \App\Mail\WelcomeEmail($user));
            }

            // 6. Log pour analytics
            \Log::info('Nouvel utilisateur enregistré', [
                'user_id' => $user->id,
                'email' => $user->email,
                'currency' => $user->currency,
                'language' => $user->language,
                'registration_date' => now(),
            ]);

        } catch (\Exception $e) {
            // Log l'erreur mais ne pas faire échouer l'inscription
            \Log::error("Erreur lors du traitement de l'inscription", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Initialize basic streaks for new user
     */
    private function initializeUserStreaks($user): void
    {
        $basicStreaks = [
            'daily_login' => [
                'type' => 'daily_login',
                'name' => 'Connexion quotidienne',
                'description' => 'Se connecter chaque jour',
                'current_count' => 1, // Premier login
                'best_count' => 1,
                'is_active' => true,
                'last_activity_date' => now(),
            ],
            'daily_transaction' => [
                'type' => 'daily_transaction',
                'name' => 'Transaction quotidienne',
                'description' => 'Enregistrer au moins une transaction par jour',
                'current_count' => 0,
                'best_count' => 0,
                'is_active' => true,
                'last_activity_date' => null,
            ],
        ];

        foreach ($basicStreaks as $streakData) {
            $streakData['user_id'] = $user->id;
            \App\Models\Streak::create($streakData);
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(UserRegistered $event, \Throwable $exception): void
    {
        \Log::error('Échec du traitement UserRegistered', [
            'user_id' => $event->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
