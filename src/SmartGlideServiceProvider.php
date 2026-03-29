<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide;

use Shammaa\SmartGlide\Components\SmartBackground;
use Shammaa\SmartGlide\Components\SmartImage;
use Shammaa\SmartGlide\Components\SmartPicture;
use Shammaa\SmartGlide\Console\ClearSmartGlideCache;
use Shammaa\SmartGlide\Console\WarmSmartGlideCache;
use Shammaa\SmartGlide\Console\SmartGlideStats;
use Shammaa\SmartGlide\Http\Controllers\ImageController;
use Shammaa\SmartGlide\Http\Controllers\ImageDataController;
use Shammaa\SmartGlide\Http\Controllers\AdminController;
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

        $this->app->alias(SmartGlideManager::class, 'smart-glide');
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
        $dataPath     = trim(config('smart-glide.data_path', '/img-data'), '/');
        $middleware   = config('smart-glide.route_middleware', []);

        // Image delivery route  →  GET /img/{path}
        Route::middleware($middleware)
            ->prefix($deliveryPath)
            ->group(function () {
                Route::get('{path}', [ImageController::class, 'show'])
                    ->where('path', '.*')
                    ->name('smart-glide.image');
            });

        // Headless JSON API  →  GET /img-data/{path}  (for React / Next.js)
        if (config('smart-glide.api.enabled', true)) {
            Route::middleware(array_merge($middleware, config('smart-glide.api.middleware', [])))
                ->prefix($dataPath)
                ->group(function () {
                    Route::get('{path}', [ImageDataController::class, 'show'])
                        ->where('path', '.*')
                        ->name('smart-glide.image-data');
                });
        }

        // Admin Management API  →  /img-admin  (callable from your website/dashboard)
        if (config('smart-glide.admin.enabled', false)) {
            $adminPath = trim(config('smart-glide.admin.path', '/img-admin'), '/');
            $adminMiddleware = array_merge($middleware, config('smart-glide.admin.middleware', []));

            Route::middleware($adminMiddleware)
                ->prefix($adminPath)
                ->name('smart-glide.admin.')
                ->group(function () {
                    Route::get('stats',            [AdminController::class, 'stats'])->name('stats');
                    Route::get('stats/manifest',   [AdminController::class, 'manifest'])->name('manifest');
                    Route::post('warm',            [AdminController::class, 'warm'])->name('warm');
                    Route::post('warm-all',        [AdminController::class, 'warmAll'])->name('warm-all');
                    Route::post('forget',          [AdminController::class, 'forget'])->name('forget');
                    Route::get('exists',           [AdminController::class, 'exists'])->name('exists');
                    Route::get('dimensions',       [AdminController::class, 'dimensions'])->name('dimensions');
                });
        }
    }

    private function registerComponents(): void
    {
        Blade::component('smart-glide-img', SmartImage::class);
        Blade::component('smart-glide-bg', SmartBackground::class);
        Blade::component('smart-glide-picture', SmartPicture::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ClearSmartGlideCache::class,
                WarmSmartGlideCache::class,
                SmartGlideStats::class,
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

