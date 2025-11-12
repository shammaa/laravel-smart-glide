<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide;

use Shammaa\SmartGlide\Components\SmartBackground;
use Shammaa\SmartGlide\Components\SmartImage;
use Shammaa\SmartGlide\Components\SmartPicture;
use Shammaa\SmartGlide\Console\ClearSmartGlideCache;
use Shammaa\SmartGlide\Http\Controllers\ImageController;
use Shammaa\SmartGlide\Support\SmartGlideManager;
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
        Blade::component('smart-glide-picture', SmartPicture::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearSmartGlideCache::class,
            ]);

            $this->scheduleCachePurge();
        }
    }

    private function scheduleCachePurge(): void
    {
        $purgeTime = config('smart-glide.cache_strategy.purge_time');

        if (! $purgeTime || ! class_exists(\Illuminate\Console\Scheduling\Schedule::class)) {
            return;
        }

        $this->callAfterResolving(\Illuminate\Console\Scheduling\Schedule::class, function ($schedule) use ($purgeTime): void {
            $schedule->command('smart-glide:clear-cache --force')->dailyAt($purgeTime);
        });
    }
}

