<?php

// ✅ Créer un script tests/diagnose.php pour débugger les problèmes

namespace Tests;

require_once __DIR__ . '/../vendor/autoload.php';

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

echo "=== DIAGNOSTIC DES TESTS ===\n\n";

// 1. Vérifier Laravel
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "✅ Laravel bootstrapped\n";

// 2. Vérifier la configuration de base de données
echo "Base de données par défaut: " . config('database.default') . "\n";
echo "Configuration testing disponible: " . (config('database.connections.testing') ? 'OUI' : 'NON') . "\n";

// 3. Vérifier SQLite
try {
    config(['database.default' => 'testing']);
    DB::connection()->getPdo();
    echo "✅ Connexion SQLite fonctionnelle\n";
} catch (\Exception $e) {
    echo "❌ Erreur SQLite: " . $e->getMessage() . "\n";
}

// 4. Vérifier les migrations
try {
    \Illuminate\Support\Facades\Artisan::call('migrate:status');
    echo "✅ Migrations disponibles\n";
} catch (\Exception $e) {
    echo "❌ Erreur migrations: " . $e->getMessage() . "\n";
}

// 5. Vérifier Sanctum
if (class_exists(\Laravel\Sanctum\Sanctum::class)) {
    echo "✅ Sanctum installé\n";
} else {
    echo "❌ Sanctum manquant\n";
}

echo "\n=== FIN DU DIAGNOSTIC ===\n";
