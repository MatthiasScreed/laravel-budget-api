<?php

// Fichier: database/nuclear_clean.php
// Usage: php database/nuclear_clean.php

require_once __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

try {
    echo "💣 NETTOYAGE NUCLÉAIRE DE LA BASE DE DONNÉES\n";
    echo "============================================\n";

    // 1. Désactiver toutes les contraintes
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    echo "🔓 Contraintes de clés étrangères désactivées\n";

    // 2. Obtenir TOUTES les tables existantes
    $tables = collect(DB::select('SHOW TABLES'))->map(function ($table) {
        return array_values((array) $table)[0];
    });

    echo '📋 Tables trouvées: '.$tables->count()."\n";
    $tables->each(function ($table) {
        echo "   - $table\n";
    });

    // 3. Supprimer TOUTES les tables sans exception
    echo "\n🗑️  Suppression de toutes les tables...\n";
    foreach ($tables as $table) {
        try {
            DB::statement("DROP TABLE IF EXISTS `$table`");
            echo "✅ Supprimé: $table\n";
        } catch (Exception $e) {
            echo "❌ Erreur sur $table: ".$e->getMessage()."\n";
        }
    }

    // 4. Réactiver les contraintes
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    echo "\n🔒 Contraintes réactivées\n";

    // 5. Vérifier que tout est vide
    $remaining = collect(DB::select('SHOW TABLES'));
    if ($remaining->isEmpty()) {
        echo "🎉 SUCCESS! Base de données complètement vide\n";
        echo "👉 Vous pouvez maintenant lancer: php artisan migrate\n";
    } else {
        echo "⚠️  Il reste des tables:\n";
        $remaining->each(function ($table) {
            $tableName = array_values((array) $table)[0];
            echo "   - $tableName\n";
        });
    }

} catch (Exception $e) {
    echo '💥 ERREUR CRITIQUE: '.$e->getMessage()."\n";
    echo "🔧 Tentative de réactivation des contraintes...\n";

    try {
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        echo "✅ Contraintes réactivées\n";
    } catch (Exception $e2) {
        echo "❌ Impossible de réactiver les contraintes\n";
        echo "👉 Exécutez manuellement: SET FOREIGN_KEY_CHECKS=1;\n";
    }

    echo "\n🆘 SOLUTION DE SECOURS:\n";
    echo 'mysql -u username -p -e "DROP DATABASE '.env('DB_DATABASE').'; CREATE DATABASE '.env('DB_DATABASE').";\"\n";
}
