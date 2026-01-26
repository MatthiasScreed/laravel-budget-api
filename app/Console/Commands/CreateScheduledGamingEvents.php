<?php

// app/Console/Commands/CreateScheduledGamingEvents.php

namespace App\Console\Commands;

use App\Models\GamingEvent;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CreateScheduledGamingEvents extends Command
{
    protected $signature = 'gaming:schedule-events';

    protected $description = 'Créer les événements gaming programmés';

    public function handle()
    {
        $now = Carbon::now();

        // Weekend bonus (vendredi soir au dimanche soir)
        if ($now->isFriday() && $now->hour >= 18) {
            $this->createWeekendEvent();
        }

        // Double XP du mercredi (Wisdom Wednesday)
        if ($now->isWednesday() && $now->hour >= 12 && $now->hour < 14) {
            $this->createWednesdayEvent();
        }

        // Flash Friday (vendredi après-midi)
        if ($now->isFriday() && $now->hour >= 14 && $now->hour < 16) {
            $this->createFlashFridayEvent();
        }

        $this->info('Vérification des événements programmés terminée.');
    }

    protected function createWeekendEvent()
    {
        // Vérifier si un événement weekend n'existe pas déjà
        $existingEvent = GamingEvent::where('type', 'weekend_bonus')
            ->where('start_at', '>=', now()->startOfDay())
            ->where('end_at', '<=', now()->endOfWeek())
            ->first();

        if (! $existingEvent) {
            GamingEvent::create([
                'name' => 'Weekend Warrior',
                'type' => 'weekend_bonus',
                'description' => '+50% XP tout le weekend !',
                'multiplier' => 1.50,
                'start_at' => now(),
                'end_at' => now()->endOfWeek()->subDay(), // Dimanche soir
                'is_active' => true,
            ]);

            $this->info('Événement Weekend Warrior créé !');
        }
    }

    protected function createWednesdayEvent()
    {
        GamingEvent::create([
            'name' => 'Wisdom Wednesday',
            'type' => 'goal_bonus',
            'description' => 'Double XP sur les objectifs !',
            'multiplier' => 2.00,
            'conditions' => ['action_types' => ['goal_create', 'goal_contribute']],
            'start_at' => now(),
            'end_at' => now()->addHours(2),
            'is_active' => true,
        ]);

        $this->info('Événement Wisdom Wednesday créé !');
    }

    protected function createFlashFridayEvent()
    {
        GamingEvent::create([
            'name' => 'Flash Friday',
            'type' => 'flash_bonus',
            'description' => 'Triple XP pendant 2h !',
            'multiplier' => 3.00,
            'start_at' => now(),
            'end_at' => now()->addHours(2),
            'is_active' => true,
        ]);

        $this->info('Événement Flash Friday créé !');
    }
}
