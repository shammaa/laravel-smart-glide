<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Components;

use Shammaa\SmartGlide\Support\SmartGlideManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\View\Component;

final class SmartImage extends Component
{
    public function __construct(
        private readonly SmartGlideManager $manager,
        public string $src,
        public ?string $profile = null,
        public ?string $alt = null,
        public array $params = [],
        public ?string $class = null,
        public ?string $style = null,
        public ?int $width = null,
        public ?int $height = null,
        public ?string $aspectRatio = null,
        public array $seo = [],
        public ?bool $schema = null,
        public array|string|bool|null $responsive = null
    ) {
    }

    public function render(): View
    {
        $baseParameters = $this->buildParameters();
        $breakpoints = $this->resolveBreakpoints();
        $src = $this->manager->deliveryUrl($this->src, $baseParameters);
        $responsive = $this->buildSrcSet($baseParameters, $breakpoints);
        $seoAttributes = $this->seoAttributes();
        $styleAttr = $this->composeStyle($this->style, $this->aspectRatio);
        $structuredData = $this->structuredData($src, $baseParameters, $seoAttributes);

        return view('smart-glide::components.img', [
            'src' => $src,
            'srcset' => $responsive['srcset'],
            'sizes' => $responsive['sizes'],
            'alt' => $this->alt,
            'class' => $this->class,
            'style' => $styleAttr,
            'width' => $this->width,
            'height' => $this->height,
            'seoAttributes' => $seoAttributes,
            'structuredData' => $structuredData,
        ]);
    }

    private function buildParameters(): array
    {
        $parameters = $this->params;

        if ($this->profile) {
            $parameters['profile'] = $this->profile;
        }

        return $parameters;
    }

    private function buildSrcSet(array $baseParameters, array $breakpoints): array
    {
        if (empty($breakpoints)) {
            return ['srcset' => null, 'sizes' => null];
        }

        $entries = [];

        foreach ($breakpoints as $label => $width) {
            $params = array_merge($baseParameters, ['w' => $width]);
            $entries[] = sprintf('%s %dw', $this->manager->deliveryUrl($this->src, $params), $width);
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
            'contentUrl' => url($src),
            'url' => url($src),
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

    private function resolveBreakpoints(): array
    {
        if ($this->responsive === false) {
            return [];
        }

        if (is_array($this->responsive)) {
            return $this->normalizeBreakpoints($this->responsive);
        }

        if (is_string($this->responsive)) {
            $preset = config("smart-glide.responsive_sets.{$this->responsive}");

            if ($preset) {
                return $this->normalizeBreakpoints($preset);
            }

            $values = array_map('trim', explode(',', $this->responsive));

            return $this->normalizeBreakpoints($values);
        }

        $defaults = config('smart-glide.breakpoints', []);

        return $this->normalizeBreakpoints($defaults);
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

