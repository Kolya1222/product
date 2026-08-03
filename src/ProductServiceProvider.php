<?php

namespace roilafx\Product;

use EvolutionCMS\ServiceProvider;
use Illuminate\Support\Facades\Route;
use roilafx\Product\Console\Commands\GenerateTestData;

class ProductServiceProvider extends ServiceProvider
{
    protected $namespace = 'product';
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->loadPluginsFrom(
            dirname(__DIR__) . '/plugins/'
        );

        $this->app->singleton('product.data', function ($app) {
            return new \roilafx\Product\Services\ProductDataService();
        });

        $this->loadViewsFrom(
            dirname(__DIR__) . '/views/',
            'products'
        );

        $this->loadMigrationsFrom(
            dirname(__DIR__) . '/migrations/'
        );

        Route::group(['middleware' => 'bindings'], function () {
            $this->loadRoutesFrom(
                dirname(__DIR__) . '/routes/web.php'
            );
        });

        $this->publishes([
            __DIR__ . '/../publishable/assets' => MODX_BASE_PATH . 'assets/',
        ]);

        $this->app->registerRoutingModule(
            'Пресеты атрибутов',
            __DIR__ . '/../routes/preset-module.php',
            'fa fa-magic'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Регистрируем консольную команду
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTestData::class,
            ]);
        }
    }
}
