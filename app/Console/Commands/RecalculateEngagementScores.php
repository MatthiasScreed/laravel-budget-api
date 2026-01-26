<?php

// app/Console/Commands/RecalculateEngagementScores.php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\EngagementService;
use Illuminate\Console\Command;

class RecalculateEngagementScores extends Command
{
    protected $signature = 'engagement:recalculate {--user= : ID utilisateur spécifique}';

    protected $description = 'Recalculer les scores d\'engagement des utilisateurs';

    protected EngagementService $engagementService;

    public function __construct(EngagementService $engagementService)
    {
        parent::__construct();
        $this->engagementService = $engagementService;
    }

    public function handle()
    {
        $userId = $this->option('user');

        if ($userId) {
            $user = User::findOrFail($userId);
            $score = $this->engagementService->updateEngagementScore($user);
            $this->info("Score de {$user->name}: {$score}");
        } else {
            $users = User::whereHas('level')->get();
            $bar = $this->output->createProgressBar($users->count());

            foreach ($users as $user) {
                $this->engagementService->updateEngagementScore($user);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info('Recalcul terminé pour tous les utilisateurs !');
        }
    }
}
