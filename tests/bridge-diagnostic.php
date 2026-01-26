<?php

/**
 * Script de diagnostic Bridge API
 * Usage: php tests/bridge-diagnostic.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 DIAGNOSTIC BRIDGE API\n";
echo str_repeat('=', 50)."\n\n";

// Configuration
$clientId = config('services.bridge.client_id');
$clientSecret = config('services.bridge.client_secret');

if (! $clientId || ! $clientSecret) {
    echo "❌ Credentials Bridge manquantes !\n";
    echo "Vérifier config/services.php\n";
    exit(1);
}

echo "✅ Credentials trouvées\n";
echo 'Client ID: '.substr($clientId, 0, 10)."...\n\n";

// Test 1: Connectivité Bridge API
echo "📡 Test 1: Connectivité Bridge API\n";
echo str_repeat('-', 50)."\n";

$start = microtime(true);
try {
    $response = \Illuminate\Support\Facades\Http::timeout(10)
        ->get('https://api.bridgeapi.io/v3/health');

    $duration = microtime(true) - $start;

    if ($response->successful()) {
        echo "✅ Bridge API accessible\n";
        echo '⏱️  Temps: '.round($duration * 1000)."ms\n";
    } else {
        echo "⚠️  Status: {$response->status()}\n";
    }
} catch (\Exception $e) {
    $duration = microtime(true) - $start;
    echo "❌ Erreur: {$e->getMessage()}\n";
    echo '⏱️  Temps avant timeout: '.round($duration, 1)."s\n";
}

echo "\n";

// Test 2: Authentification
echo "📡 Test 2: Authentification Bridge\n";
echo str_repeat('-', 50)."\n";

$user = \App\Models\User::first();

if (! $user) {
    echo "❌ Aucun user en BDD\n";
    exit(1);
}

if (! $user->bridge_user_uuid) {
    echo "⚠️  Pas de Bridge UUID, création...\n";

    $start = microtime(true);
    try {
        $response = \Illuminate\Support\Facades\Http::timeout(30)
            ->withHeaders([
                'Client-Id' => $clientId,
                'Client-Secret' => $clientSecret,
                'Bridge-Version' => '2025-01-15',
                'Content-Type' => 'application/json',
            ])->post(
                'https://api.bridgeapi.io/v3/aggregation/users',
                ['external_user_id' => (string) $user->id]
            );

        $duration = microtime(true) - $start;

        if ($response->status() === 409) {
            echo "ℹ️  User existe déjà\n";
            // Récupérer l'UUID
            $listResponse = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders([
                    'Client-Id' => $clientId,
                    'Client-Secret' => $clientSecret,
                    'Bridge-Version' => '2025-01-15',
                ])->get('https://api.bridgeapi.io/v3/aggregation/users');

            $users = $listResponse->json()['resources'] ?? [];
            $bridgeUser = collect($users)->firstWhere(
                'external_user_id',
                (string) $user->id
            );

            if ($bridgeUser) {
                $user->bridge_user_uuid = $bridgeUser['uuid'];
                $user->save();
                echo "✅ UUID récupéré: {$bridgeUser['uuid']}\n";
            }
        } elseif ($response->successful()) {
            $userData = $response->json();
            $user->bridge_user_uuid = $userData['uuid'];
            $user->save();
            echo "✅ User créé: {$userData['uuid']}\n";
        } else {
            echo "❌ Erreur: {$response->body()}\n";
        }

        echo '⏱️  Temps: '.round($duration * 1000)."ms\n";

    } catch (\Exception $e) {
        $duration = microtime(true) - $start;
        echo "❌ Erreur: {$e->getMessage()}\n";
        echo '⏱️  Temps avant timeout: '.round($duration, 1)."s\n";
    }
} else {
    echo "✅ Bridge UUID existant: {$user->bridge_user_uuid}\n";
}

echo "\n";

// Test 3: Obtention token
echo "📡 Test 3: Obtention token\n";
echo str_repeat('-', 50)."\n";

$start = microtime(true);
try {
    $response = \Illuminate\Support\Facades\Http::timeout(30)
        ->withHeaders([
            'Client-Id' => $clientId,
            'Client-Secret' => $clientSecret,
            'Bridge-Version' => '2025-01-15',
            'Content-Type' => 'application/json',
        ])->post(
            'https://api.bridgeapi.io/v3/aggregation/authorization/token',
            ['user_uuid' => $user->bridge_user_uuid]
        );

    $duration = microtime(true) - $start;

    if ($response->successful()) {
        $token = $response->json()['access_token'];
        echo "✅ Token obtenu\n";
        echo 'Token: '.substr($token, 0, 30)."...\n";
        echo '⏱️  Temps: '.round($duration * 1000)."ms\n";

        // Test 4: Connect Session SANS callback
        echo "\n";
        echo "📡 Test 4: Connect Session SANS callback\n";
        echo str_repeat('-', 50)."\n";

        $start4 = microtime(true);
        try {
            $sessionResponse = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders([
                    'Client-Id' => $clientId,
                    'Client-Secret' => $clientSecret,
                    'Bridge-Version' => '2025-01-15',
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                ])->post(
                    'https://api.bridgeapi.io/v3/aggregation/connect-sessions',
                    ['user_email' => $user->email]
                );

            $duration4 = microtime(true) - $start4;

            if ($sessionResponse->successful()) {
                $sessionData = $sessionResponse->json();
                echo "✅ Session créée SANS callback\n";
                echo "Session ID: {$sessionData['id']}\n";
                echo "URL: {$sessionData['url']}\n";
                echo '⏱️  Temps: '.round($duration4 * 1000)."ms\n";
            } else {
                echo "❌ Erreur: {$sessionResponse->body()}\n";
                echo '⏱️  Temps: '.round($duration4 * 1000)."ms\n";
            }
        } catch (\Exception $e) {
            $duration4 = microtime(true) - $start4;
            echo "❌ Timeout: {$e->getMessage()}\n";
            echo '⏱️  Temps avant timeout: '.round($duration4, 1)."s\n";
        }

        // Test 5: Connect Session AVEC callback
        echo "\n";
        echo "📡 Test 5: Connect Session AVEC callback\n";
        echo str_repeat('-', 50)."\n";

        $callbackUrl = config('app.url').'/api/bank/callback';
        echo "Callback URL: $callbackUrl\n\n";

        $start5 = microtime(true);
        try {
            $sessionResponse2 = \Illuminate\Support\Facades\Http::timeout(30)
                ->withHeaders([
                    'Client-Id' => $clientId,
                    'Client-Secret' => $clientSecret,
                    'Bridge-Version' => '2025-01-15',
                    'Authorization' => "Bearer $token",
                    'Content-Type' => 'application/json',
                ])->post(
                    'https://api.bridgeapi.io/v3/aggregation/connect-sessions',
                    [
                        'user_email' => $user->email,
                        'callback_url' => $callbackUrl,
                    ]
                );

            $duration5 = microtime(true) - $start5;

            if ($sessionResponse2->successful()) {
                echo "✅ Session créée AVEC callback\n";
                echo '⏱️  Temps: '.round($duration5 * 1000)."ms\n";
            } else {
                echo "❌ Erreur: {$sessionResponse2->body()}\n";
                echo '⏱️  Temps: '.round($duration5 * 1000)."ms\n";
            }
        } catch (\Exception $e) {
            $duration5 = microtime(true) - $start5;
            echo "❌ Timeout: {$e->getMessage()}\n";
            echo '⏱️  Temps avant timeout: '.round($duration5, 1)."s\n";
        }

    } else {
        echo "❌ Erreur token: {$response->body()}\n";
        echo '⏱️  Temps: '.round($duration * 1000)."ms\n";
    }

} catch (\Exception $e) {
    $duration = microtime(true) - $start;
    echo "❌ Timeout: {$e->getMessage()}\n";
    echo '⏱️  Temps avant timeout: '.round($duration, 1)."s\n";
}

echo "\n";
echo str_repeat('=', 50)."\n";
echo "✅ Diagnostic terminé\n";
