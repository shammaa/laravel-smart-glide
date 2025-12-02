# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2024-01-01

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

