<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\View\Component;
use Shammaa\SmartGlide\Support\SmartGlideManager;

final class SmartPicture extends Component
{
    public function __construct(
        private readonly SmartGlideManager $manager,
        public string $src,
        public array $sources = [],
        public ?string $alt = null,
        public array $params = [],
        public ?string $profile = null,
        public ?string $class = null,
        public ?string $style = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $imgClass = null,
        public ?string $imgStyle = null,
        public ?int $imgWidth = null,
        public ?int $imgHeight = null,
        public ?string $aspectRatio = null,
        public ?string $sizes = null,
        public array $seo = [],
        public ?bool $schema = null,
        public array|string|bool|null $responsive = null
    ) {
    }

    public function render(): View
    {
        $baseParameters = $this->buildParameters($this->params, $this->profile);
        $fallbackBreakpoints = $this->resolveBreakpoints($this->responsive);
        $fallbackSrcSet = $this->buildSrcSet($this->src, $baseParameters, $fallbackBreakpoints);
        $fallbackUrl = $this->manager->deliveryUrl($this->src, $baseParameters);
        $seoAttributes = $this->seoAttributes();
        $structuredData = $this->structuredData($fallbackUrl, $baseParameters, $seoAttributes);
        $imgStyle = $this->composeStyle($this->imgStyle, $this->aspectRatio);

        return view('smart-glide::components.picture', [
            'class' => $this->class,
            'style' => $this->style,
            'width' => $this->width,
            'height' => $this->height,
            'sources' => $this->buildSourceEntries(),
            'img' => [
                'src' => $fallbackUrl,
                'srcset' => $fallbackSrcSet['srcset'],
                'sizes' => $this->sizes ?? $fallbackSrcSet['sizes'],
                'alt' => $this->alt,
                'class' => $this->imgClass,
                'style' => $imgStyle,
                'width' => $this->imgWidth ?? $this->width,
                'height' => $this->imgHeight ?? $this->height,
                'seoAttributes' => $seoAttributes,
            ],
            'structuredData' => $structuredData,
        ]);
    }

    private function buildSourceEntries(): array
    {
        return collect($this->sources)->map(function ($source) {
            if (is_string($source)) {
                $source = ['src' => $source];
            }

            $profile = $source['profile'] ?? $this->profile;
            $params = $this->buildParameters(
                array_merge($this->params, $source['params'] ?? []),
                $profile
            );

            $srcPath = $source['src'] ?? $this->src;
            $widths = $this->resolveBreakpoints($source['responsive'] ?? $source['widths'] ?? $source['breakpoints'] ?? null, default: []);

            if (isset($source['srcset']) && is_array($source['srcset'])) {
                $srcset = $this->buildCustomSrcSet($source['srcset'], $srcPath, $params);
            } else {
                $srcset = $this->buildSrcSet($srcPath, $params, $widths);
            }

            $sizes = $source['sizes'] ?? null;
            $type = $source['type'] ?? null;
            $media = $source['media'] ?? null;

            return [
                'media' => $media,
                'type' => $type,
                'srcset' => $srcset['srcset'],
                'sizes' => $sizes ?? $srcset['sizes'],
                'src' => $srcset['srcset'] ? null : $this->manager->deliveryUrl($srcPath, $params),
            ];
        })->all();
    }

    private function buildCustomSrcSet(array $definitions, string $path, array $baseParameters): array
    {
        $entries = [];

        foreach ($definitions as $definition) {
            if (is_string($definition)) {
                $entries[] = $definition;
                continue;
            }

            $descriptor = $definition['descriptor'] ?? null;
            $params = array_merge($baseParameters, $definition['params'] ?? []);
            $profile = $definition['profile'] ?? null;

            if ($profile) {
                $params = $this->buildParameters($params, $profile);
            }

            $entries[] = trim($this->manager->deliveryUrl($definition['src'] ?? $path, $params) . ($descriptor ? ' ' . $descriptor : ''));
        }

        return [
            'srcset' => implode(', ', array_filter($entries)),
            'sizes' => null,
        ];
    }

    private function buildParameters(array $parameters, ?string $profile): array
    {
        if ($profile) {
            $parameters['profile'] = $profile;
        }

        return $parameters;
    }

    private function resolveBreakpoints(array|string|bool|null $responsive, ?array $default = null): array
    {
        if ($responsive === false) {
            return [];
        }

        if (is_array($responsive)) {
            return $this->normalizeBreakpoints($responsive);
        }

        if (is_string($responsive)) {
            $preset = config("smart-glide.responsive_sets.{$responsive}");

            if ($preset) {
                return $this->normalizeBreakpoints($preset);
            }

            $values = array_map('trim', explode(',', $responsive));

            return $this->normalizeBreakpoints($values);
        }

        return $this->normalizeBreakpoints($default ?? config('smart-glide.breakpoints', []));
    }

    private function normalizeBreakpoints(array $values): array
    {
        $widths = [];

        foreach ($values as $value) {
            if (is_array($value) && isset($value['w'])) {
                $value = $value['w'];
            }

            if (! is_numeric($value)) {
                continue;
            }

            $int = (int) $value;

            if ($int > 0) {
                $widths[] = $int;
            }
        }

        $widths = array_values(array_unique($widths));
        sort($widths);

        return $widths;
    }

    private function buildSrcSet(string $path, array $parameters, array $breakpoints): array
    {
        if (empty($breakpoints)) {
            return ['srcset' => null, 'sizes' => null];
        }

        $entries = [];

        foreach ($breakpoints as $width) {
            $params = array_merge($parameters, ['w' => $width]);
            $entries[] = sprintf('%s %dw', $this->manager->deliveryUrl($path, $params), $width);
        }

        return [
            'srcset' => implode(', ', $entries),
            'sizes' => implode(', ', array_map(
                static fn (int $width): string => "(max-width: {$width}px) 100vw",
                $breakpoints
            )),
        ];
    }

    private function seoAttributes(): array
    {
        $defaults = config('smart-glide.seo.image_attributes', [
            'loading' => 'lazy',
            'decoding' => 'async',
        ]);

        $attributes = array_merge($defaults, $this->seo);

        foreach (['src', 'srcset', 'sizes', 'alt', 'class'] as $forbidden) {
            unset($attributes[$forbidden]);
        }

        return array_filter($attributes, static fn ($value) => ! is_null($value) && $value !== false);
    }

    private function structuredData(string $src, array $parameters, array $seoAttributes): ?string
    {
        $options = config('smart-glide.seo.structured_data', []);
        $enabled = $options['enabled'] ?? false;

        if (! is_null($this->schema)) {
            $enabled = $this->schema;
        }

        if (! $enabled) {
            return null;
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'ImageObject',
            'contentUrl' => $src,
            'url' => $src,
            'caption' => $this->alt,
        ];

        if (isset($parameters['w'])) {
            $data['width'] = (int) $parameters['w'];
        }

        if (isset($parameters['h'])) {
            $data['height'] = (int) $parameters['h'];
        }

        if (isset($parameters['fm'])) {
            $data['encodingFormat'] = $parameters['fm'];
        }

        if ($title = Arr::get($seoAttributes, 'title')) {
            $data['name'] = $title;
        }

        if ($license = Arr::get($seoAttributes, 'data-license')) {
            $data['license'] = $license;
        }

        $extra = $options['fields'] ?? [];
        if (! empty($extra)) {
            $data = array_merge($data, $extra);
        }

        $filtered = array_filter($data, static fn ($value) => ! is_null($value) && $value !== '');

        return json_encode($filtered, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function composeStyle(?string $style, ?string $aspectRatio): ?string
    {
        $declarations = [];

        if ($style) {
            $declarations[] = rtrim($style, ';');
        }

        if ($aspectRatio) {
            $declarations[] = 'aspect-ratio: ' . $this->formatAspectRatio($aspectRatio);
        }

        if (empty($declarations)) {
            return null;
        }

        return implode('; ', $declarations) . ';';
    }

    private function formatAspectRatio(string $value): string
    {
        if (str_contains($value, ':')) {
            [$w, $h] = array_pad(array_map('trim', explode(':', $value, 2)), 2, '1');
            $w = is_numeric($w) ? (float) $w : 1.0;
            $h = is_numeric($h) ? (float) $h : 1.0;

            if ($h === 0.0) {
                $h = 1.0;
            }

            return $w . ' / ' . $h;
        }

        return $value;
    }
}

