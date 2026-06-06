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
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(
        storage_path('logs/insights-job.log')
    );

// Rappel matin — 9h, seulement aux utilisateurs qui n'ont pas encore agi
Schedule::command('quest:notify')->dailyAt('09:00');

// Alerte série soir — 20h, seulement aux séries en danger
Schedule::command('quest:notify --streak')->dailyAt('20:00');

// ==========================================
// STREAK DANGER — chaque jour à 18h (Paris)
// ==========================================
// Envoie un email aux users dont la série expire ce soir
Schedule::command('coinquest:streak-danger')
    ->dailyAt('18:00')
    ->timezone('Europe/Paris')
    ->withoutOverlapping()
    ->onFailure(function () {
        \Log::error('coinquest:streak-danger a échoué');
    });

// ==========================================
// NETTOYAGE DES STREAKS EXPIRÉES — 00:05
// ==========================================
// Brise les streaks non mises à jour depuis >1 jour
Schedule::call(function () {
    \App\Models\Streak::where('is_active', true)
        ->whereDate('last_activity_date', '<', now()->subDay()->toDateString())
        ->each(fn($streak) => $streak->checkIfBroken());
})
    ->dailyAt('00:05')
    ->timezone('Europe/Paris')
    ->withoutOverlapping()
    ->name('streak-expiry-check');

