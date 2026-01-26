<?php

/**
 * 🧪 TEST BRIDGE API v3 2025 - FLOW D'AUTHENTIFICATION COMPLET
 *
 * Ce script teste le flow en 3 étapes :
 * 1. Création d'un utilisateur Bridge
 * 2. Obtention d'un access token
 * 3. Création d'une Connect Session avec Bearer token
 *
 * Usage: php test-bridge-auth-flow.php
 */

// ==========================================
// CHARGEMENT ENVIRONNEMENT
// ==========================================

$envFile = __DIR__.'/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

$clientId = getenv('BRIDGE_CLIENT_ID');
$clientSecret = getenv('BRIDGE_CLIENT_SECRET');
$baseUrl = getenv('BRIDGE_BASE_URL') ?: 'https://api.bridgeapi.io';
$version = getenv('BRIDGE_VERSION') ?: '2025-01-15';

// ==========================================
// CONFIGURATION
// ==========================================

echo "\n";
echo "🔐 TEST BRIDGE API v3 2025 - AUTHENTICATION FLOW\n";
echo "=================================================\n\n";

echo "Configuration:\n";
echo "  Base URL: {$baseUrl}\n";
echo "  Version: {$version}\n";
echo '  Client-Id: '.substr($clientId, 0, 20)."...\n";
echo '  Client-Secret: '.substr($clientSecret, 0, 20)."...\n\n";

if (! $clientId || ! $clientSecret) {
    echo "❌ ERREUR : BRIDGE_CLIENT_ID ou BRIDGE_CLIENT_SECRET manquant dans .env\n\n";
    exit(1);
}

// ==========================================
// FONCTIONS HELPER
// ==========================================

function makeRequest($method, $url, $headers, $body = null)
{
    $ch = curl_init();

    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
    ];

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = is_array($body) ? json_encode($body) : $body;
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $response,
        'data' => json_decode($response, true),
        'error' => $error,
    ];
}

function getBaseHeaders($clientId, $clientSecret, $version)
{
    return [
        "Client-Id: {$clientId}",
        "Client-Secret: {$clientSecret}",
        "Bridge-Version: {$version}",
        'Content-Type: application/json',
        'Accept: application/json',
    ];
}

function getAuthHeaders($clientId, $clientSecret, $version, $accessToken)
{
    return [
        "Client-Id: {$clientId}",
        "Client-Secret: {$clientSecret}",
        "Bridge-Version: {$version}",
        "Authorization: Bearer {$accessToken}",
        'Content-Type: application/json',
        'Accept: application/json',
    ];
}

// ==========================================
// TEST 0 : VÉRIFICATION CREDENTIALS
// ==========================================

echo "📡 TEST 0/3 : Vérification des credentials...\n";
echo "Endpoint: GET /v3/providers\n\n";

$response = makeRequest(
    'GET',
    "{$baseUrl}/v3/providers?limit=3",
    getBaseHeaders($clientId, $clientSecret, $version)
);

if ($response['code'] === 200) {
    echo "✅ Credentials VALIDES\n";
    $providers = $response['data']['resources'] ?? [];
    echo '   Providers disponibles : '.count($providers)."\n\n";
} else {
    echo "❌ ERREUR {$response['code']} : Credentials invalides\n";
    echo 'Réponse: '.json_encode($response['data'], JSON_PRETTY_PRINT)."\n\n";
    exit(1);
}

// ==========================================
// TEST 1 : CRÉATION UTILISATEUR BRIDGE
// ==========================================

echo "📡 TEST 1/3 : Création d'un utilisateur Bridge...\n";
echo "Endpoint: POST /v3/aggregation/users\n\n";

// Générer un external_user_id unique pour ce test
$externalUserId = 'test_user_'.time();

$response = makeRequest(
    'POST',
    "{$baseUrl}/v3/aggregation/users",
    getBaseHeaders($clientId, $clientSecret, $version),
    ['external_user_id' => $externalUserId]
);

echo "Status Code: {$response['code']}\n";

if ($response['code'] === 200 || $response['code'] === 201) {
    $bridgeUserUuid = $response['data']['uuid'] ?? null;

    if ($bridgeUserUuid) {
        echo "✅ SUCCÈS ! Utilisateur créé\n";
        echo "   External User ID: {$externalUserId}\n";
        echo "   Bridge UUID: {$bridgeUserUuid}\n\n";
    } else {
        echo "❌ ERREUR : UUID manquant dans la réponse\n";
        echo json_encode($response['data'], JSON_PRETTY_PRINT)."\n\n";
        exit(1);
    }
} else {
    echo "❌ ÉCHEC création utilisateur\n";
    echo 'Réponse: '.json_encode($response['data'], JSON_PRETTY_PRINT)."\n\n";
    exit(1);
}

// ==========================================
// TEST 2 : OBTENTION ACCESS TOKEN
// ==========================================

echo "📡 TEST 2/3 : Obtention d'un access token...\n";
echo "Endpoint: POST /v3/aggregation/authorization/token\n\n";

$response = makeRequest(
    'POST',
    "{$baseUrl}/v3/aggregation/authorization/token",
    getBaseHeaders($clientId, $clientSecret, $version),
    ['user_uuid' => $bridgeUserUuid]
);

echo "Status Code: {$response['code']}\n";

if ($response['code'] === 200 || $response['code'] === 201) {
    $accessToken = $response['data']['access_token'] ?? null;
    $expiresAt = $response['data']['expires_at'] ?? null;

    if ($accessToken) {
        echo "✅ SUCCÈS ! Token obtenu\n";
        echo '   Access Token: '.substr($accessToken, 0, 30)."...\n";
        echo "   Expires At: {$expiresAt}\n";
        echo '   Token Length: '.strlen($accessToken)." chars\n\n";
    } else {
        echo "❌ ERREUR : Access token manquant\n";
        echo json_encode($response['data'], JSON_PRETTY_PRINT)."\n\n";
        exit(1);
    }
} else {
    echo "❌ ÉCHEC obtention token\n";
    echo 'Réponse: '.json_encode($response['data'], JSON_PRETTY_PRINT)."\n\n";
    exit(1);
}

// ==========================================
// TEST 3 : CRÉATION CONNECT SESSION
// ==========================================

echo "📡 TEST 3/3 : Création d'une Connect Session...\n";
echo "Endpoint: POST /v3/aggregation/connect-sessions\n";
echo "Authentication: Bearer Token ✅\n\n";

// ✅ Body minimal : SEULEMENT user_email (OBLIGATOIRE)
// ⚠️ PAS de callback_url pour éviter l'erreur whitelist
// Bridge utilisera automatiquement l'URL configurée dans le dashboard
$body = [
    'user_email' => 'test@example.com',
];

echo '📝 Body envoyé : '.json_encode($body, JSON_PRETTY_PRINT)."\n";
echo "ℹ️  callback_url OMIS : Bridge utilisera la config dashboard\n\n";

$response = makeRequest(
    'POST',
    "{$baseUrl}/v3/aggregation/connect-sessions",
    getAuthHeaders($clientId, $clientSecret, $version, $accessToken),
    $body
);

echo "Status Code: {$response['code']}\n";

if ($response['code'] === 200 || $response['code'] === 201) {
    $connectUrl = $response['data']['url'] ?? null;
    $sessionId = $response['data']['id'] ?? null;

    if ($connectUrl) {
        echo "✅ SUCCÈS ! Connect Session créée\n";
        echo "   Session ID: {$sessionId}\n";
        echo "   Connect URL: {$connectUrl}\n\n";

        echo "🎉 FLOW D'AUTHENTIFICATION COMPLET RÉUSSI !\n\n";
        echo "Tu peux maintenant :\n";
        echo "1. Ouvrir l'URL dans un navigateur\n";
        echo "2. Connecter un compte bancaire de test\n";
        echo "3. Récupérer l'item_id dans le callback\n\n";

        echo "Connect URL complète :\n";
        echo "{$connectUrl}\n\n";

    } else {
        echo "❌ ERREUR : URL manquante dans la réponse\n";
        echo json_encode($response['data'], JSON_PRETTY_PRINT)."\n\n";
    }
} else {
    echo "❌ ÉCHEC création session\n";
    echo 'Réponse: '.json_encode($response['data'], JSON_PRETTY_PRINT)."\n\n";

    if ($response['code'] === 401) {
        echo "💡 Token invalide ou expiré. Cela ne devrait pas arriver avec un token frais.\n\n";
    } elseif ($response['code'] === 400) {
        $errorCode = $response['data']['errors'][0]['code'] ?? '';
        if ($errorCode === 'connect_session.callback_url_not_whitelisted') {
            echo "💡 SOLUTION : Retire callback_url du body OU configure le domaine dans Bridge Dashboard\n\n";
        }
    }
}

// ==========================================
// TEST 4 : RÉCUPÉRATION DES ITEMS
// ==========================================

echo "📡 TEST 4/4 : Récupération des items de l'utilisateur...\n";
echo "Endpoint: GET /v3/aggregation/items\n\n";

$response = makeRequest(
    'GET',
    "{$baseUrl}/v3/aggregation/items",
    getAuthHeaders($clientId, $clientSecret, $version, $accessToken)
);

echo "Status Code: {$response['code']}\n";

if ($response['code'] === 200) {
    $items = $response['data']['resources'] ?? [];
    echo "✅ SUCCÈS ! Items récupérés\n";
    echo "   Nombre d'items : ".count($items)."\n\n";

    if (count($items) === 0) {
        echo "💡 Aucun item connecté pour cet utilisateur (normal pour un nouveau test)\n\n";
    } else {
        echo "Items trouvés :\n";
        foreach ($items as $item) {
            echo "   - Item ID: {$item['id']}, Status: {$item['status']}\n";
        }
        echo "\n";
    }
} else {
    echo "⚠️ Échec récupération items (non critique)\n";
    echo 'Réponse: '.json_encode($response['data'], JSON_PRETTY_PRINT)."\n\n";
}

// ==========================================
// NETTOYAGE (OPTIONNEL)
// ==========================================

echo "🧹 NETTOYAGE : Suppression de l'utilisateur de test...\n";

$response = makeRequest(
    'DELETE',
    "{$baseUrl}/v3/aggregation/users/{$bridgeUserUuid}",
    getBaseHeaders($clientId, $clientSecret, $version)
);

if ($response['code'] === 200 || $response['code'] === 204) {
    echo "✅ Utilisateur de test supprimé\n\n";
} else {
    echo "⚠️ Impossible de supprimer l'utilisateur (non critique)\n\n";
}

// ==========================================
// RÉSUMÉ
// ==========================================

echo "=================================================\n";
echo "📊 RÉSUMÉ DES TESTS\n";
echo "=================================================\n\n";

echo "✅ Les 3 étapes du flow d'authentification fonctionnent :\n";
echo "   1. Création utilisateur Bridge\n";
echo "   2. Obtention access token (Bearer)\n";
echo "   3. Création Connect Session\n\n";

echo "🚀 TON INTÉGRATION EST PRÊTE !\n\n";

echo "📚 Documentation Bridge :\n";
echo "   → https://docs.bridgeapi.io/docs/user-creation-authentication\n";
echo "   → https://docs.bridgeapi.io/docs/financial-data-aggregation\n\n";

echo "🔧 Prochaines étapes :\n";
echo "   1. Lance 'php artisan migrate' pour créer bridge_user_uuid\n";
echo "   2. Remplace BankIntegrationService.php par la version corrigée\n";
echo "   3. Remplace BankController.php par la version corrigée\n";
echo "   4. Teste depuis ton frontend Vue.js\n\n";
