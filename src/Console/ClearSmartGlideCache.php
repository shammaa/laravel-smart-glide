<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

final class ClearSmartGlideCache extends Command
{
    protected $signature = 'smart-glide:clear-cache {--force : Skip confirmation prompt}';

    protected $description = 'Delete cached Smart Glide renditions.';

    public function handle(Filesystem $filesystem): int
    {
        $cachePath = config('smart-glide.cache');

        if (! $cachePath) {
            $this->error('Cache path is not configured (smart-glide.cache).');

            return self::FAILURE;
        }

        if (! $filesystem->exists($cachePath)) {
            $this->info('Cache directory does not exist. Nothing to clear.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete all Smart Glide cached files?')) {
            $this->info('Operation cancelled.');

            return self::SUCCESS;
        }

        $filesystem->deleteDirectory($cachePath);
        $filesystem->makeDirectory($cachePath);

        // Clear manifest from cache store
        if (app()->bound('cache.store')) {
            app('cache.store')->forget('smart_glide_manifest');
        }

        $this->info('Smart Glide cache and manifest cleared.');

        return self::SUCCESS;
    }
}

