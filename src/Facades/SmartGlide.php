<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Facades;

use Illuminate\Support\Facades\Facade;
use Shammaa\SmartGlide\Support\SmartGlideManager;

/**
 * Smart Glide Facade
 *
 * ── URL Generation ────────────────────────────────────────────────────────────
 * @method static string url(string $path, array $parameters = [])
 * @method static string deliveryUrl(string $path, array $parameters = [])
 * @method static string croppedUrl(string $path, int $width, int $height, array $parameters = [])
 * @method static string placeholderUrl(string $path, array $parameters = [])
 * @method static array<int, string> multipleUrls(string $path, array $widths, array $parameters = [])
 *
 * ── Blur / LQIP ──────────────────────────────────────────────────────────────
 * @method static string placeholder(string $path, array $parameters = [])
 *
 * ── Responsive ───────────────────────────────────────────────────────────────
 * @method static array responsiveData(string $path, ?string $profile = null, array $parameters = [], array|string|bool|null $responsive = null)
 * @method static array resolveBreakpoints(array|string|bool|null $responsive = null)
 *
 * ── Headless API payload (Inertia / Resources) ────────────────────────────────
 * @method static array apiPayload(string $path, array $options = [])
 *
 * ── Image introspection ───────────────────────────────────────────────────────
 * @method static bool imageExists(string $path)
 * @method static array|null dimensions(string $path)
 *
 * ── Cache Management ─────────────────────────────────────────────────────────
 * @method static void warmPath(string $path, array $widths = [], array $parameters = [])
 * @method static int forgetPath(string $path)
 * @method static array cacheStats()
 *
 * @see SmartGlideManager
 */
final class SmartGlide extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'smart-glide';
    }
}
