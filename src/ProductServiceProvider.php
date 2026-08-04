<?php

namespace roilafx\Product;

use EvolutionCMS\ServiceProvider;
use Illuminate\Support\Facades\Route;
use roilafx\Product\Console\Commands\GenerateTestData;
use roilafx\Product\Models\ProductVariant;
use roilafx\Product\Models\VariantAttributeValue;
use roilafx\Product\Observers\ProductVariantObserver;
use roilafx\Product\Observers\VariantAttributeValueObserver;

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
        $this->app->singleton('product.data', function ($app) {
            return new \roilafx\Product\Services\ProductDataService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadPluginsFrom(
            dirname(__DIR__) . '/plugins/'
        );
        
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateTestData::class,
            ]);
        }

        ProductVariant::observe(ProductVariantObserver::class);
        VariantAttributeValue::observe(VariantAttributeValueObserver::class);

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
}
