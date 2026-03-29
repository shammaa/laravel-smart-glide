<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Console;

use Illuminate\Console\Command;
use Shammaa\SmartGlide\Support\SmartGlideManager;

/**
 * Warm the Smart Glide cache for a specific image or all images in the source directory.
 *
 * Usage:
 *   php artisan smart-glide:warm products/phone.jpg
 *   php artisan smart-glide:warm products/phone.jpg --profile=hero --widths=640,960,1280
 *   php artisan smart-glide:warm --all --profile=thumbnail
 */
final class WarmSmartGlideCache extends Command
{
    protected $signature = 'smart-glide:warm
        {path? : Relative image path to warm (e.g. products/phone.jpg)}
        {--all : Warm all images found in the source directory}
        {--profile= : Profile to apply (default, hero, thumbnail, …)}
        {--widths= : Comma-separated widths to generate (e.g. 640,960,1280)}
        {--ext=jpg,jpeg,png,webp,avif : Comma-separated extensions when using --all}';

    protected $description = 'Pre-warm (process & cache) Smart Glide image renditions';

    public function __construct(private readonly SmartGlideManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $widths  = $this->parseWidths();
        $params  = $this->parseParams();
        $profile = $this->option('profile');

        if ($profile) {
            $params['profile'] = $profile;
        }

        if ($this->option('all')) {
            return $this->warmAll($widths, $params);
        }

        $path = $this->argument('path');

        if (! $path) {
            $this->error('Provide a <path> argument or use --all.');
            return self::FAILURE;
        }

        $this->warmSingle((string) $path, $widths, $params);

        return self::SUCCESS;
    }

    // ── Internals ──────────────────────────────────────────────────────────────

    private function warmSingle(string $path, array $widths, array $params): void
    {
        $this->info("Warming: {$path}");

        try {
            $this->manager->warmPath($path, $widths, $params);
            $this->line("  <fg=green>✓</> Done" . ($widths ? ' (' . implode(', ', $widths) . 'w)' : ''));
        } catch (\Throwable $e) {
            $this->line("  <fg=red>✗</> {$e->getMessage()}");
        }
    }

    private function warmAll(array $widths, array $params): int
    {
        $sourcePath = config('smart-glide.source');
        $extensions = array_map('trim', explode(',', (string) $this->option('ext')));

        if (! $sourcePath || ! is_dir($sourcePath)) {
            $this->error('Source directory not found: ' . ($sourcePath ?? 'null'));
            return self::FAILURE;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS)
        );

        $count = 0;

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! in_array(strtolower($file->getExtension()), $extensions, true)) {
                continue;
            }

            $relative = ltrim(str_replace($sourcePath, '', $file->getPathname()), DIRECTORY_SEPARATOR . '/');
            $relative = str_replace('\\', '/', $relative);

            $this->warmSingle($relative, $widths, $params);
            $count++;
        }

        $stats = $this->manager->cacheStats();
        $this->newLine();
        $this->info("Warmed {$count} image(s). Cache: {$stats['count']} entries · {$stats['size_mb']} MB");

        return self::SUCCESS;
    }

    private function parseWidths(): array
    {
        $raw = $this->option('widths');

        if (! $raw) {
            return [];
        }

        return array_filter(
            array_map('intval', explode(',', (string) $raw)),
            static fn (int $w) => $w > 0
        );
    }

    private function parseParams(): array
    {
        return [];
    }
}
