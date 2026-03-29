<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shammaa\SmartGlide\Support\SmartGlideManager;

/**
 * Admin management controller — callable from your website/dashboard.
 *
 * All routes are prefixed with the configured `admin_path` (default: /img-admin)
 * and protected by the `admin_middleware` (default: empty — you SHOULD add auth middleware).
 *
 * Available endpoints:
 *   GET  /img-admin/stats              → cache statistics
 *   GET  /img-admin/stats/manifest     → full cache manifest
 *   POST /img-admin/warm               → warm a single image
 *   POST /img-admin/warm-all           → warm all images in source dir
 *   POST /img-admin/forget             → evict cache for a specific image
 *   GET  /img-admin/exists             → check if source image exists
 *   GET  /img-admin/dimensions         → get original image dimensions
 */
final class AdminController
{
    public function __construct(
        private readonly SmartGlideManager $manager
    ) {}

    // ── GET /img-admin/stats ───────────────────────────────────────────────────

    /**
     * Return cache statistics.
     *
     * Response:
     * {
     *   "count": 152,
     *   "size_mb": 48.3,
     *   "cache_path": "...",
     *   "source_path": "...",
     *   "max_size_mb": 1024
     * }
     */
    public function stats(): JsonResponse
    {
        $stats = $this->manager->cacheStats();

        return response()->json([
            'count'       => $stats['count'],
            'size_mb'     => $stats['size_mb'],
            'cache_path'  => config('smart-glide.cache'),
            'source_path' => config('smart-glide.source'),
            'max_size_mb' => config('smart-glide.cache_strategy.max_size_mb'),
        ]);
    }

    // ── GET /img-admin/stats/manifest ──────────────────────────────────────────

    /**
     * Return the full cache manifest (all cached image entries).
     *
     * Response:
     * {
     *   "count": 152,
     *   "manifest": [ { "path": "...", "cached_at": "...", "last_accessed": "..." }, ... ]
     * }
     */
    public function manifest(): JsonResponse
    {
        $stats = $this->manager->cacheStats();

        $manifest = collect($stats['manifest'])
            ->map(static fn (array $entry) => [
                'path'          => $entry['path'] ?? null,
                'width'         => $entry['parameters']['w'] ?? null,
                'profile'       => $entry['parameters']['profile'] ?? null,
                'format'        => $entry['parameters']['fm'] ?? null,
                'cached_at'     => isset($entry['cached_at']) ? (string) $entry['cached_at'] : null,
                'last_accessed' => isset($entry['last_accessed']) ? (string) $entry['last_accessed'] : null,
            ])
            ->values();

        return response()->json([
            'count'    => $stats['count'],
            'size_mb'  => $stats['size_mb'],
            'manifest' => $manifest,
        ]);
    }

    // ── POST /img-admin/warm ───────────────────────────────────────────────────

    /**
     * Pre-warm (process & cache) a single image.
     *
     * Request body (JSON):
     * {
     *   "path":    "products/phone.jpg",   // required
     *   "profile": "hero",                 // optional
     *   "widths":  [640, 960, 1280]        // optional — defaults to config breakpoints
     * }
     *
     * Response:
     * { "warmed": true, "path": "products/phone.jpg", "widths": [640, 960, 1280] }
     */
    public function warm(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path'      => ['required', 'string', 'max:500'],
            'profile'   => ['nullable', 'string', 'max:64'],
            'widths'    => ['nullable', 'array'],
            'widths.*'  => ['integer', 'min:1', 'max:5000'],
        ]);

        $path    = $validated['path'];
        $widths  = $validated['widths'] ?? [];
        $params  = [];

        if (! empty($validated['profile'])) {
            $params['profile'] = $validated['profile'];
        }

        if (! $this->manager->imageExists($path)) {
            return response()->json([
                'warmed'  => false,
                'path'    => $path,
                'error'   => 'Image not found in source directory.',
            ], 404);
        }

        $this->manager->warmPath($path, $widths, $params);

        $resolved = empty($widths)
            ? array_values(config('smart-glide.breakpoints', []))
            : $widths;

        return response()->json([
            'warmed' => true,
            'path'   => $path,
            'widths' => $resolved,
        ]);
    }

    // ── POST /img-admin/warm-all ───────────────────────────────────────────────

    /**
     * Pre-warm all images in the source directory.
     *
     * Request body (JSON):
     * {
     *   "profile":    "thumbnail",             // optional
     *   "widths":     [320, 640, 960],          // optional
     *   "extensions": ["jpg", "png", "webp"]   // optional — defaults to allowed_extensions
     * }
     *
     * Response:
     * { "warmed_count": 48, "skipped_count": 3, "size_mb": 52.1 }
     */
    public function warmAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile'      => ['nullable', 'string', 'max:64'],
            'widths'       => ['nullable', 'array'],
            'widths.*'     => ['integer', 'min:1', 'max:5000'],
            'extensions'   => ['nullable', 'array'],
            'extensions.*' => ['string', 'max:10'],
        ]);

        $sourcePath = config('smart-glide.source');

        if (! $sourcePath || ! is_dir($sourcePath)) {
            return response()->json([
                'error' => 'Source directory not found or not configured.',
            ], 500);
        }

        $allowedExt = $validated['extensions']
            ?? config('smart-glide.security.allowed_extensions', ['jpg', 'jpeg', 'png', 'webp', 'avif']);

        $widths  = $validated['widths'] ?? [];
        $params  = [];

        if (! empty($validated['profile'])) {
            $params['profile'] = $validated['profile'];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS)
        );

        $warmedCount  = 0;
        $skippedCount = 0;

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! in_array(strtolower($file->getExtension()), $allowedExt, true)) {
                continue;
            }

            $relative = ltrim(str_replace($sourcePath, '', $file->getPathname()), DIRECTORY_SEPARATOR . '/');
            $relative = str_replace('\\', '/', $relative);

            try {
                $this->manager->warmPath($relative, $widths, $params);
                $warmedCount++;
            } catch (\Throwable) {
                $skippedCount++;
            }
        }

        $stats = $this->manager->cacheStats();

        return response()->json([
            'warmed_count'  => $warmedCount,
            'skipped_count' => $skippedCount,
            'size_mb'       => $stats['size_mb'],
            'total_entries' => $stats['count'],
        ]);
    }

    // ── POST /img-admin/forget ─────────────────────────────────────────────────

    /**
     * Evict all cached renditions for a specific image.
     * Call this after replacing/updating the original file.
     *
     * Request body (JSON):
     * { "path": "products/phone.jpg" }
     *
     * Response:
     * { "forgotten": true, "path": "products/phone.jpg", "deleted_count": 5 }
     */
    public function forget(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        $path    = $validated['path'];
        $deleted = $this->manager->forgetPath($path);

        return response()->json([
            'forgotten'     => true,
            'path'          => $path,
            'deleted_count' => $deleted,
        ]);
    }

    // ── GET /img-admin/exists ──────────────────────────────────────────────────

    /**
     * Check whether the original source image exists.
     *
     * Query param: ?path=products/phone.jpg
     *
     * Response:
     * { "exists": true, "path": "products/phone.jpg" }
     */
    public function exists(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        $path   = $validated['path'];
        $exists = $this->manager->imageExists($path);

        return response()->json([
            'exists' => $exists,
            'path'   => $path,
        ]);
    }

    // ── GET /img-admin/dimensions ──────────────────────────────────────────────

    /**
     * Get the original pixel dimensions of a source image.
     *
     * Query param: ?path=products/phone.jpg
     *
     * Response:
     * { "path": "products/phone.jpg", "width": 3840, "height": 2160 }
     */
    public function dimensions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string', 'max:500'],
        ]);

        $path       = $validated['path'];
        $dimensions = $this->manager->dimensions($path);

        if (! $dimensions) {
            return response()->json([
                'path'  => $path,
                'error' => 'Image not found or dimensions unreadable.',
            ], 404);
        }

        return response()->json([
            'path'   => $path,
            'width'  => $dimensions['width'],
            'height' => $dimensions['height'],
        ]);
    }
}
