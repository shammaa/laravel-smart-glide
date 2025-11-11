<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Components;

use Shammaa\SmartGlide\Support\SmartGlideManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\View\Component;

final class SmartBackground extends Component
{
    public function __construct(
        private readonly SmartGlideManager $manager,
        public string $src,
        public ?string $profile = null,
        public ?string $class = null,
        public array $params = [],
        public bool $lazy = true,
        public array $seo = [],
        public ?bool $schema = null,
        public ?string $alt = null,
        public array|string|bool|null $responsive = null
    ) {
    }

    public function render(): View
    {
        $breakpoints = $this->resolveBreakpoints();
        $baseParameters = $this->buildParameters();
        $styles = $this->buildStyles($breakpoints, $baseParameters);
        $seoAttributes = $this->seoAttributes();
        $defaultUrl = $this->manager->deliveryUrl($this->src, $baseParameters);
        $structuredData = $this->structuredData($defaultUrl, $baseParameters, $seoAttributes);

        return view('smart-glide::components.background', [
            'class' => $this->class,
            'styles' => $styles,
            'placeholder' => $this->lazy ? $this->buildPlaceholder($baseParameters) : null,
            'seoAttributes' => $seoAttributes,
            'structuredData' => $structuredData,
        ]);
    }

    private function buildParameters(array $overrides = []): array
    {
        $parameters = $this->params;

        if ($this->profile) {
            $parameters['profile'] = $this->profile;
        }

        return array_merge($parameters, $overrides);
    }

    private function buildStyles(array $breakpoints, array $baseParameters): array
    {
        $styles = [
            'base' => $this->backgroundImage($baseParameters),
        ];

        foreach ($breakpoints as $label => $width) {
            $styles["@media (min-width: {$width}px)"] = $this->backgroundImage(
                array_merge($baseParameters, ['w' => $width])
            );
        }

        return $styles;
    }

    private function backgroundImage(array $parameters): string
    {
        $url = $this->manager->deliveryUrl($this->src, $parameters);

        return "background-image: url('{$url}');";
    }

    private function buildPlaceholder(array $baseParameters): string
    {
        $params = array_merge($baseParameters, ['w' => 24, 'blur' => 20, 'q' => 40]);

        return $this->manager->deliveryUrl($this->src, $params);
    }

    private function seoAttributes(): array
    {
        $defaults = config('smart-glide.seo.background_attributes', [
            'role' => 'img',
        ]);

        $attributes = array_merge($defaults, $this->seo);

        foreach (['style', 'class'] as $forbidden) {
            unset($attributes[$forbidden]);
        }

        if ($this->alt) {
            $attributes['aria-label'] = $this->alt;
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

        return $this->normalizeBreakpoints(config('smart-glide.breakpoints', []));
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
}

