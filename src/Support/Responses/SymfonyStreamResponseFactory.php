<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Support\Responses;

use League\Flysystem\FilesystemOperator;
use League\Glide\Responses\ResponseFactoryInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SymfonyStreamResponseFactory implements ResponseFactoryInterface
{
    public function create(FilesystemOperator $cache, string $path)
    {
        if (! $cache->fileExists($path)) {
            return new StreamedResponse(static function (): void {
            }, 404);
        }

        $headers = [
            'Content-Type' => $this->guessMimeType($cache, $path),
            'Content-Length' => (string) $cache->fileSize($path),
            'Last-Modified' => gmdate('D, d M Y H:i:s', $cache->lastModified($path)) . ' GMT',
            'Cache-Control' => 'public, max-age=31536000',
        ];

        return new StreamedResponse(
            static function () use ($cache, $path): void {
                $stream = $cache->readStream($path);

                if (! is_resource($stream)) {
                    return;
                }

                while (! feof($stream)) {
                    echo fread($stream, 8192);
                }

                fclose($stream);
            },
            200,
            $headers
        );
    }

    private function guessMimeType(FilesystemOperator $cache, string $path): string
    {
        try {
            return $cache->mimeType($path) ?? 'application/octet-stream';
        } catch (\Throwable) {
            return 'application/octet-stream';
        }
    }
}

