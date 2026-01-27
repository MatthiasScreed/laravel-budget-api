<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\StreakService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(
            \App\Events\UserRegistered::class,
            \App\Listeners\HandleUserRegistered::class
        );

        Event::listen(
            \App\Events\GoalCreated::class,
            \App\Listeners\HandleGoalCreated::class
        );

        Event::listen(
            \App\Events\CategoryCreated::class,
            \App\Listeners\HandleCategoryCreated::class
        );
    }
}
