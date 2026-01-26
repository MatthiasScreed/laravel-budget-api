<!DOCTYPE html>
<html>
<head>
    <title>Test Bridge API - CoinQuest</title>
    <style>
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
            background: #0f172a;
            color: #e2e8f0;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .test-section {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        pre {
            background: #0f172a;
            border: 1px solid #334155;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 13px;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        .status-success {
            background: #10b981;
            color: white;
        }
        .status-error {
            background: #ef4444;
            color: white;
        }
    </style>
</head>
<body>
<div class="header">
    <h1>🔍 Diagnostic Bridge API</h1>
    <p style="opacity: 0.9; margin: 10px 0 0 0;">CoinQuest Banking Integration</p>
    <p style="opacity: 0.7; font-size: 14px; margin: 5px 0 0 0;">{{ $results['timestamp'] }}</p>
</div>

<div class="test-section">
    <h2>📊 Statut Global</h2>
    <p class="status-badge {{ str_contains($results['overall_status'], '✅') ? 'status-success' : 'status-error' }}">
        {{ $results['overall_status'] }}
    </p>
</div>

@foreach($results['tests'] as $testName => $testData)
    <div class="test-section">
        <h3>{{ ucfirst(str_replace('_', ' ', $testName)) }}</h3>

        @if(isset($testData['success']))
            <p class="{{ $testData['success'] ? 'success' : 'error' }}">
                {{ $testData['success'] ? '✅ Succès' : '❌ Échec' }}
            </p>
        @endif

        @if(isset($testData['message']))
            <p class="{{ str_contains($testData['message'], '✅') ? 'success' : 'error' }}" style="font-weight: bold;">
                {{ $testData['message'] }}
            </p>
        @endif

        <pre>{{ json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
    </div>
@endforeach>

<div class="test-section">
    <h3>🔧 Actions recommandées si échec</h3>
    <ol style="line-height: 1.8;">
        <li>Vérifiez vos credentials sur <a href="https://dashboard.bridgeapi.io" target="_blank" style="color: #60a5fa;">Bridge Dashboard</a></li>
        <li>Assurez-vous d'utiliser les credentials <strong>SANDBOX</strong></li>
        <li>Exécutez: <code>php artisan config:clear</code></li>
        <li>Redémarrez Laravel Herd</li>
        <li>Vérifiez que votre IP n'est pas bloquée</li>
    </ol>
</div>
</body>
</html>
