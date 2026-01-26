<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BankingLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'banking:logs {--lines=50} {--user=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Voir les logs bancaires';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $lines = $this->option('lines');
        $userId = $this->option('user');

        $logFile = storage_path('logs/banking.log');

        if (! file_exists($logFile)) {
            $this->error('Fichier de log bancaire introuvable');

            return;
        }

        $command = "tail -{$lines} {$logFile}";

        if ($userId) {
            $command .= " | grep 'user_id.*{$userId}'";
        }

        $this->info('📋 Logs bancaires :');
        system($command);
    }
}
