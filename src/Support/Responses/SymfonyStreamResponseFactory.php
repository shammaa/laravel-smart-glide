<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Support\Responses;

use League\Glide\Responses\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SymfonyStreamResponseFactory implements ResponseFactoryInterface
{
    /**
     * @param mixed $request
     */
    public function create($request, PsrResponseInterface $response)
    {
        $stream = $response->getBody();

        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }

        return new StreamedResponse(
            static function () use ($stream): void {
                if ($stream->isSeekable()) {
                    $stream->rewind();
                }

                while (! $stream->eof()) {
                    echo $stream->read(8192);
                }
            },
            $response->getStatusCode(),
            $headers
        );
    }
}

