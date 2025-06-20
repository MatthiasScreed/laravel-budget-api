<?php

namespace App\Console\Commands;

use App\Models\Streak;
use App\Models\User;
use App\Services\StreakService;
use Illuminate\Console\Command;

class TestGamingStreaks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gaming:test-streaks {user_id? : ID de l\'utilisateur}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Tester le système de streaks gaming';

    /**
     * Execute the console command.
     */
    public function handle(StreakService $streakService)
    {
        $this->info('🎮 Test du système Gaming Streaks');
        $this->line('===============================');

        // 1. Obtenir un utilisateur
        $userId = $this->argument('user_id');
        $user = $userId ? User::find($userId) : User::first();

        if (!$user) {
            // Créer un utilisateur de test
            $user = User::create([
                'name' => 'Test Gaming User',
                'email' => 'gaming@test.com',
                'password' => bcrypt('password123'),
                'email_verified_at' => now()
            ]);
            $this->info("✅ Utilisateur créé: {$user->name} (ID: {$user->id})");
        } else {
            $this->info("✅ Utilisateur trouvé: {$user->name} (ID: {$user->id})");
        }

        // 2. Tester la streak de connexion
        $this->testLoginStreak($user, $streakService);

        // 3. Tester la streak de transaction
        $this->testTransactionStreak($user, $streakService);

        // 4. Afficher les résultats
        $this->displayResults($user, $streakService);

        return 0;

    }

    protected function testLoginStreak(User $user, StreakService $streakService)
    {
        $this->line('');
        $this->info('🔥 Test Streak de Connexion');
        $this->line('---------------------------');

        // Simuler plusieurs connexions
        for ($i = 1; $i <= 5; $i++) {
            $result = $streakService->triggerStreak($user, Streak::TYPE_DAILY_LOGIN);

            if ($result['success']) {
                $this->line("  Jour {$i}: {$result['message']}");
                $this->line("    - Streak: {$result['streak']['current_count']} jours");
                $this->line("    - Bonus XP: {$result['bonus_xp']}");

                if ($result['is_milestone']) {
                    $this->comment("    🎉 MILESTONE ATTEINT !");
                }
            } else {
                $this->error("  Échec jour {$i}: {$result['message']}");
            }

            // Simuler le passage d'un jour
            if ($i < 5) {
                $this->simulateNextDay($user, Streak::TYPE_DAILY_LOGIN);
            }
        }
    }

    protected function testTransactionStreak(User $user, StreakService $streakService)
    {
        $this->line('');
        $this->info('💰 Test Streak de Transaction');
        $this->line('------------------------------');

        // Créer des transactions de test
        for ($i = 1; $i <= 3; $i++) {
            // Créer une transaction fictive
            $transaction = $user->transactions()->create([
                'category_id' => 1, // Supposons que la catégorie 1 existe
                'type' => 'expense',
                'amount' => 50.00,
                'description' => "Transaction test {$i}",
                'transaction_date' => now(),
                'status' => 'completed'
            ]);

            $result = $streakService->triggerStreak($user, Streak::TYPE_DAILY_TRANSACTION);

            if ($result['success']) {
                $this->line("  Transaction {$i}: {$result['message']}");
                $this->line("    - Streak: {$result['streak']['current_count']} jours");
                $this->line("    - Bonus XP: {$result['bonus_xp']}");
            }

            // Simuler le passage d'un jour
            if ($i < 3) {
                $this->simulateNextDay($user, Streak::TYPE_DAILY_TRANSACTION);
            }
        }
    }

    protected function simulateNextDay(User $user, string $streakType)
    {
        // Modifier la date de la dernière activité pour simuler le passage du temps
        $streak = $user->streaks()->where('type', $streakType)->first();
        if ($streak) {
            $streak->update([
                'last_activity_date' => now()->subDay()
            ]);
        }
    }

    protected function displayResults(User $user, StreakService $streakService)
    {
        $this->line('');
        $this->info('📊 Résultats Finaux');
        $this->line('-------------------');

        $streaks = $streakService->getUserStreaks($user);
        $gamingStats = $user->getGamingStats();

        // Afficher les streaks
        foreach ($streaks as $streak) {
            $this->line("🔥 {$streak['type_name']}:");
            $this->line("   - Streak actuelle: {$streak['current_count']} jours");
            $this->line("   - Meilleure streak: {$streak['best_count']} jours");
            $this->line("   - Bonus disponible: " . ($streak['bonus_available'] ? 'OUI' : 'NON'));
            $this->line("   - Risque: {$streak['risk_level']}");
        }

        // Afficher les stats gaming
        $this->line('');
        $this->line("🎮 Stats Gaming:");
        $this->line("   - Niveau: {$gamingStats['level_info']['current_level']}");
        $this->line("   - XP Total: {$gamingStats['level_info']['total_xp']}");
        $this->line("   - Titre: {$gamingStats['level_info']['title']}");
        $this->line("   - Succès: {$gamingStats['achievements_count']}");

        $this->line('');
        $this->comment('✅ Test terminé avec succès !');
    }

}
