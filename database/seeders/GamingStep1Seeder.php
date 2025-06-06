<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class GamingStep1Seeder extends Seeder
{

    /**
     * Run the database seeds.
     * ❌ ERREUR 2 : Méthode run() était vide !
     */
    public function run(): void
    {
        $this->test(); // Appeler la méthode de test
    }

    /**
     * Tester le système de niveaux
     */
    public function test(): void
    {
        echo "🎮 TEST DU SYSTÈME GAMING - ÉTAPE 1 : NIVEAUX\n";
        echo "=" . str_repeat("=", 50) . "\n";

        // Créer un utilisateur de test
        $user = $this->createTestUser();

        // Tester l'ajout d'XP
        $this->testXpAddition($user);

        // Tester la montée de niveau
        $this->testLevelUp($user);

        // Tester les statistiques
        $this->testStats($user);

        // Nettoyer
        $this->cleanup($user);

        echo "\n✅ TOUS LES TESTS SONT PASSÉS !\n";
    }

    /**
     * Créer un utilisateur de test
     */
    protected function createTestUser(): User
    {
        // Supprimer l'utilisateur s'il existe déjà
        User::where('email', 'test-gaming-step1@example.com')->delete();

        $user = User::create([
            'name' => 'Test Gaming Step 1',
            'email' => 'test-gaming-step1@example.com',
            'password' => bcrypt('password'),
            'email_verified_at' => now()
        ]);

        echo "👤 Utilisateur créé : {$user->name}\n";
        echo "📊 Niveau initial : {$user->getCurrentLevel()}\n";
        echo "🏆 Titre : {$user->getTitle()}\n";

        return $user;
    }

    /**
     * Tester l'ajout d'XP
     */
    protected function testXpAddition(User $user): void
    {
        echo "\n--- TEST AJOUT XP ---\n";

        $result = $user->addXp(25);

        echo "⭐ XP ajoutés : 25\n";
        echo "📈 Progression : " . round($result['progress_percentage'], 1) . "%\n";
        echo "🎯 XP total : {$result['total_xp']}\n";

        // ❌ ERREUR 5 : assert() peut causer des problèmes en production
        if ($result['xp_added'] !== 25) {
            throw new \Exception('❌ XP ajoutés incorrects');
        }

        if ($result['leveled_up'] !== false) {
            throw new \Exception('❌ Ne devrait pas monter de niveau');
        }

        echo "✅ Test ajout XP réussi\n";
    }

    /**
     * Tester la montée de niveau
     */
    protected function testLevelUp(User $user): void
    {
        echo "\n--- TEST MONTÉE DE NIVEAU ---\n";

        $result = $user->addXp(100); // Devrait faire monter au niveau 2

        echo "⭐ XP ajoutés : 100\n";
        echo "🆙 Montée de niveau : " . ($result['leveled_up'] ? 'OUI' : 'NON') . "\n";
        echo "📊 Nouveau niveau : {$result['new_level']}\n";
        echo "🏆 Nouveau titre : {$user->getTitle()}\n";

        if ($result['leveled_up'] !== true) {
            throw new \Exception('❌ Devrait monter de niveau');
        }

        if ($result['new_level'] < 2) {
            throw new \Exception('❌ Devrait être niveau 2 ou plus');
        }

        echo "✅ Test montée de niveau réussi\n";
    }

    /**
     * Tester les statistiques
     */
    protected function testStats(User $user): void
    {
        echo "\n--- TEST STATISTIQUES ---\n";

        $stats = $user->getGamingStats();

        echo "📊 Niveau : {$stats['level_info']['current_level']}\n";
        echo "⭐ XP Total : {$stats['level_info']['total_xp']}\n";
        echo "📈 Progression : " . round($stats['level_info']['progress_percentage'], 1) . "%\n";
        echo "🏆 Titre : {$stats['level_info']['title']}\n";

        if (!isset($stats['level_info'])) {
            throw new \Exception('❌ Les infos de niveau doivent exister');
        }

        if ($stats['level_info']['total_xp'] <= 0) {
            throw new \Exception('❌ XP total doit être > 0');
        }

        echo "✅ Test statistiques réussi\n";
    }


    /**
     * Nettoyer les données de test
     */
    protected function cleanup(User $user): void
    {
        $user->delete();
        echo "\n🧹 Utilisateur de test supprimé\n";
    }
}
