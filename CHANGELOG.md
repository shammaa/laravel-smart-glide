# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.3.0] - 2026-03-29

### Added

- **Headless JSON API** — New `GET /img-data/{path}` endpoint returns `src`, `srcset`, `sizes`, `blurDataUrl`, and JSON-LD schema as JSON, ready for React, Vue, or mobile consumption.
- **`imageExists()`** — Check whether the original source file exists before generating URLs.
- **`dimensions()`** — Read the native pixel width & height of any source image (via `getimagesize`).
- **`multipleUrls()`** — Generate signed delivery URLs for a set of widths in one call; returns `[width => url]`.
- **`apiPayload()`** — Build the complete JSON-ready payload (src, srcset, sizes, blurDataUrl, dimensions) directly from the Facade — ideal for Inertia.js props and Laravel API Resources.
- **`warmPath()`** — Pre-process and cache all renditions for a single image path (safe to call from queued jobs).
- **`forgetPath()`** — Evict all cached renditions for a given path; use after replacing the original file.
- **`cacheStats()`** — Return entry count, total disk size (MB), and the full manifest array for observability.
- **`smart-glide:warm` Artisan command** — Warm a single image or the **entire source directory** from the CLI; supports `--profile`, `--widths`, `--all`, and `--ext` flags.
- **`smart-glide:stats` Artisan command** — Display cache statistics as a formatted table; `--manifest` shows per-entry detail.
- **`api.enabled` config flag** — Toggle the `/img-data` JSON route without touching routes files.
- **`data_path` config key** — Customise the JSON API path prefix (default `/img-data`).

### Changed

- `SmartGlideServiceProvider` now registers `WarmSmartGlideCache` and `SmartGlideStats` alongside `ClearSmartGlideCache`.
- `SmartGlide` Facade updated with full `@method` PHPDoc coverage for all new methods.

---

## [2.2.0] - 2026-01-22


### Added
- **Headless & API Support**: Added `responsiveData()` method to `SmartGlide` facade and manager to extract `src`, `srcset`, and `sizes` as a plain array.
- **Refactored Components**: Improved `SmartImage` component architecture by offloading responsive logic to the manager.

## [2.1.0] - 2026-01-06

### Added
- **WebP/AVIF Auto-Negotiation**: Automatically serves the best format based on browser `Accept` headers.
- **LQIP (Blur-up) Placeholders**: Added `placeholder()` and `placeholderUrl()` methods for smooth loading.
- **CDN Integration**: Support for custom CDN domains in image URLs.
- **Throttled Cache Budgeting**: Optimized performance by reducing disk I/O during cache checks.
- **Manifest Synchronization**: CLI `clear-cache` now properly clears the manifest from the cache store.

## [2.0.0] - 2025-12-30

### Added
- Initial release
- Signed URLs & Validation with HMAC signatures
- Responsive Blade components (`<x-smart-glide-img>`, `<x-smart-glide-bg>`, `<x-smart-glide-picture>`)
- Smart Profiles for reusable WebP/AVIF compression presets
- Unified `/img` delivery path
- Intelligent caching with LRU-inspired cache manifest
- SEO-Ready attributes and JSON-LD ImageObject snippets
- Security rules (block remote sources, enforce max dimensions/quality)
- External profile file support
- Artisan command for cache clearing
- Scheduled cache purge functionality

### Features
- Automatic `srcset` and `sizes` generation
- Responsive image support with breakpoints
- Picture element support with multiple sources
- Background image component with media queries
- Browser cache headers and ETag support
- Parameter validation and sanitization
- Extension whitelisting

