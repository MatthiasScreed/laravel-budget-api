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
