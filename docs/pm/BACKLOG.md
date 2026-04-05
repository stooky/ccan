# Backlog

**Last groomed:** 2026-04-05

## Completed (Bolts 1-3)

| # | Item | Bolt |
|---|------|------|
| B-001 | Fix nginx config (try_files, headers, redirects) | Bolt 1 |
| B-002 | Consolidate deploy scripts | Bolt 1 |
| B-003 | Get nginx config into version control | Bolt 1 |
| B-004 | Delete dead platform config files | Bolt 1 |
| B-005 | Parameterize build for N-site (SITE_URL, SITE_DIR) | Bolt 2 |
| B-006 | Create site registry (sites.yaml) | Bolt 2 |
| B-007 | Nginx template generation | Bolt 2 |
| B-008 | Post-deploy health check | Bolt 2 |
| SEO-004 | New vs Used comparison page | Pre-existing |
| SEO-005 | "How did you find us" form field | Pre-existing |
| SEO-006 | Container modifications page | Pre-existing |
| SEO-007 | Expand keyword variations | Pre-existing |
| SEO-008 | Industry certifications/badges (TrustBadges) | Pre-existing |
| SEO-009 | Staff/founder profile (Meet Sam) | Pre-existing (missing Person schema) |
| SEO-013 | Loyalty/referral program | Pre-existing |

## Priority: High — SEO Partial Completions

| # | Item | Size | Status | Notes |
|---|------|------|--------|-------|
| SEO-001 | FAQ Schema to remaining key pages | S | executable | On 4 pages, missing from city/product pages |
| SEO-002 | Additional city landing pages | S | executable | 9 of 13 target cities done — 4 remaining |
| SEO-003 | Deploy Testimonials component | S | executable | Component exists but not used on any pages |

## Priority: Medium — New Features

| # | Item | Size | Status | Notes |
|---|------|------|--------|-------|
| SEO-010 | Community involvement | S | blocked | Needs sponsorship/partnership info from Sam |
| SEO-011 | Service area map | M | executable | SVG map of 300km delivery radius |
| SEO-012 | Interactive container planner | XL | executable | Heavy dev — "PlanMyCan" style tool |
| B-009 | Add Person schema to About page | S | executable | Sam's founder profile exists but no schema |

## Priority: Low

| # | Item | Size | Status | Source |
|---|------|------|--------|--------|
| B-010 | Add automated test suite | XL | executable | DLC audit |
| B-011 | Add real founder photo to About page | S | blocked | Needs photo from Sam |
| B-012 | Create customer case study pages | M | executable | Captain's log |
| B-013 | External monitoring (UptimeRobot) | S | executable | Captain's log |
| B-014 | Provisioning script (provision-site.sh) | L | executable | Staff panel Phase 3 |
| B-015 | Agent API (create/deploy/check/update) | L | executable | Staff panel Phase 3 |

## Parked

| # | Item | Reason |
|---|------|--------|
| B-020 | Migrate to Vercel/Netlify | PHP backend requires VPS |
| B-021 | CI/CD pipeline | Single developer, low deploy cadence |
| B-022 | Per-site PHP-FPM pools | Defer until >5 sites per server |
| B-023 | Build locally, rsync dist/ | Defer until >2 servers |
