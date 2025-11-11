<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Http\Controllers;

use Shammaa\SmartGlide\Support\SmartGlideManager;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ImageController
{
    public function __construct(
        private readonly SmartGlideManager $manager
    ) {
    }

    public function show(Request $request, string $path): Response|StreamedResponse
    {
        try {
            return $this->manager->serve($path, $request);
        } catch (FileNotFoundException $exception) {
            abort(404, $exception->getMessage());
        }
    }
}

