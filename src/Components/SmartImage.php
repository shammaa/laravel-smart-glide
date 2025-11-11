<?php

declare(strict_types=1);

namespace AppWriters\SmartGlide\Components;

use AppWriters\SmartGlide\Support\SmartGlideManager;
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
        public array $seo = [],
        public ?bool $schema = null
    ) {
    }

    public function render(): View
    {
        $baseParameters = $this->buildParameters();
        $src = $this->manager->deliveryUrl($this->src, $baseParameters);
        $responsive = $this->buildSrcSet($baseParameters);
        $seoAttributes = $this->seoAttributes();
        $structuredData = $this->structuredData($src, $baseParameters, $seoAttributes);

        return view('smart-glide::components.img', [
            'src' => $src,
            'srcset' => $responsive['srcset'],
            'sizes' => $responsive['sizes'],
            'alt' => $this->alt,
            'class' => $this->class,
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

    private function buildSrcSet(array $baseParameters): array
    {
        $breakpoints = config('smart-glide.breakpoints', []);

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
}

