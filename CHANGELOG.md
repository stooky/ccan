# Changelog

All notable changes to C-Can Sam will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.0] — 2026-04-05

### Added
- Multi-site build and deploy parameterization for N-site deployment

### Fixed
- Content Security Policy updated to allow Chart.js CDN for SEO report

## [1.1.0] — 2026-04-05

### Added
- Reports tab in admin panel with SEO performance report
- Multi-select bulk delete for form submissions
- Backup download button in admin panel
- Spam management system with learning capabilities
- Visitor traffic tracking with LLM/AI bot detection
- City landing pages for Moose Jaw and Swift Current with HowTo schema
- Service Areas dropdown in main navigation
- Google Merchant Center product feed with Product schemas
- Conversion optimization and promo CTAs across homepage and contact page
- Comprehensive SEO and LLM optimization improvements
- Rental page rewrite with consistent layout

### Changed
- Simplified robots.txt disallow rules
- Updated rental pricing and homepage copy
- Reorganized footer navigation with city pages and useful links

### Fixed
- Deploy pipeline: nginx config, security headers, script consolidation
- Form submission bulk delete variable reassignment bug

## [1.0.0] — 2026-01-18

### Added
- Astro 5 static site with Tailwind CSS and responsive design
- PHP backend with contact form handler and Resend email integration
- Admin panel with form submissions viewer and site rebuild controls
- Blog section with 6 launch articles and strategic CTAs
- Container detail pages for sales and rental inventory
- GDPR-compliant cookie consent banner
- Google Analytics 4 and Google Tag Manager integration
- SEO foundation: structured data, meta tags, OpenGraph, sitemaps
- Lighthouse performance optimizations (100 scores across categories)
- Legal pages: terms, privacy policy, shipping and return policy
- Deployment scripts for Ubuntu 24 with SSL and nginx
- Centralized configuration via single `config.yaml`
- Tabbed quote request form
- Favicon and PWA manifest setup

[Unreleased]: https://github.com/stooky/ccan/compare/v1.2.0...HEAD
[1.2.0]: https://github.com/stooky/ccan/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/stooky/ccan/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/stooky/ccan/releases/tag/v1.0.0
