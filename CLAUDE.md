# C-Can Sam — Project Context

## What This Project Is

Shipping container sales, rentals, and storage website for a Saskatchewan-based business. Built as a config-driven static site that can be templated for N businesses.

## Architecture

```
Astro (static)  →  dist/        →  nginx (serves HTML/CSS/JS)
config.yaml     →  site.ts      →  Astro components (build-time)
config.yaml     →  PHP backend  →  API endpoints (runtime)
data/*.json     →  flat-file DB →  PHP reads/writes (runtime)
```

- **Frontend:** Astro 5 + TailwindCSS 4. Static HTML, inlined CSS, ~2KB JS total.
- **Backend:** PHP 8 via nginx/PHP-FPM. Contact forms, admin panel, visitor tracking, review tagging.
- **Data:** Flat-file JSON (no database). Reviews, submissions, inventory, spam logs.
- **Hosting:** Self-hosted VPS ($6/mo, Ubuntu 24.04, nginx, Let's Encrypt SSL).

## Key Files

| File | Purpose |
|------|---------|
| `config.yaml` | Single source of truth — symlink to `sites/ccansam.com/config.yaml` |
| `sites/sites.yaml` | Site registry — lists all deployable sites |
| `src/config/site.ts` | Reads config.yaml at build time, exports `siteConfig` |
| `astro.config.mjs` | Astro build config — reads site URL from config.yaml |
| `api/_config.php` | Shared PHP config loader — supports SITE_ROOT env var |
| `scripts/deploy.js` | Main deploy script (backup, pull, build, nginx, health check) |
| `scripts/ship.js` | Convenience wrapper (commit + push + deploy) |
| `deploy/nginx-prod.conf` | Production nginx config (version controlled) |
| `deploy/nginx.conf.template` | Template for generating new site nginx configs |

## Multi-Site

The codebase supports N sites via environment variables:
- `SITE_DIR` — path to site config directory (Astro build-time)
- `SITE_URL` — override site URL (Astro build-time)
- `SITE_ROOT` — site root for PHP (set by nginx fastcgi_param, runtime)

Sites are registered in `sites/sites.yaml`. Each site has its own config at `sites/<domain>/config.yaml`.

## Deployment

### SSH Access
- **Production:** `ssh -i "~/.ssh/id_rsa 1" root@ccansam.com`
- **Staging:** `ssh -i "~/.ssh/id_rsa 1" root@ccan.crkid.com`

### Deploy Commands
```bash
npm run deploy:prod                              # Deploy to production
npm run deploy:staging                           # Deploy to staging
npm run ship "commit message"                    # Commit + push + deploy
node scripts/deploy.js production --site ccansam.com  # Explicit site
```

### Server Paths
- Install: `/var/www/ccan`
- Data: `/var/www/ccan/data/` (submissions, reviews, inventory — server-side only)
- Config: `/var/www/ccan/config.yaml` (symlink to sites/ccansam.com/config.yaml)
- Nginx: `/etc/nginx/sites-available/ccansam.com`
- Nginx logs: `/var/log/nginx/`

## Admin Panel
- Access via: `/api/admin.php?key=<ADMIN_SECRET_PATH>`
- Admin key is in `config.yaml` under `admin.secret_path`
- Do not hardcode the key in this file or commit it in plain text

## Conventions
- Config changes require a rebuild (`npm run build`) to take effect on static pages
- PHP reads config.yaml on every request — no rebuild needed for PHP-only settings
- Captain's logs go in `docs/captains_log/` with format `caplog-YYYYMMDD-HHMMSS-slug.txt`
- PM docs in `docs/pm/` (CURRENT-SPRINT.md, BACKLOG.md, SPRINT-LOG.md)
- Git remote uses SSH alias `github-stooky` (not HTTPS)

## Branch
- Main branch: `storage-containers`
- Both prod and staging deploy from `main`
