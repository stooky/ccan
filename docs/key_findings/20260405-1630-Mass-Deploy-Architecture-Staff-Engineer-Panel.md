# Mass Deployment & Agent Management Architecture — Staff Engineer Panel Analysis

**Date:** 2026-04-05
**Panel:** Tim (SpaceX), Rob (Roblox), Fran (Meta), Al (AWS/VPS), Will Larson (Moderator)
**Trigger:** Review build/deploy architecture for scaling to mass deployment and agent-managed operations

---

## Problem Statement

The current architecture is a config-driven Astro static site with PHP backend, deployed to self-hosted VPS via SSH. It works well for a single site but has hardcoded domains, manual nginx configs, a stash/restore multi-site pattern that doesn't scale, and no agent-friendly operations surface.

**Current vs Target:**

| Dimension | Current (1 site) | Target (N sites) | Gap |
|-----------|------------------|-------------------|-----|
| Deploy time | ~15s (SSH + build) | N × 15s sequential | No parallelism |
| Config management | 1 config.yaml | N configs, diverging | Stash/restore doesn't scale |
| nginx management | 2 manual files | N × 2 files | Not auto-generated |
| Agent autonomy | Can run deploy.js | Needs to provision, configure, SSL, DNS | No provisioning automation |
| Monitoring | None | N sites to watch | No health checks |

**Constraints:** $6/mo VPS per site, solo dev + AI agents, PHP required, must remain simple.

---

## Panel Analysis

### Tim — Staff Engineer, SpaceX

**Risk assessment:** Architecture is well-designed for single site. Scaling via stash/restore is a data integrity risk — one bad swap wipes another site's config. Grows linearly with site count.

**Key quote:** "The stash/restore pattern is a prototype of the right idea. The problem isn't the concept — it's that swapping files in a single directory is a race condition waiting to happen."

**Recommendation:** Multi-tenant monorepo with `sites/<domain>/` structure. Each site gets own config.yaml, data/, and nginx conf. ~2 days.

**Unique contribution:** `site` field in `astro.config.mjs` is hardcoded to `https://ccansam.com`. Sitemaps, canonical tags, and OG URLs will be wrong for any other domain. Must be parameterized via `SITE_URL` env var — Astro supports this natively.

---

### Rob — Staff Engineer, Roblox

**Risk assessment:** Current deploy is NOT idempotent — `git pull` on dirty working tree can fail, `npm run build` can fail silently with stale node_modules. No health check after deploy. Agent can't confirm success.

**Key quote:** "The difference between 'it deployed' and 'it works' is a health check. You have the first. You need the second."

**Recommendation:** Site manifest, post-deploy health check, idempotent deploy. ~3 days.

**Unique contribution:** PHP reads `config.yaml` from hardcoded path relative to `__DIR__`. Multiple sites on same server = PHP config collisions. Need per-site config path, or per-site PHP-FPM pools for proper isolation.

---

### Fran — Staff Engineer, Meta

**Risk assessment:**

Scales fine: config.yaml as source of truth, Astro static build, PHP flat-file backend, SSH deploy.
Doesn't scale: hardcoded domains, single config with stash/restore, no post-deploy verification, no provisioning automation, sequential builds.

**Key quote:** "You're one environment variable and one directory restructure away from N-site deployment. Don't let anyone sell you on Kubernetes for this."

**Recommendation:** Three phases: parameterize build (1 day), manifest + operations (1 day), provisioning (2 days).

**Unique contribution:** nginx configs should be generated from a template with `{{domain}}`, `{{ssl_cert_path}}`, `{{redirects}}` placeholders. Eliminates redirect duplication between config.yaml and nginx, and means adding a site doesn't require writing a nginx conf by hand.

---

### Al — Staff Engineer, AWS (VPS/Infrastructure)

**Risk assessment:** The VPS + static + PHP architecture is correct for the constraint set. The temptation to jump to containers/serverless should be resisted. What's needed is process isolation and a control plane.

**Key quote:** "Multi-tenancy on a VPS is just isolation done manually. PHP-FPM pools give you process isolation. Separate directories give you filesystem isolation. Separate nginx server blocks give you network isolation. You don't need Docker to get isolation."

**Recommendation:** Per-site directories, nginx template, PHP-FPM pool template, sites.yaml manifest, provisioning script. ~3 days.

**Unique contribution:** Per-site PHP-FPM pools with `open_basedir` give process AND filesystem isolation. One compromised PHP script can't read another site's data. Config: `pm = ondemand`, `pm.max_children = 3` per pool keeps memory bounded. $6/mo VPS handles 5-10 sites.

**Capacity planning:** A $6/mo Vultr instance (1 vCPU, 1GB RAM) comfortably hosts 5-10 Astro+PHP sites. Need VPS #2 around site 8-10.

---

## Consensus Matrix

| Question | Tim (SpaceX) | Rob (Roblox) | Fran (Meta) | Al (AWS) |
|----------|-------------|-------------|-------------|----------|
| Keep VPS model? | YES (5) | YES (4) | YES (5) | YES (5) |
| sites/ directory structure? | YES (5) | YES (4) | YES (5) | YES (5) |
| sites.yaml manifest? | NO (3) | YES (5) | YES (4) | YES (5) |
| nginx conf template? | YES (3) | YES (4) | YES (5) | YES (5) |
| PHP-FPM pool per site? | NO (2) | YES (3) | NO (2) | YES (5) |
| Parameterize SITE_URL? | YES (5) | YES (5) | YES (5) | YES (5) |
| Post-deploy health check? | YES (4) | YES (5) | YES (5) | YES (4) |
| Provisioning script? | NO (4) | YES (3) | YES (3) | YES (4) |

### Vote Tally

| Decision | For | Against | Confidence (avg) | Result |
|----------|-----|---------|-------------------|--------|
| Keep VPS model | 4-0 | 0 | 4.75 | APPROVED |
| sites/ directory structure | 4-0 | 0 | 4.75 | APPROVED |
| sites.yaml manifest | 3-1 | Tim | 4.25 | APPROVED |
| nginx template generation | 4-0 | 0 | 4.25 | APPROVED |
| PHP-FPM pool per site | 2-2 | Tim, Fran | 3.0 | SPLIT (deferred) |
| Parameterize SITE_URL | 4-0 | 0 | 5.0 | APPROVED |
| Post-deploy health check | 4-0 | 0 | 4.5 | APPROVED |
| Provisioning script | 3-1 | Tim | 3.5 | APPROVED |

### Dissent Record

| Panelist | Decision | Position | Key Concern | Risk if Ignored |
|----------|----------|----------|-------------|-----------------|
| Tim | sites.yaml manifest | AGAINST | YAGNI — sites/ directory IS the manifest | Minor maintenance burden |
| Tim | Provisioning script | AGAINST | Premature at <5 sites | Manual provisioning is fine for <5 |
| Tim | PHP-FPM pools | AGAINST | Over-engineering for single-owner sites | PHP crash takes down all sites |
| Fran | PHP-FPM pools | AGAINST | Operational complexity for solo dev | Same as Tim |

---

## Will Larson's Decision

Fran's phased approach with Al's isolation insights deferred to Phase 3.

### Phase 1 — Parameterize the Build (~3.5 hours)

| Step | What | Why | Effort |
|------|------|-----|--------|
| 1a | Add SITE_URL env var to astro.config.mjs | Sitemaps, canonicals, OG tags need correct domain | 15 min |
| 1b | Create sites/ccansam.com/ directory, move config.yaml | Establish N-site directory pattern | 30 min |
| 1c | Update src/config/site.ts to accept SITE_DIR env var | Build reads from per-site config | 30 min |
| 1d | Update deploy.js to accept --site domain flag | Deploy targets specific site | 1 hour |
| 1e | Update PHP to find config via env var or site root | Runtime config per site | 1 hour |

### Phase 2 — Manifest + Operations (~3.5 hours)

| Step | What | Why | Effort |
|------|------|-----|--------|
| 2a | Create sites.yaml manifest | Agent-readable site registry | 30 min |
| 2b | Create deploy/nginx.conf.template | Auto-generate nginx confs | 1 hour |
| 2c | Add post-deploy health check to deploy.js | "Deployed" ≠ "works" | 30 min |
| 2d | Add --all flag for selective multi-site deploy | Deploy only changed sites | 1 hour |
| 2e | Move all secrets to config.local.yaml | Secrets per-server, not per-repo | 30 min |

### Phase 3 — Provisioning + Isolation (~5.5 hours)

| Step | What | Why | Effort |
|------|------|-----|--------|
| 3a | Create provision-site.sh | One command to add a site | 2 hours |
| 3b | Per-site PHP-FPM pools (when >5 sites) | Process isolation, open_basedir | 1 hour |
| 3c | Server capacity planning | ~8-10 sites per $6/mo VPS | 30 min |
| 3d | Agent API: create-site, deploy-site, check-site, update-config | Four commands for full autonomy | 2 hours |

**Total: ~12.5 hours across 3 bolts.**

### Agent API Surface (Target State)

```
create-site <domain> <server>     # Provision new site on target server
deploy-site <domain>              # Build and deploy a specific site
check-site <domain>               # Health check: HTTP 200 + key strings
update-config <domain> <key> <val> # Update a site's config.yaml and redeploy
```

### Target Architecture (N sites on M servers)

```
Repo (single monorepo)
├── sites/
│   ├── ccansam.com/
│   │   ├── config.yaml
│   │   ├── data/          (gitignored — server-side only)
│   │   └── pages/         (site-specific page overrides if needed)
│   ├── fireandfrost.ca/
│   │   ├── config.yaml
│   │   └── ...
│   └── sites.yaml         (manifest: domain → server → status)
├── src/                    (shared Astro components/layouts)
├── api/                    (shared PHP backend)
├── deploy/
│   ├── nginx.conf.template
│   └── php-fpm.pool.template
└── scripts/
    ├── deploy.js           (--site <domain> or --all)
    ├── ship.js             (commit + push + deploy wrapper)
    └── provision.js        (new site setup)
```

### Decision Records

```
DECISION: Keep VPS model, no containers/serverless | VOTE: 4-0 | CONFIDENCE: 4.75 | DISSENT: NONE
DECISION: Multi-tenant monorepo with sites/<domain>/ | VOTE: 4-0 | CONFIDENCE: 4.75 | DISSENT: NONE
DECISION: Parameterize SITE_URL and SITE_DIR env vars | VOTE: 4-0 | CONFIDENCE: 5.0 | DISSENT: NONE
DECISION: sites.yaml manifest for agent operations | VOTE: 3-1 | CONFIDENCE: 4.25 | DISSENT: Tim: YAGNI at <5 sites
DECISION: nginx conf template generation | VOTE: 4-0 | CONFIDENCE: 4.25 | DISSENT: NONE
DECISION: Post-deploy health check | VOTE: 4-0 | CONFIDENCE: 4.5 | DISSENT: NONE
DECISION: Provisioning script (Phase 3) | VOTE: 3-1 | CONFIDENCE: 3.5 | DISSENT: Tim: premature at <5 sites
DECISION: Defer PHP-FPM pools to Phase 3 | VOTE: 2-2 (Will breaks tie) | CONFIDENCE: 3.0 | DISSENT: Al/Rob: blast radius
```

### What's Explicitly Deferred

| Item | Rationale | Revisit When |
|------|-----------|--------------|
| PHP-FPM pool per site | Ops complexity > benefit for solo dev at <5 sites | >5 sites on one VPS OR multi-owner |
| Docker/containers | No benefit over VPS at this scale | >50 sites or deploying to cloud |
| CI/CD pipeline | Solo dev + agent can run deploy.js directly | Second human developer joins |
| Database (from JSON) | Flat-file works at current volumes | Any site exceeds ~10K records |
| CDN | VPS serves static fine with gzip | Traffic exceeds VPS bandwidth |

---

## Key Takeaways

> "You're one environment variable and one directory restructure away from N-site deployment." — Fran

> "The agent's API surface should be four commands. Everything else is internal." — Fran

> "Multi-tenancy on a VPS is just isolation done manually. You don't need Docker." — Al

> "The difference between 'it deployed' and 'it works' is a health check." — Rob

> "The stash/restore pattern is a prototype of the right idea." — Tim

---

## Files Referenced

| File | Role |
|------|------|
| scripts/deploy.js | Main deploy script — SSH, backup, build, nginx, permissions |
| scripts/ship.js | Convenience wrapper — commit, push, call deploy.js |
| astro.config.mjs | Build config — hardcoded site URL needs parameterization |
| config.yaml | Single source of truth — drives Astro build + PHP runtime |
| src/config/site.ts | TypeScript bridge — reads YAML at build time, exports siteConfig |
| deploy/nginx-prod.conf | Production nginx — needs to become a template |
| deploy/nginx-staging.conf | Staging nginx — needs to become a template |
| api/admin.php | PHP backend pattern — reads config.yaml, flat-file JSON storage |
| scripts/stash.js | Current multi-site pattern — file swap, doesn't scale |
