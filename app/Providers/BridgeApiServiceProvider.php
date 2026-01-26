<?php

namespace App\Providers;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;

class BridgeApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('bridge.client', function ($app) {
            return new Client([
                'base_uri' => config('banking.bridge.api_url'),
                'timeout' => config('banking.bridge.timeout', 30),
                'headers' => [
                    'Client-Id' => config('banking.bridge.client_id'),
                    'Client-Secret' => config('banking.bridge.client_secret'),
                    'Bridge-Version' => config('banking.bridge.version'),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/banking.php' => config_path('banking.php'),
        ], 'banking-config');
    }
}
