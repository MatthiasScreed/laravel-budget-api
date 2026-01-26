<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TestBridgeConfig extends Command
{
    protected $signature = 'bridge:test-config';

    protected $description = 'Test Bridge API v3 complete flow';

    public function handle()
    {
        $this->info('🔍 Testing Bridge API v3 Complete Flow...');

        $clientId = config('services.bridge.client_id');
        $clientSecret = config('services.bridge.client_secret');

        if (! $clientId || ! $clientSecret) {
            $this->error('❌ Missing credentials');

            return 1;
        }

        $this->info('✅ Credentials OK');
        $this->newLine();

        $baseUrl = 'https://api.bridgeapi.io/v3/aggregation';
        $externalUserId = 'test_user_'.Str::random(10);

        // ÉTAPE 1 : Créer un user (avec external_user_id uniquement)
        $this->info('📝 Step 1: Creating test user...');

        $userResponse = Http::withHeaders([
            'Bridge-Version' => '2025-01-15',
            'Client-Id' => $clientId,
            'Client-Secret' => $clientSecret,
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/users", [
            'external_user_id' => $externalUserId, // ✅ Correct format
        ]);

        if (! $userResponse->successful()) {
            $this->error('❌ User creation failed: '.$userResponse->status());
            $this->line($userResponse->body());

            return 1;
        }

        $user = $userResponse->json();
        $userUuid = $user['uuid'] ?? null;

        $this->info('✅ User created');
        $this->line("   UUID: {$userUuid}");
        $this->line("   External ID: {$externalUserId}");
        $this->newLine();

        // ÉTAPE 2 : Obtenir un access token (avec external_user_id)
        $this->info('🔑 Step 2: Getting access token...');

        $tokenResponse = Http::withHeaders([
            'Bridge-Version' => '2025-01-15',
            'Client-Id' => $clientId,
            'Client-Secret' => $clientSecret,
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/authorization/token", [
            'external_user_id' => $externalUserId, // ✅ Utilise external_user_id
        ]);

        if (! $tokenResponse->successful()) {
            $this->error('❌ Token creation failed: '.$tokenResponse->status());
            $this->line($tokenResponse->body());

            return 1;
        }

        $tokenData = $tokenResponse->json();
        $accessToken = $tokenData['access_token'] ?? null;

        $this->info('✅ Access token obtained (valid 2h)');
        $this->newLine();

        // ÉTAPE 3 : Créer une connect session
        $this->info('🔗 Step 3: Creating connect session...');

        $sessionResponse = Http::withHeaders([
            'Bridge-Version' => '2025-01-15',
            'Client-Id' => $clientId,
            'Client-Secret' => $clientSecret,
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type' => 'application/json',
        ])->post("{$baseUrl}/connect-sessions", [
            'user_email' => 'test.coinquest@gmail.com',
            'callback_url' => config('services.bridge.redirect_url'),
        ]);

        if ($sessionResponse->successful()) {
            $this->info('✅ SUCCESS! Connect session created!');
            $session = $sessionResponse->json();
            $this->newLine();
            $this->line('📊 Session ID: '.($session['id'] ?? 'N/A'));
            $this->line('🔗 Connect URL: '.($session['url'] ?? 'N/A'));
            $this->newLine();
            $this->info('🎉 Bridge API v3 is FULLY WORKING!');
            $this->info('💡 You can now integrate Bridge in your app!');
        } else {
            $this->error('❌ Connect session failed: '.$sessionResponse->status());
            $this->line($sessionResponse->body());

            return 1;
        }

        return 0;
    }
}
