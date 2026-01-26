<?php

// Charger .env
$envFile = __DIR__.'/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
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

echo "🔍 TEST BRIDGE API v3 2025 - BON ENDPOINT\n";
echo "=========================================\n\n";

echo "Base URL: {$baseUrl}\n";
echo "Version: {$version}\n";
echo 'Client-Id: '.substr($clientId, 0, 15)."...\n";
echo 'Client-Secret: '.substr($clientSecret, 0, 15)."...\n\n";

// ==========================================
// TEST 1 : Vérifier que les credentials fonctionnent
// ==========================================

echo "📡 TEST 1/2 : Vérification credentials avec /v3/providers...\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "{$baseUrl}/v3/providers?limit=5&country_code=FR",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Client-Id: {$clientId}",
        "Client-Secret: {$clientSecret}",
        "Bridge-Version: {$version}",
        'Content-Type: application/json',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    echo "✅ Credentials VALIDES (Code 200)\n\n";
} else {
    echo "❌ Credentials INVALIDES (Code {$httpCode})\n";
    echo "Réponse: {$response}\n\n";
    exit(1);
}

// ==========================================
// TEST 2 : Tester le BON endpoint Connect Session
// ==========================================

echo "📡 TEST 2/2 : Test endpoint Connect Session v3 2025...\n";
echo "Endpoint: POST /v3/aggregation/connect-sessions\n\n";

// Body minimal selon documentation Bridge v3 2025
$body = json_encode([
    'redirect_url' => 'https://example.com/callback',
    'account_types' => 'all',
    'user_email' => 'test@example.com',
]);

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "{$baseUrl}/v3/aggregation/connect-sessions",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => [
        "Client-Id: {$clientId}",
        "Client-Secret: {$clientSecret}",
        "Bridge-Version: {$version}",
        'Content-Type: application/json',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "Status Code: {$httpCode}\n";

if ($httpCode === 200 || $httpCode === 201) {
    echo "✅ SUCCÈS ! L'endpoint fonctionne !\n\n";

    $data = json_decode($response, true);

    if (isset($data['url'])) {
        echo "🎉 URL Bridge Connect reçue !\n";
        echo "URL: {$data['url']}\n";
        echo 'ID: '.($data['id'] ?? 'N/A')."\n\n";

        echo "✅ TON INTÉGRATION VA FONCTIONNER !\n";
        echo "Tu peux maintenant tester dans ton application.\n";
    } else {
        echo "⚠️  Réponse reçue mais URL manquante:\n";
        echo json_encode($data, JSON_PRETTY_PRINT)."\n";
    }

} elseif ($httpCode === 400 || $httpCode === 422) {
    echo "⚠️  L'endpoint existe mais les paramètres sont incorrects\n\n";

    $data = json_decode($response, true);
    echo "Réponse Bridge:\n";
    echo json_encode($data, JSON_PRETTY_PRINT)."\n\n";

    if (isset($data['errors'])) {
        echo "💡 Erreurs détectées:\n";
        foreach ($data['errors'] as $error) {
            echo '  - '.($error['message'] ?? json_encode($error))."\n";
        }
    }

} elseif ($httpCode === 401) {
    echo "❌ ERREUR 401 : Credentials invalides\n\n";
    echo "💡 SOLUTION:\n";
    echo "1. Va sur https://dashboard.bridgeapi.io\n";
    echo "2. Assure-toi d'être en mode SANDBOX\n";
    echo "3. Génère de nouvelles clés API\n";
    echo "4. Mets-les à jour dans .env\n";

} elseif ($httpCode === 404) {
    echo "❌ ERREUR 404 : Endpoint introuvable\n\n";
    echo "Cela ne devrait PAS arriver avec la doc officielle Bridge.\n";
    echo "Vérifie que BRIDGE_BASE_URL = https://api.bridgeapi.io\n\n";
    echo "Réponse complète:\n{$response}\n";

} else {
    echo "❌ ERREUR {$httpCode}\n\n";
    echo "Réponse complète:\n{$response}\n";

    if ($error) {
        echo "\nErreur cURL: {$error}\n";
    }
}

echo "\n";
echo "=========================================\n";
echo "📚 DOCUMENTATION BRIDGE v3 2025\n";
echo "=========================================\n";
echo "→ https://docs.bridgeapi.io/docs/migration-guide-from-2021-to-2025-version\n";
echo "→ https://docs.bridgeapi.io/reference/connect-sessions\n";
echo "\n";
