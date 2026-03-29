<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shammaa\SmartGlide\Support\SmartGlideManager;

/**
 * Headless API controller — serves image metadata as JSON for React/Next.js/Vue consumers.
 *
 * GET /img-data/{path}
 *   ?profile=hero
 *   &responsive=retina          (named set) | responsive=640,960,1280 (custom) | responsive=0 (off)
 *   &w=800&h=600                (extra Glide params forwarded directly)
 *   &blur_placeholder=1         (include base64 LQIP data URI)
 *   &schema=1                   (include JSON-LD ImageObject)
 */
final class ImageDataController
{
    public function __construct(
        private readonly SmartGlideManager $manager
    ) {}

    public function show(Request $request, string $path): JsonResponse
    {
        // ── build base parameters from query string ──────────────────────────
        $profile    = $request->query('profile');
        $responsive = $this->resolveResponsive($request->query('responsive'));

        // Collect extra Glide params (w, h, fit, focus, q, fm…)
        $glideKeys  = ['w', 'h', 'fit', 'focus', 'q', 'fm', 'crop', 'flip', 'filt', 'sharp', 'con', 'bri', 'gam', 'pixel', 'bg', 'border', 'mark', 'p', 'or'];
        $params     = array_filter(
            $request->only($glideKeys),
            static fn ($v) => $v !== null && $v !== ''
        );

        // ── get responsive data from the manager ─────────────────────────────
        $data = $this->manager->responsiveData(
            path:       $path,
            profile:    $profile ?: null,
            parameters: $params,
            responsive: $responsive,
        );

        // ── optional blur placeholder (LQIP) ─────────────────────────────────
        $blurDataUrl = null;
        if ($request->boolean('blur_placeholder')) {
            $blurDataUrl = $this->manager->placeholder($path, $params);
        }

        // ── optional JSON-LD schema ───────────────────────────────────────────
        $schema = null;
        if ($request->boolean('schema')) {
            $schema = $this->buildSchema($data['src'], $data['parameters'], $request->query('alt', ''));
        }

        // ── compose response ──────────────────────────────────────────────────
        $response = [
            'src'          => $data['src'],
            'srcset'       => $data['srcset'],
            'sizes'        => $data['sizes'],
            'widths'       => $data['widths'],
            'blurDataUrl'  => $blurDataUrl,
            'schema'       => $schema,
        ];

        return response()->json(array_filter($response, static fn ($v) => $v !== null));
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    private function resolveResponsive(mixed $value): array|string|bool|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === '0' || $value === 'false') {
            return false;
        }

        // Named set (e.g. "hero", "retina")
        if (is_string($value) && ! str_contains($value, ',') && ! is_numeric($value)) {
            return $value;
        }

        // Comma-separated widths → array of ints
        if (str_contains((string) $value, ',')) {
            return array_map('intval', explode(',', (string) $value));
        }

        return null;
    }

    private function buildSchema(string $src, array $parameters, string $alt): array
    {
        $data = [
            '@context'    => 'https://schema.org',
            '@type'       => 'ImageObject',
            'contentUrl'  => url($src),
            'url'         => url($src),
            'caption'     => $alt ?: null,
        ];

        if (isset($parameters['w'])) {
            $data['width'] = (int) $parameters['w'];
        }

        if (isset($parameters['h'])) {
            $data['height'] = (int) $parameters['h'];
        }

        if (isset($parameters['fm'])) {
            $data['encodingFormat'] = 'image/' . $parameters['fm'];
        }

        return array_filter($data, static fn ($v) => $v !== null && $v !== '');
    }
}
