<?php

declare(strict_types=1);

namespace Shammaa\SmartGlide\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Shammaa\SmartGlide\Support\SmartGlideManager;

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

    public function render(): View|HtmlString
    {
        $responsive = $this->manager->responsiveData(
            path: $this->src,
            profile: $this->profile,
            parameters: $this->params,
            responsive: $this->responsive
        );

        $src = $responsive['src'];
        $seoAttributes = $this->seoAttributes();
        $styleAttr = $this->composeStyle($this->style, $this->aspectRatio);
        $structuredData = $this->structuredData($src, $responsive['parameters'], $seoAttributes);

        if ($this->shouldRenderInline()) {
            return $this->renderInlineImage(
                src: $src,
                srcset: $responsive['srcset'],
                sizes: $responsive['sizes'],
                alt: $this->alt,
                class: $this->class,
                style: $styleAttr,
                width: $this->width,
                height: $this->height,
                seoAttributes: $seoAttributes,
                structuredData: $structuredData,
            );
        }

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

    private function shouldRenderInline(): bool
    {
        $factory = app('view');

        if (! method_exists($factory, 'exists') || ! $factory->exists('smart-glide::components.img')) {
            return true;
        }

        $finder = method_exists($factory, 'getFinder') ? $factory->getFinder() : null;

        if (! $finder) {
            return false;
        }

        try {
            $path = $finder->find('smart-glide::components.img');
        } catch (\InvalidArgumentException) {
            return true;
        }

        if (! $path || ! is_file($path)) {
            return true;
        }

        return filesize($path) === 0;
    }

    private function renderInlineImage(
        string $src,
        ?string $srcset,
        ?string $sizes,
        ?string $alt,
        ?string $class,
        ?string $style,
        ?int $width,
        ?int $height,
        array $seoAttributes,
        ?string $structuredData
    ): HtmlString {
        $attributes = array_merge(
            [
                'src' => $src,
                'alt' => $alt ?? '',
                'width' => $width,
                'height' => $height,
                'class' => $class,
                'style' => $style,
                'srcset' => $srcset,
                'sizes' => $sizes,
            ],
            $seoAttributes
        );

        $attributeString = collect($attributes)
            ->filter(static fn ($value) => ! is_null($value) && $value !== '')
            ->map(static fn ($value, $attribute) => sprintf('%s="%s"', $attribute, e($value)))
            ->implode(' ');

        $html = '<img ' . $attributeString . ' />';

        if ($structuredData) {
            $html .= '<script type="application/ld+json">' . $structuredData . '</script>';
        }

        return new HtmlString($html);
    }
}

