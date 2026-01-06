<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Facades;

use Illuminate\Support\Facades\Facade;
use Shammaa\SmartGlide\Support\SmartGlideManager;

/**
 * @method static string url(string $path, array $parameters = [])
 * @method static string deliveryUrl(string $path, array $parameters = [])
 * @method static string croppedUrl(string $path, int $width, int $height, array $parameters = [])
 * @method static string placeholderUrl(string $path, array $parameters = [])
 * @method static string placeholder(string $path, array $parameters = [])
 */
final class SmartGlide extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'smart-glide';
    }

    /**
     * @return SmartGlideManager
     */
    public static function getFacadeRoot()
    {
        return parent::getFacadeRoot();
    }
}


