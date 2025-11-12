# Laravel Smart Glide

Advanced image processing toolkit for Laravel with unified `/img` routing, responsive components, and intelligent caching.

---

## Features

- 🔐 **Signed URLs & Validation** — Protect image transformations with HMAC signatures, strict parameter validation, and extension whitelisting.
- 🖼️ **Responsive Components** — Blade components generate `srcset`, `sizes`, and background media queries automatically.
- ⚙️ **Smart Profiles** — Define reusable WebP/AVIF compression presets and override them from an external PHP profile file.
- 📦 **Unified Delivery Path** — All image responses flow through `/img/...`, simplifying CDNs, caching rules, and observability.
- 🧠 **Intelligent Caching** — LRU-inspired cache manifest with size budgets, warmup metadata, and background eviction.
- 🧾 **SEO-Ready** — Configurable attributes (`loading`, `fetchpriority`, `aria`, `title`, etc.) plus optional JSON-LD `ImageObject` snippets.
- 🛡️ **Security Rules** — Block remote sources (optional), enforce max width/height/quality ranges, and stricter MIME filters.
- 🧰 **DX Friendly** — Publishable config, auto-discovered components, and clean service provider wiring.

---

## Requirements

- PHP 8.3+
- Laravel 11.x or 12.x
- GD or Imagick PHP extension
- League Glide 3.x (installed automatically)

---

## Installation

```bash
composer require shammaa/laravel-smart-glide
```

The service provider and components are auto-discovered. If discovery is disabled, register the provider manually in `bootstrap/app.php` (Laravel 11+):

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        Shammaa\SmartGlide\SmartGlideServiceProvider::class,
    ])
    ->create();
```

Publish the configuration file when you want to customize paths, caching, or SEO defaults:

```bash
php artisan vendor:publish --tag=smart-glide-config
```

This creates `config/smart-glide.php` in your application.

---

## Quick Start

1. Place your original images inside `resources/assets/images` (default source path).
2. Ensure `storage/app/smart-glide-cache` is writable (default cache path).
3. Use the Blade component:

```blade
<x-smart-glide-img src="team/photo.jpg" alt="Team portrait" />
```

This renders a responsive `<img>` tag with signed URLs, lazy loading, and optimized defaults. All requests resolve to `/img/team/photo.jpg?...`.

---

## Configuration Overview

The config file (`config/smart-glide.php`) exposes several groups:

| Key | Description |
| --- | --- |
| `source`, `cache`, `delivery_path` | Control filesystem paths and the unified `/img` endpoint. |
| `security` | Toggle URL signing, allowed formats, max dimensions, quality ranges, and remote sources. |
| `profiles` & `profile_file` | Define reusable transformation presets; merge an external PHP file at runtime. |
| `breakpoints` | Global widths for automatic `srcset` generation. |
| `responsive_sets` | Named breakpoint aliases usable from the components. |
| `cache_strategy` | Enforce cache size (MB) and LRU time window. |
| `cache_headers` | Configure browser cache days and enable ETag responses. |
| `logging` | Enable processing logs and choose the log channel. |
| `seo` | Default attributes for `<img>` / background components and structured data behaviour. |

> Tip: set `SMART_GLIDE_SCHEMA_ENABLED=true` to emit JSON-LD `ImageObject` snippets for every component by default.

---

## Blade Components

### `<x-smart-glide-img>`

Render responsive `<img>` tags with minimal boilerplate.

```blade
<x-smart-glide-img
    src="portfolio/hero.jpg"
    profile="hero"
    alt="Product hero image"
    class="rounded-xl shadow-lg"
    :params="['fit' => 'crop', 'focus' => 'center']"
    :seo="[
        'fetchpriority' => 'high',
        'title' => 'Hero Image',
        'data-license' => 'CC-BY-4.0',
    ]"
    schema
/>
```

**Common properties**

| Prop | Type | Description |
| ---- | ---- | ----------- |
| `src` | string | Relative path (inside `smart-glide.source`). |
| `profile` | string | Apply a config profile (`profiles.hero`). |
| `params` | array | Additional Glide parameters (width, height, etc.). |
| `seo` | array | Extra HTML attributes (`fetchpriority`, `title`, `itemprop`, …). |
| `schema` | bool | Force JSON-LD emission for this image only. |

Structured data respects component-level overrides and merges extra fields from `config('smart-glide.seo.structured_data.fields')`.

#### How `srcset` Is Generated

- Smart Glide reads breakpoint widths from `config('smart-glide.breakpoints')` by default.
- For each width, the component clones your parameters, overrides `w`, and calls the delivery URL to produce entries like `/img/photo.jpg?w=640&... 640w`.
- The generated HTML will look similar to:
  ```html
  <img
      src="/img/photo.jpg?w=960&fm=webp&q=82&s=..."
      srcset="/img/photo.jpg?w=360&... 360w, /img/photo.jpg?w=640&... 640w, ..."
      sizes="(max-width: 360px) 100vw, (max-width: 640px) 100vw, ..."
  >
  ```
- Override widths without touching the config:
  ```blade
  <x-smart-glide-img src="gallery/piece.jpg" :responsive="[320, 640, 960]" />
  ```
- Use a named preset from `config('smart-glide.responsive_sets')`:
  ```blade
  <x-smart-glide-img src="hero.jpg" responsive="hero" />
  ```
- Disable `srcset` entirely when you want a single rendition:
  ```blade
  <x-smart-glide-img src="logo.png" :responsive="false" />
  ```
- If you need full control, set the attributes manually via `:seo="['sizes' => '...']"` or `['srcset' => '...']`.

### `<x-smart-glide-bg>`

Responsive background helper with media queries and placeholders.

```blade
<x-smart-glide-bg
    src="banners/summit.jpg"
    profile="hero"
    class="hero-banner"
    alt="Annual summit banner"
    lazy="true"
    :seo="['data-license' => 'Internal use only']"
    responsive="hero"
/>
```

The component outputs a wrapper `<div>` with inline styles plus an optional `<style>` block for breakpoint-specific backgrounds. A blurred placeholder overlay is included when `lazy` is `true`.

### `<x-smart-glide-picture>`

Responsive `<picture>` helper that lets you define multiple `<source>` breakpoints and a Smart Glide powered fallback.

```blade
<x-smart-glide-picture
    src="gallery/feature.jpg"
    alt="Feature image"
    class="feature-picture"
    :sources="[
        ['media' => '(min-width: 1200px)', 'widths' => [1200], 'params' => ['fit' => 'crop', 'w' => 1200, 'h' => 675]],
        ['media' => '(min-width: 768px)', 'widths' => [900], 'params' => ['w' => 900, 'h' => 506]],
    ]"
/>
```

Each source entry accepts:

| Key | Description |
| --- | ----------- |
| `media` | Optional media query for the `<source>` tag. |
| `src` | Override image path for this source (defaults to component `src`). |
| `params` | Glide parameters merged with the base parameters. |
| `profile` | Profile name to merge with parameters. |
| `widths` / `responsive` | Array or preset used to generate the `srcset`. |
| `sizes` | Custom `sizes` attribute. |
| `type` | MIME type hint for the source. |
| `srcset` | Custom array of descriptors if you need full control. |

The internal `<img>` fallback inherits Smart Glide features (SEO attributes, structured data, signed URLs) and can be styled separately via `img-class`.

---

## Signing & Security

- URLs are signed with HMAC using `SMART_GLIDE_SIGNATURE_KEY` or `APP_KEY` when `security.secure_urls` is enabled.
- Parameters are sanitized and validated against maximum width/height and quality limits.
- Remote image sources are rejected by default; enable them per environment with `SMART_GLIDE_ALLOW_REMOTE=true`.
- Allowed output formats (`fm`) and file extensions are configurable to avoid unsafe file types.

Requests with invalid signatures or disallowed parameters return `403/400` responses automatically.

---

## Smart Cache

Smart Glide stores processed images in the configured cache path and tracks metadata in a manifest stored in your cache store.

- `max_size_mb` limits total cache footprint.
- `lru_window` determines how far back "least recently used" entries remain before eviction.
- Metadata includes `cached_at`, `last_accessed`, and `cache_file` references to support background cleanup.

Responses include browser cache headers (`Cache-Control`, `Expires`) and optional `ETag`. HTTP clients respecting `If-None-Match` will receive `304 Not Modified` when appropriate.

---

## External Profiles

To allow non-developers to tweak compression parameters, create a PHP file that returns an array:

```php
<?php

return [
    'hero' => ['w' => 1920, 'h' => 1080, 'fm' => 'webp', 'q' => 80],
    'thumbnail' => ['w' => 320, 'h' => 320, 'fit' => 'crop', 'q' => 72],
];
```

Point `SMART_GLIDE_PROFILE_FILE` to this file. Its definitions merge on top of the inline `profiles` config.

---

## CLI Helpers

Add these convenience commands to your application (optional suggestions):

```php
// app/Console/Commands/ClearSmartGlideCache.php
```

Then run:

```bash
php artisan smart-glide:clear-cache
```

*(Command scaffolding is not bundled yet; feel free to implement it following your workflow.)*

---

## Testing

```bash
composer test          # PHPUnit (requires Laravel testing harness)
composer test-coverage # Coverage (if configured)
composer analyse       # Static analysis (PHPStan/Psalm if added)
```

Because this is a Laravel package, use [Orchestra Testbench](https://github.com/orchestral/testbench) for isolated package tests.

---

## Roadmap Ideas

- Remote URL whitelisting & SSRF hardening helpers.
- Middleware abstractions for header customization.
- First-class Artisan commands (cache clear, warmup, manifest stats).
- Vue/React components for hybrid stacks.

Contributions and issues are welcome once the package is public.

---

## License

Released under the [MIT License](LICENSE.md).

