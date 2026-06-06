<?php

use App\Jobs\GenerateDailyInsightsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new GenerateDailyInsightsJob())
    ->dailyAt('08:00')
    ->name('generate-daily-insights')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/insights-job.log'));

// Rappel matin — 9h
Schedule::command('quest:notify')
    ->dailyAt('09:00');

// Alerte série soir — 20h
Schedule::command('quest:notify --streak')
    ->dailyAt('20:00');

// ==========================================
// RÉSUMÉ HEBDOMADAIRE — lundi à 8h (Paris)
// ==========================================
Schedule::command('coinquest:weekly-report')
    ->weeklyOn(1, '08:00')
    ->timezone('Europe/Paris')
    ->name('weekly-report-emails')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('coinquest:weekly-report a échoué');
    });

// ==========================================
// STREAK DANGER — chaque jour à 18h (Paris)
// ==========================================
Schedule::command('coinquest:streak-danger')
    ->dailyAt('18:00')
    ->timezone('Europe/Paris')
    ->name('streak-danger-emails')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('coinquest:streak-danger a échoué');
    });

// ==========================================
// NETTOYAGE DES STREAKS EXPIRÉES — 00:05
// ==========================================
Schedule::call(function () {
    \App\Models\Streak::where('is_active', true)
        ->whereDate('last_activity_date', '<', now()->subDay()->toDateString())
        ->each(fn($streak) => $streak->checkIfBroken());
})
    ->dailyAt('00:05')
    ->timezone('Europe/Paris')
    ->name('streak-expiry-check')
    ->withoutOverlapping();
