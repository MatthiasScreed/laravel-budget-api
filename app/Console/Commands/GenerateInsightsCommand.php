<?php

namespace App\Console\Commands;

use App\Jobs\GenerateDailyInsightsJob;
use Illuminate\Console\Command;

class GenerateInsightsCommand extends Command
{
    protected $signature = 'insights:generate
        {--user= : ID utilisateur spécifique}';

    protected $description = 'Générer les insights financiers';

    /**
     * Exécuter la commande
     */
    public function handle(): int
    {
        $userId = $this->option('user')
            ? (int) $this->option('user')
            : null;

        GenerateDailyInsightsJob::dispatch($userId);

        $target = $userId
            ? "utilisateur #$userId"
            : 'tous les utilisateurs';

        $this->info("Job dispatché pour $target");

        return Command::SUCCESS;
    }
}
