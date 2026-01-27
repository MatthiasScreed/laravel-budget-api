<?php

namespace Database\Seeders;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Database\Seeder;

class GamingStep2Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        self::test();
    }

    /**
     * Tester le système de succès
     */
    public static function test(): void
    {
        echo "🏆 TEST DU SYSTÈME GAMING - ÉTAPE 2 : SUCCÈS\n";
        echo '='.str_repeat('=', 50)."\n";

        // Créer les succès par défaut
        self::createDefaultAchievements();

        // Créer un utilisateur de test
        $user = self::createTestUser();

        // Tester le déblocage de succès
        self::testAchievementUnlocking($user);

        // Tester les statistiques avec succès
        self::testStatsWithAchievements($user);

        // Nettoyer
        self::cleanup($user);

        echo "\n✅ TOUS LES TESTS SONT PASSÉS !\n";
    }

    /**
     * Créer les succès par défaut
     */
    protected static function createDefaultAchievements(): void
    {
        echo "🏆 Création des succès par défaut...\n";

        Achievement::createDefaults();

        $count = Achievement::count();
        echo "✅ {$count} succès créés\n";
    }

    /**
     * Créer un utilisateur de test
     */
    protected static function createTestUser(): User
    {
        // Email unique avec timestamp - évite tous les problèmes de doublons
        $testEmail = 'test-gaming-'.time().'-'.rand(1000, 9999).'@example.com';

        $user = User::create([
            'name' => 'Test Gaming Step 2',
            'email' => $testEmail,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        echo "\n👤 Utilisateur créé : {$user->name}\n";
        echo "📧 Email : {$testEmail}\n";
        echo "📊 Niveau initial : {$user->getCurrentLevel()}\n";
        echo "🏆 Succès initiaux : {$user->achievements()->count()}\n";

        return $user;
    }

    /**
     * Tester le déblocage de succès
     */
    protected static function testAchievementUnlocking(User $user): void
    {
        echo "\n--- TEST DÉBLOCAGE DE SUCCÈS ---\n";

        // Créer quelques transactions pour déclencher des succès
        self::createTestTransactions($user);

        // Vérifier les succès
        $unlockedAchievements = $user->checkAndUnlockAchievements();

        echo '🎯 Succès débloqués : '.count($unlockedAchievements)."\n";

        foreach ($unlockedAchievements as $achievement) {
            echo "   🏆 {$achievement->name} (+{$achievement->points} XP)\n";
        }

        if (count($unlockedAchievements) === 0) {
            throw new \Exception('❌ Aucun succès débloqué - Vérifier les critères');
        }

        // Vérifier qu'on ne peut pas débloquer deux fois le même succès
        $secondTry = $user->checkAndUnlockAchievements();

        if (count($secondTry) > 0) {
            throw new \Exception('❌ Des succès ont été débloqués deux fois');
        }

        echo "✅ Test déblocage de succès réussi\n";
    }

    /**
     * Créer des transactions de test pour déclencher des succès
     */
    protected static function createTestTransactions(User $user): void
    {
        // Créer une catégorie de test
        $category = $user->categories()->create([
            'name' => 'Test Category',
            'type' => 'expense',
            'color' => '#3B82F6',
            'is_active' => true,
        ]);

        // Créer plusieurs transactions pour débloquer les succès
        // "Premier pas" (1 transaction) et "Actif" (10 transactions)
        for ($i = 1; $i <= 15; $i++) {
            $user->transactions()->create([
                'category_id' => $category->id,
                'type' => 'expense',
                'amount' => 10 * $i,
                'transaction_date' => now()->subDays($i),
                'description' => "Transaction test {$i}",
                'status' => 'completed',
            ]);
        }

        echo "📝 15 transactions de test créées\n";
        echo '📊 Total transactions user : '.$user->transactions()->count()."\n";
    }

    /**
     * Tester les statistiques avec succès
     */
    protected static function testStatsWithAchievements(User $user): void
    {
        echo "\n--- TEST STATISTIQUES AVEC SUCCÈS ---\n";

        $stats = $user->getGamingStats();

        echo "📊 Niveau : {$stats['level_info']['current_level']}\n";
        echo "⭐ XP Total : {$stats['level_info']['total_xp']}\n";
        echo "🏆 Succès débloqués : {$stats['achievements_count']}\n";
        echo '🎯 Succès récents : '.count($stats['recent_achievements'])."\n";

        if ($stats['achievements_count'] === 0) {
            echo "⚠️  ATTENTION: Aucun succès débloqué\n";
            echo "🔍 Vérification des succès disponibles...\n";

            $availableAchievements = Achievement::active()->get();
            foreach ($availableAchievements as $achievement) {
                $canUnlock = $achievement->checkCriteria($user);
                echo "   🏆 {$achievement->name} - ".($canUnlock ? '✅ PEUT DÉBLOQUER' : '❌ Critères non remplis')."\n";
            }

            throw new \Exception('❌ Aucun succès dans les statistiques');
        }

        // Afficher les succès récents
        foreach ($stats['recent_achievements'] as $achievement) {
            echo "   🏆 {$achievement->name} ({$achievement->rarity_name})\n";
        }

        echo "✅ Test statistiques avec succès réussi\n";
    }

    /**
     * Nettoyer les données de test
     */
    protected static function cleanup(User $user): void
    {
        echo "\n🧹 Nettoyage en cours...\n";

        // 1. D'abord supprimer les succès (pas de dépendances)
        $user->achievements()->detach();
        echo "✅ Succès détachés\n";

        // 2. Supprimer les transactions EN PREMIER (elles dépendent des catégories)
        $transactionCount = $user->transactions()->count();
        $user->transactions()->forceDelete(); // forceDelete pour éviter soft delete
        echo "✅ {$transactionCount} transactions supprimées\n";

        // 3. MAINTENANT on peut supprimer les catégories
        $categoryCount = $user->categories()->count();
        $user->categories()->forceDelete(); // forceDelete pour être sûr
        echo "✅ {$categoryCount} catégories supprimées\n";

        // 4. Supprimer le niveau
        if ($user->level) {
            $user->level->delete();
            echo "✅ Niveau supprimé\n";
        }

        // 5. Enfin supprimer l'utilisateur
        $user->delete(); // Soft delete de l'utilisateur c'est OK
        echo "✅ Utilisateur supprimé\n";

        echo "🧹 Nettoyage terminé avec succès\n";
    }
}
