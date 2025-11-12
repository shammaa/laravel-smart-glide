<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Image Source Directory
    |--------------------------------------------------------------------------
    |
    | Absolute path to the directory containing original assets.
    | By default, we look inside the Laravel application's resources/assets/images
    | directory. Update this to match your project's structure.
    |
    */
    'source' => env('SMART_GLIDE_SOURCE', storage_path('app/public')),

    /*
    |--------------------------------------------------------------------------
    | Cache Directory
    |--------------------------------------------------------------------------
    |
    | Processed images are stored in this directory so subsequent requests
    | can be served instantly. Make sure your application has write access.
    |
    */
    'cache' => env('SMART_GLIDE_CACHE', storage_path('app/smart-glide-cache')),

    /*
    |--------------------------------------------------------------------------
    | Unified Delivery Path
    |--------------------------------------------------------------------------
    |
    | All generated image URLs (src/srcset) will be prefixed with this
    | value to ensure a single routing and cache path.
    |
    */
    'delivery_path' => env('SMART_GLIDE_DELIVERY_PATH', '/img'),

    /*
    |--------------------------------------------------------------------------
    | Secure URL Signing
    |--------------------------------------------------------------------------
    |
    | Prevents malicious actors from generating arbitrary transformations.
    | Uses APP_KEY by default. Disable with caution only in development.
    |
    */
    'secure' => env('SMART_GLIDE_SECURE', true),

    /*
    |--------------------------------------------------------------------------
    | Security Rules
    |--------------------------------------------------------------------------
    |
    | Harden image delivery by controlling accepted inputs and enforcing limits.
    |
    */
    'security' => [
        'secure_urls' => env('SMART_GLIDE_SECURE', true),
        'signature_key' => env('SMART_GLIDE_SIGNATURE_KEY', null),
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'],
        'allowed_formats' => ['webp', 'jpg', 'jpeg', 'png', 'avif'],
        'max_width' => env('SMART_GLIDE_MAX_WIDTH', 3840),
        'max_height' => env('SMART_GLIDE_MAX_HEIGHT', 2160),
        'min_quality' => env('SMART_GLIDE_MIN_QUALITY', 20),
        'max_quality' => env('SMART_GLIDE_MAX_QUALITY', 95),
        'allow_remote_images' => env('SMART_GLIDE_ALLOW_REMOTE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Compression Profiles
    |--------------------------------------------------------------------------
    |
    | Define reusable compression presets. Associate a media type or context
    | with an array of Glide transformation parameters.
    |
    */
    'profiles' => [
        'default' => [
            'fm' => 'webp',
            'q' => 82,
        ],
        'thumbnail' => [
            'w' => 320,
            'h' => 320,
            'fit' => 'crop',
            'fm' => 'webp',
            'q' => 75,
        ],
        'hero' => [
            'w' => 1600,
            'h' => 900,
            'fit' => 'crop',
            'focus' => 'center',
            'fm' => 'webp',
            'q' => 80,
        ],
        'portrait' => [
            'w' => 800,
            'h' => 1200,
            'fit' => 'crop',
            'focus' => 'faces',
            'fm' => 'webp',
            'q' => 78,
        ],
        'square' => [
            'w' => 600,
            'h' => 600,
            'fit' => 'crop',
            'fm' => 'webp',
            'q' => 80,
        ],
        'profile_photo' => [
            'w' => 400,
            'h' => 400,
            'fit' => 'crop',
            'focus' => 'faces',
            'fm' => 'webp',
            'q' => 82,
        ],
        'cover' => [
            'w' => 2048,
            'h' => 1152,
            'fit' => 'crop',
            'focus' => 'center',
            'fm' => 'webp',
            'q' => 85,
        ],
        'background' => [
            'w' => 2560,
            'h' => 1440,
            'fit' => 'max',
            'fm' => 'webp',
            'q' => 82,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | External Compression Profiles
    |--------------------------------------------------------------------------
    |
    | Provide the absolute path to a PHP file that returns an array of profiles.
    | This is perfect for delegating compression tuning to DevOps/Media teams.
    | The returned array will be merged on top of the inline profile definitions.
    |
    */
    'profile_file' => env('SMART_GLIDE_PROFILE_FILE', null),

    /*
    |--------------------------------------------------------------------------
    | Responsive Breakpoints
    |--------------------------------------------------------------------------
    |
    | Automatically generate srcset attributes using these breakpoints.
    | Keys are label identifiers; values are pixel widths.
    |
    */
    'breakpoints' => [
        'xs' => 360,
        'sm' => 640,
        'md' => 960,
        'lg' => 1280,
        'xl' => 1600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Named Responsive Sets
    |--------------------------------------------------------------------------
    |
    | Define shortcut aliases that components can reference via
    | `responsive="landscape"` or `responsive="thumbnails"`.
    |
    */
    'responsive_sets' => [
        'hero' => [640, 960, 1280, 1600, 1920],
        'thumbnails' => [240, 320, 480],
        'square' => [320, 480, 640],
        'portrait' => [480, 768, 1024],
        'hd' => [960, 1280, 1600],
        'fhd' => [1280, 1600, 1920, 2560],
        'retina' => [640, 960, 1280, 1920, 2560],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Strategy
    |--------------------------------------------------------------------------
    |
    | fine-tune how the smart cache behaves. Strategies can include disk usage
    | caps, LRU eviction, and metadata persistence. Values are expressed in MB.
    |
    */
    'cache_strategy' => [
        'max_size_mb' => env('SMART_GLIDE_CACHE_MAX_MB', 1024),
        'lru_window' => env('SMART_GLIDE_CACHE_LRU_WINDOW', 14), // days
        'purge_time' => env('SMART_GLIDE_CACHE_PURGE_TIME', '03:00'),
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP Cache Headers
    |--------------------------------------------------------------------------
    |
    | Configure browser cache directives and ETag support for downstream clients.
    |
    */
    'cache_headers' => [
        'browser_cache_days' => env('SMART_GLIDE_BROWSER_CACHE_DAYS', 7),
        'use_etag' => env('SMART_GLIDE_USE_ETAG', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Fine-tune runtime logging for diagnostics and observability.
    |
    */
    'logging' => [
        'enabled' => env('SMART_GLIDE_LOGGING', true),
        'channel' => env('SMART_GLIDE_LOG_CHANNEL', env('LOG_CHANNEL', 'stack')),
        'log_processing_time' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO Defaults
    |--------------------------------------------------------------------------
    |
    | Define default attributes and structured data behaviour for rendered
    | images and background components to improve search visibility.
    |
    */
    'seo' => [
        'image_attributes' => [
            'loading' => env('SMART_GLIDE_IMG_LOADING', 'lazy'),
            'decoding' => env('SMART_GLIDE_IMG_DECODING', 'async'),
            'fetchpriority' => env('SMART_GLIDE_IMG_FETCHPRIORITY', 'auto'),
        ],
        'background_attributes' => [
            'role' => env('SMART_GLIDE_BG_ROLE', 'img'),
        ],
        'structured_data' => [
            'enabled' => env('SMART_GLIDE_SCHEMA_ENABLED', false),
            'fields' => [],
        ],
    ],
];

