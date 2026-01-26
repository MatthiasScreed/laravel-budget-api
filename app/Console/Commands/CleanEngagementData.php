<?php

// app/Console/Commands/CleanEngagementData.php

namespace App\Console\Commands;

use App\Models\UserAction;
use App\Models\UserNotification;
use App\Models\UserSessionExtended;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanEngagementData extends Command
{
    protected $signature = 'engagement:clean {--days=30 : Nombre de jours à conserver}';

    protected $description = 'Nettoyer les anciennes données d\'engagement';

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoffDate = Carbon::now()->subDays($days);

        $this->info("Nettoyage des données d'engagement antérieures au {$cutoffDate->toDateString()}...");

        // Nettoyer les actions utilisateur anciennes
        $deletedActions = UserAction::where('created_at', '<', $cutoffDate)->delete();
        $this->info("Actions supprimées: {$deletedActions}");

        // Nettoyer les notifications lues anciennes
        $deletedNotifications = UserNotification::whereNotNull('read_at')
            ->where('read_at', '<', $cutoffDate->subWeeks(2)) // Garder 2 semaines de plus pour les notifications lues
            ->delete();
        $this->info("Notifications supprimées: {$deletedNotifications}");

        // Nettoyer les sessions étendues anciennes
        $deletedSessions = UserSessionExtended::where('started_at', '<', $cutoffDate)->delete();
        $this->info("Sessions supprimées: {$deletedSessions}");

        $this->info('Nettoyage terminé !');
    }
}
