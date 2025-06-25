<?php

namespace App\Providers;

use App\Events\CategoryCreated;
use App\Events\GoalCreated;
use App\Events\UserRegistered;
use App\Events\TransactionCreated;
use App\Events\GoalCompleted;
use App\Events\AchievementUnlocked;
use App\Events\LevelUp;
use App\Events\StreakMilestone;

use App\Listeners\HandleCategoryCreated;
use App\Listeners\HandleGoalCreated;
use App\Listeners\HandleUserRegistered;
use App\Listeners\HandleTransactionCreated;
use App\Listeners\HandleGoalCompleted;
use App\Listeners\HandleAchievementUnlocked;
use App\Listeners\HandleLevelUp;
use App\Listeners\HandleStreakMilestone;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // ==========================================
        // ÉVÉNEMENTS LARAVEL PAR DÉFAUT
        // ==========================================
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],

        // ==========================================
        // ÉVÉNEMENTS GAMING PERSONNALISÉS
        // ==========================================
        UserRegistered::class => [
            HandleUserRegistered::class,
        ],

        GoalCreated::class => [
            HandleGoalCreated::class,
        ],

        CategoryCreated::class => [
            HandleCategoryCreated::class,
        ],

        TransactionCreated::class => [
            HandleTransactionCreated::class,
        ],

        GoalCompleted::class => [
            HandleGoalCompleted::class,
        ],

        // ==========================================
        // ÉVÉNEMENTS GAMING AVANCÉS
        // ==========================================
        AchievementUnlocked::class => [
            HandleAchievementUnlocked::class,
        ],

        LevelUp::class => [
            HandleLevelUp::class,
        ],

        StreakMilestone::class => [
            HandleStreakMilestone::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
