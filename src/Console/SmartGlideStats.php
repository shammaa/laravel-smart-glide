<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Console;

use Illuminate\Console\Command;
use Shammaa\SmartGlide\Support\SmartGlideManager;

/**
 * Display cache statistics and manifest details.
 *
 * Usage:
 *   php artisan smart-glide:stats
 *   php artisan smart-glide:stats --manifest   (show full manifest)
 */
final class SmartGlideStats extends Command
{
    protected $signature = 'smart-glide:stats
        {--manifest : Show the full cache manifest entries}';

    protected $description = 'Display Smart Glide cache statistics';

    public function __construct(private readonly SmartGlideManager $manager)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $stats = $this->manager->cacheStats();

        $this->newLine();
        $this->line('<fg=cyan;options=bold>Smart Glide Cache Statistics</>');
        $this->line(str_repeat('─', 40));

        $this->table(
            ['Metric', 'Value'],
            [
                ['Cached entries',  $stats['count']],
                ['Total disk size', $stats['size_mb'] . ' MB'],
                ['Cache path',      config('smart-glide.cache')],
                ['Source path',     config('smart-glide.source')],
                ['Max size (MB)',   config('smart-glide.cache_strategy.max_size_mb', '—')],
            ]
        );

        if ($this->option('manifest') && ! empty($stats['manifest'])) {
            $this->newLine();
            $this->line('<fg=cyan>Manifest entries:</>');

            $rows = [];
            foreach ($stats['manifest'] as $entry) {
                $rows[] = [
                    $entry['path'] ?? '—',
                    isset($entry['parameters']['w']) ? $entry['parameters']['w'] . 'w' : '—',
                    isset($entry['parameters']['profile']) ? $entry['parameters']['profile'] : '—',
                    isset($entry['cached_at']) ? (string) $entry['cached_at'] : '—',
                    isset($entry['last_accessed']) ? (string) $entry['last_accessed'] : '—',
                ];
            }

            $this->table(
                ['Path', 'Width', 'Profile', 'Cached At', 'Last Accessed'],
                $rows
            );
        }

        $this->newLine();

        return self::SUCCESS;
    }
}
