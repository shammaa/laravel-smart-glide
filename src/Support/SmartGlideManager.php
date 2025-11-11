<?php

declare(strict_types=1);

namespace AppWriters\SmartGlide\Support;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Glide\Responses\LaravelResponseFactory;
use League\Glide\Server;
use League\Glide\ServerFactory;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SmartGlideManager
{
    private Server $server;

    private array $profiles;

    public function __construct(
        private readonly array $config,
        private readonly CacheRepository $cache
    ) {
        $this->profiles = $this->loadProfiles($config);
        $this->initialize();
    }

    public function serve(string $path, Request $request): Response|StreamedResponse
    {
        $start = microtime(true);
        $query = $request->query();
        $normalizedPath = $this->normalizePath($path);

        $parameters = $this->applyProfiles($query);

        if ($this->shouldSecure()) {
            $this->validateSignature($normalizedPath, $parameters, $query);
        }

        $this->validateParameters($normalizedPath, $parameters);

        $cacheKey = $this->manifestKey($normalizedPath, $parameters);

        $metadata = $this->warmCache($normalizedPath, $parameters, $cacheKey);
        $this->touchManifest($cacheKey);

        $etag = $this->computeEtag($metadata);

        if ($etag && $request->headers->get('If-None-Match') === $etag) {
            return $this->notModifiedResponse($etag);
        }

        $response = $this->server->getImageResponse($normalizedPath, $parameters);

        $this->applyResponseHeaders($response, $etag);
        $this->logProcessing($normalizedPath, microtime(true) - $start, (bool) ($metadata['from_cache'] ?? false), $metadata);

        return $response;
    }

    public function deliveryUrl(string $path, array $parameters = []): string
    {
        $normalizedPath = $this->normalizePath($path);
        $base = '/' . trim($this->config['delivery_path'] ?? '/img', '/');
        $parameters = $this->applyProfiles($parameters);

        if ($this->shouldSecure()) {
            $parameters['s'] = $this->generateSignature($normalizedPath, $parameters);
        }

        $query = http_build_query($parameters);

        return sprintf('%s/%s%s', $base, $normalizedPath, $query ? '?' . $query : '');
    }

    private function initialize(): void
    {
        $sourcePath = $this->config['source'] ?? null;
        $cachePath = $this->config['cache'] ?? null;

        if (! $sourcePath || ! $cachePath) {
            throw new RuntimeException('Smart Glide requires both source and cache paths.');
        }

        if (! is_dir($cachePath)) {
            mkdir($cachePath, 0755, true);
        }

        $source = new Filesystem(new LocalFilesystemAdapter($sourcePath));
        $cache = new Filesystem(new LocalFilesystemAdapter($cachePath));

        $responseFactory = new LaravelResponseFactory(app('request'));

        $this->server = ServerFactory::create([
            'response' => $responseFactory,
            'source' => $source,
            'cache' => $cache,
            'cache_path_prefix' => '',
            'group_cache_in_folders' => false,
            'max_image_size' => $this->config['max_image_size'] ?? 4000 * 4000,
        ]);
    }

    private function normalizePath(string $path): string
    {
        $clean = trim($path, '/');
        $clean = str_replace("\0", '', $clean);

        if ($clean === '' || str_contains($clean, '../')) {
            throw new FileNotFoundException("Invalid image path: {$path}");
        }

        if (preg_match('/^[a-z][a-z0-9+\-.]*:\/\//i', $clean)) {
            if (! $this->allowRemoteImages()) {
                throw new FileNotFoundException('Remote image sources are disabled.');
            }
        }

        return $clean;
    }

    private function applyProfiles(array $parameters): array
    {
        $profile = $parameters['profile'] ?? null;
        unset($parameters['profile']);

        $defaults = $this->profiles['default'] ?? [];

        if ($profile) {
            $profileDefaults = $this->profiles[$profile] ?? [];
            $defaults = array_merge($defaults, $profileDefaults);
        }

        return array_merge($defaults, $parameters);
    }

    private function validateParameters(string $path, array $parameters): void
    {
        $security = $this->config['security'] ?? [];
        $allowedExtensions = $security['allowed_extensions'] ?? ($this->config['allowed_extensions'] ?? []);

        if ($allowedExtensions) {
            $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));

            if ($extension === '' || ! in_array($extension, $allowedExtensions, true)) {
                abort(400, 'Disallowed image extension.');
            }
        }

        if (isset($parameters['fm'])) {
            $format = strtolower((string) $parameters['fm']);
            $allowedFormats = $security['allowed_formats'] ?? [];

            if ($allowedFormats && ! in_array($format, $allowedFormats, true)) {
                abort(400, 'Requested format is not allowed.');
            }
        }

        foreach (['w' => 'width', 'h' => 'height'] as $key => $label) {
            if (! isset($parameters[$key])) {
                continue;
            }

            if (! is_numeric($parameters[$key])) {
                abort(400, ucfirst($label) . ' must be numeric.');
            }

            $value = (int) $parameters[$key];

            if ($value <= 0) {
                abort(400, ucfirst($label) . ' must be greater than zero.');
            }

            $limit = (int) ($key === 'w'
                ? ($security['max_width'] ?? 0)
                : ($security['max_height'] ?? 0));

            if ($limit > 0 && $value > $limit) {
                abort(400, ucfirst($label) . ' exceeds maximum allowed.');
            }
        }

        if (isset($parameters['q'])) {
            if (! is_numeric($parameters['q'])) {
                abort(400, 'Quality must be numeric.');
            }

            $quality = (int) $parameters['q'];
            $minQuality = (int) ($security['min_quality'] ?? 0);
            $maxQuality = (int) ($security['max_quality'] ?? 0);

            if ($quality < 1) {
                abort(400, 'Quality must be between 1 and 100.');
            }

            if ($minQuality > 0 && $quality < $minQuality) {
                abort(400, 'Quality below minimum allowed.');
            }

            if ($maxQuality > 0 && $quality > $maxQuality) {
                abort(400, 'Quality exceeds maximum allowed.');
            }
        }
    }

    private function shouldSecure(): bool
    {
        return (bool) ($this->config['security']['secure_urls'] ?? $this->config['secure'] ?? true);
    }

    private function allowRemoteImages(): bool
    {
        return (bool) ($this->config['security']['allow_remote_images'] ?? false);
    }

    private function generateSignature(string $path, array $parameters): string
    {
        $key = $this->config['security']['signature_key'] ?? config('app.key');

        if (! $key) {
            throw new RuntimeException('APP_KEY must be configured to sign Smart Glide URLs.');
        }

        $input = $path . '|' . http_build_query($parameters);

        return hash_hmac('sha256', $input, $key);
    }

    private function validateSignature(string $path, array $parameters, array $query): void
    {
        $supplied = $query['s'] ?? null;

        if (! $supplied) {
            abort(403, 'Smart Glide signature missing.');
        }

        $expected = $this->generateSignature($path, $parameters);

        if (! hash_equals($expected, $supplied)) {
            abort(403, 'Invalid Smart Glide signature.');
        }
    }

    private function warmCache(string $path, array $parameters, string $cacheKey): array
    {
        if ($this->cache->has($cacheKey)) {
            $metadata = $this->cache->get($cacheKey);
            $metadata['from_cache'] = true;

            return $metadata;
        }

        $cacheFile = $this->server->makeImage($path, $parameters);

        $metadata = [
            'path' => $path,
            'parameters' => $parameters,
            'cached_at' => now(),
            'cache_file' => $cacheFile,
        ];

        $this->cache->put($cacheKey, $metadata, now()->addWeeks(2));
        $this->recordManifest($cacheKey, $metadata);
        $this->enforceCacheBudget();

        $metadata['from_cache'] = false;

        return $metadata;
    }

    private function loadProfiles(array $config): array
    {
        $profiles = $config['profiles'] ?? [];
        $file = $config['profile_file'] ?? null;

        if ($file && file_exists($file)) {
            $externalProfiles = require $file;

            if (is_array($externalProfiles)) {
                $profiles = array_replace_recursive($profiles, $externalProfiles);
            }
        }

        return $profiles;
    }

    private function computeEtag(array $metadata): ?string
    {
        if (! ($this->config['cache_headers']['use_etag'] ?? false)) {
            return null;
        }

        $cachePath = $this->resolveCachePath($metadata);

        if (! $cachePath || ! file_exists($cachePath)) {
            return null;
        }

        return '"' . md5_file($cachePath) . '"';
    }

    private function applyResponseHeaders(Response|StreamedResponse $response, ?string $etag): void
    {
        $headers = $this->config['cache_headers'] ?? [];
        $days = (int) ($headers['browser_cache_days'] ?? 0);

        if ($days > 0) {
            $maxAge = $days * 86400;
            $response->headers->set('Cache-Control', 'public, max-age=' . $maxAge);
            $response->headers->set('Expires', now()->addDays($days)->toRfc7231String());
        }

        if ($etag) {
            $response->headers->set('ETag', $etag);
        }
    }

    private function notModifiedResponse(string $etag): Response
    {
        $response = response('', 304);
        $this->applyResponseHeaders($response, $etag);

        return $response;
    }

    private function logProcessing(string $path, float $elapsedSeconds, bool $fromCache, array $metadata): void
    {
        $logging = $this->config['logging'] ?? [];

        if (! ($logging['enabled'] ?? false) || ! ($logging['log_processing_time'] ?? false)) {
            return;
        }

        $channel = $logging['channel'] ?? 'stack';
        $cachePath = $this->resolveCachePath($metadata);
        $sizeBytes = null;

        if ($cachePath && file_exists($cachePath)) {
            $sizeBytes = filesize($cachePath);
        }

        Log::channel($channel)->debug('Smart Glide processed image', [
            'image' => $path,
            'time_ms' => round($elapsedSeconds * 1000, 2),
            'cached' => $fromCache,
            'size_kb' => $sizeBytes ? round($sizeBytes / 1024, 2) : null,
        ]);
    }

    private function recordManifest(string $cacheKey, array $metadata): void
    {
        unset($metadata['from_cache']);

        $manifest = $this->cache->get('smart_glide_manifest', []);
        $manifest[$cacheKey] = array_merge($metadata, [
            'last_accessed' => now(),
        ]);

        $this->cache->put('smart_glide_manifest', $manifest, now()->addMonths(6));
    }

    private function touchManifest(string $cacheKey): void
    {
        $manifest = $this->cache->get('smart_glide_manifest', []);

        if (! isset($manifest[$cacheKey])) {
            return;
        }

        $manifest[$cacheKey]['last_accessed'] = now();
        $this->cache->put('smart_glide_manifest', $manifest, now()->addMonths(6));
    }

    private function enforceCacheBudget(): void
    {
        $maxSize = (int) ($this->config['cache_strategy']['max_size_mb'] ?? 0);
        $cachePath = $this->config['cache'] ?? null;

        if ($maxSize <= 0 || ! $cachePath || ! is_dir($cachePath)) {
            return;
        }

        $maxBytes = $maxSize * 1024 * 1024;
        $currentSize = $this->directorySize($cachePath);

        if ($currentSize <= $maxBytes) {
            return;
        }

        $manifest = $this->cache->get('smart_glide_manifest', []);

        uasort($manifest, static function (array $a, array $b): int {
            return strtotime((string) $a['last_accessed']) <=> strtotime((string) $b['last_accessed']);
        });

        foreach ($manifest as $key => $entry) {
            $filePath = $this->resolveCachePath($entry);

            if ($filePath && file_exists($filePath)) {
                unlink($filePath);
            }

            unset($manifest[$key]);
            $this->cache->forget($key);

            $currentSize = $this->directorySize($cachePath);
            if ($currentSize <= $maxBytes) {
                break;
            }
        }

        $this->cache->put('smart_glide_manifest', $manifest, now()->addMonths(6));
    }

    private function directorySize(string $path): int
    {
        $size = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function resolveCachePath(array $entry): ?string
    {
        $cachePath = $this->config['cache'] ?? null;

        if (! $cachePath || empty($entry['cache_file'])) {
            return null;
        }

        return $cachePath . DIRECTORY_SEPARATOR . ltrim($entry['cache_file'], DIRECTORY_SEPARATOR);
    }

    private function manifestKey(string $path, array $parameters): string
    {
        return 'smart_glide_meta:' . md5($path . json_encode($parameters));
    }
}

