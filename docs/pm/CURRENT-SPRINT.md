# Current Sprint — Bolt 2

**Goal:** Parameterize build/deploy for N-site deployment
**Status:** In Progress
**Opened:** 2026-04-05
**Source:** Staff Engineer Panel — Mass Deploy Architecture (20260405-1630)

## Items

| # | Item | Size | Status | Notes |
|---|------|------|--------|-------|
| 1 | Add SITE_URL env var to astro.config.mjs | S | pending | Sitemaps, canonicals, OG tags |
| 2 | Create sites/ccansam.com/ directory, move config.yaml | S | pending | Establish N-site pattern |
| 3 | Update src/config/site.ts to accept SITE_DIR env var | S | pending | Build reads from per-site config |
| 4 | Update deploy.js to accept --site flag | M | pending | Deploy targets specific site |
| 5 | Update PHP to find config via site root | M | pending | Runtime config per site |
| 6 | Create sites.yaml manifest | S | pending | Agent-readable registry |
| 7 | Create deploy/nginx.conf.template | M | pending | Auto-generate nginx confs |
| 8 | Add post-deploy health check to deploy.js | S | pending | Verify site is live after deploy |

## Blockers

None.

## Metrics

- Commits this bolt: 0
- Tests: 0 (no test suite)
- Deploys: 0
