<?php

declare(strict_types=1);

namespace AppWriters\SmartGlide;

use AppWriters\SmartGlide\Components\SmartBackground;
use AppWriters\SmartGlide\Components\SmartImage;
use AppWriters\SmartGlide\Http\Controllers\ImageController;
use AppWriters\SmartGlide\Support\SmartGlideManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SmartGlideServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/smart-glide.php', 'smart-glide');

        $this->app->singleton(SmartGlideManager::class, function (Container $container): SmartGlideManager {
            return new SmartGlideManager(
                config('smart-glide'),
                $container->make('cache.store')
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/smart-glide.php' => config_path('smart-glide.php'),
        ], 'smart-glide-config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'smart-glide');

        $this->registerRoutes();
        $this->registerComponents();
    }

    private function registerRoutes(): void
    {
        $deliveryPath = trim(config('smart-glide.delivery_path', '/img'), '/');

        Route::middleware(config('smart-glide.route_middleware', []))
            ->prefix($deliveryPath)
            ->group(function () {
                Route::get('{path}', [ImageController::class, 'show'])
                    ->where('path', '.*')
                    ->name('smart-glide.image');
            });
    }

    private function registerComponents(): void
    {
        Blade::component('smart-glide-img', SmartImage::class);
        Blade::component('smart-glide-bg', SmartBackground::class);
    }
}

