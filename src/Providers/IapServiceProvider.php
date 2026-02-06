<?php

namespace Bijay\Iap\Providers;

use Bijay\Iap\Services\IapManager;
use Illuminate\Support\ServiceProvider;

class IapServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton('iap', function ($app) {
            return new IapManager();
        });

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/iap.php',
            'iap'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config file
        $this->publishes([
            __DIR__ . '/../../config/iap.php' => config_path('iap.php'),
        ], 'iap-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../../database/migrations/create_iap_subscriptions_table.php' => database_path('migrations/' . date('Y_m_d_His') . '_create_iap_subscriptions_table.php'),
        ], 'iap-migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
    }
}

