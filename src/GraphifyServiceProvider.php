<?php

namespace Jansamnan\Graphify;

use Jansamnan\Graphify\Graphify;
use Illuminate\Support\ServiceProvider;

class GraphifyServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        // Publish the configuration file
        $this->publishes([
            __DIR__ . '/../config/graphify.php' => config_path('graphify.php'),
        ], 'config');

        // Load routes if you have any
        // $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Load migrations if you have any
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views if you have any
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'graphify');
    }

    /**
     * Register any package services.
     *
     * @return void
     */
    public function register()
    {
        // Merge the configuration file
        $this->mergeConfigFrom(
            __DIR__ . '/../config/graphify.php',
            'graphify'
        );

        // $this->publishes([
        //     __DIR__ . '/../database/migrations/' => database_path('migrations'),
        // ], 'migrations');

        // Register the main class to use with the facade
        $this->app->singleton('graphify', function ($app) {
            return new Graphify();
        });
    }
}
