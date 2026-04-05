# Deploy/Build Process — Staff Engineer Panel Analysis

**Date:** 2026-04-05
**Panel:** Tim (SpaceX), Rob (Roblox), Fran (Meta), Al (AWS/nginx), Will Larson (Moderator)
**Trigger:** Static files in `public/d4t4/r3p0rts/` not being served in production — all unknown URLs return homepage instead of content or 404 page

---

## Problem Statement

The deploy/build pipeline has three different paths (`deploy.js`, `ship.js`, and CLAUDE.md manual commands) that each do different subsets of a complete deployment. Static files aren't being served, the custom 404 page doesn't work, security headers aren't applied, and 30+ WordPress redirects are dead.

**Root cause chain:**
1. nginx config (not in repo) likely has SPA-style `try_files $uri $uri/ /index.html`
2. All unmatched URLs fall back to homepage instead of serving static files or 404
3. `vercel.json`, `_headers`, and `_redirects` are platform-specific configs for Vercel/Netlify that do nothing on nginx
4. Three deploy paths with inconsistent steps create unpredictable deployments

**Impact:**

| Impact | Detail |
|--------|--------|
| Reports broken | SEO report added but unreachable in production |
| 404 page dead | Custom 404.astro never shown — users see homepage |
| Security headers missing | vercel.json and _headers are inert on nginx |
| Redirects dead | 30+ WordPress redirects in _redirects not applied |
| Deploy inconsistency | 3 deploy paths with different behaviors |

**Deploy path comparison:**

| Deploy Path | Backup | Migrate | Build | Permissions | Nginx Reload |
|-------------|--------|---------|-------|-------------|-------------|
| `deploy.js` | Yes | Yes | Yes | Yes | **No** |
| `ship.js` | **No** | **No** | Yes | **No** | **No** |
| CLAUDE.md manual | **No** | **No** | Yes | Yes | Yes |

---

## Panel Analysis

### Tim — Staff Engineer, SpaceX

**Risk assessment:**

| Risk | P | C | Score |
|------|---|---|-------|
| Static files not served (nginx config) | 1.0 | HIGH | CRITICAL |
| Security headers missing (vercel.json inert) | 1.0 | MED | HIGH |
| Old WordPress redirects broken | 1.0 | MED | HIGH |
| Data loss from ship.js (no backup) | 0.3 | HIGH | HIGH |
| Permission errors after ship.js | 0.5 | MED | MEDIUM |

**Key quote:** "You don't have a deploy pipeline. You have three partial deploy scripts and a prayer."

**Recommendation:** Consolidate to one deploy script. Delete ship.js. Fix nginx. ~3 hours.

**Unique contribution:** `ship.js` uses `git add -A` (line 136) which will commit any file in the working directory. The `.gitignore` may catch most sensitive files, but `git add -A` in a deploy script is a data leak footgun.

---

### Rob — Staff Engineer, Roblox

**Risk assessment:** The cognitive load of three deploy paths is unsustainable. You have to remember which command does what and mentally diff them. Works when you wrote it yesterday, breaks when you come back in 3 months.

**Key quote:** "The bug isn't in the code. It's in the gap between what the developer thinks they deployed and what actually got deployed."

**Recommendation:** SSH in, grab nginx config, commit to repo. Consolidate to one deploy path. ~4 hours.

**Unique contribution:** The `_redirects` file has 30+ WordPress-era redirects that are completely dead. Anyone following old Google results, bookmarks, or backlinks hits the homepage. For a site doing active SEO work, this is undermining the effort daily.

---

### Fran — Staff Engineer, Meta

**Risk assessment — bucketed:**

- **Fix yesterday:** nginx try_files, security headers, WordPress redirects
- **Fix this sprint:** deploy script consolidation, nginx config into version control
- **Don't care yet:** Vercel migration, CI/CD pipeline

**Key quote:** "Three header files, zero headers applied. That's not defense in depth, that's defense in theory."

**Recommendation:** Two-phase fix. Phase 1: SSH in, fix nginx. Phase 2: consolidate deploy scripts. ~3 hours.

**Unique contribution:** `public/_headers` (Netlify format) and `vercel.json` both define security headers (CSP, X-Frame-Options, etc.) but neither applies on nginx. The site has zero security headers in production.

---

### Al — Staff Engineer, AWS (nginx/infrastructure)

**Risk assessment:** Classic platform mismatch. Config files for three platforms (Vercel, Netlify, Cloudflare Pages) but actual platform is nginx on a VPS. The nginx config — the ONLY config that matters — isn't in the repo.

Likely nginx config pattern causing the issue:
```nginx
try_files $uri $uri/ /index.html;
```

Correct pattern for Astro static sites:
```nginx
try_files $uri $uri.html $uri/ /404.html;
```

**Key quote:** "The nginx config is the single most important file for this site and it's the only file not in version control."

**Recommendation:** Full nginx fix: try_files, security headers, redirects, commit to repo, add to deploy script. ~4 hours.

**Unique contribution:** The `root` directive might point to `/var/www/ccan` (repo root) instead of `/var/www/ccan/dist` (build output). If so, nginx looks for source files rather than built files. Combined with SPA fallback, this produces exactly the observed behavior. The `chown` in `deploy.js` (line 129) only chowns specific directories — if root points elsewhere, permissions could also be wrong.

---

## Consensus Matrix

| Question | Tim (SpaceX) | Rob (Roblox) | Fran (Meta) | Al (AWS) |
|----------|-------------|-------------|-------------|----------|
| Fix nginx try_files? | YES (5) | YES (5) | YES (5) | YES (5) |
| Get nginx config into repo? | YES (4) | YES (5) | YES (4) | YES (5) |
| Consolidate deploy scripts? | YES (5) | YES (4) | YES (4) | YES (3) |
| Delete ship.js? | YES (5) | YES (4) | NO (3) | YES (3) |
| Convert _redirects to nginx? | YES (3) | YES (5) | YES (5) | YES (5) |
| Convert security headers to nginx? | YES (4) | YES (3) | YES (5) | YES (5) |
| Delete vercel.json? | YES (5) | YES (4) | NO (2) | YES (4) |
| Move to Vercel? | NO (5) | NO (4) | NO (4) | NO (5) |

### Vote Tally

| Decision | For | Against | Confidence (avg) | Result |
|----------|-----|---------|-------------------|--------|
| Fix nginx try_files | 4-0 | 0 | 5.0 | APPROVED |
| Get nginx config into repo | 4-0 | 0 | 4.5 | APPROVED |
| Consolidate deploy scripts | 4-0 | 0 | 4.0 | APPROVED |
| Delete ship.js | 3-1 | Fran | 3.75 | APPROVED |
| Convert _redirects to nginx | 4-0 | 0 | 4.5 | APPROVED |
| Convert security headers to nginx | 4-0 | 0 | 4.25 | APPROVED |
| Delete vercel.json | 3-1 | Fran | 3.75 | APPROVED |
| Move to Vercel | 0-4 | all | 4.5 | REJECTED |

### Dissent Record

| Panelist | Decision | Position | Key Concern | Risk if Ignored |
|----------|----------|----------|-------------|-----------------|
| Fran | Delete ship.js | AGAINST | Ship.js commit+push+deploy UX is valuable for solo dev | Losing convenience = more manual steps |
| Fran | Delete vercel.json | AGAINST | Could keep as documentation of intended headers | Dead files cause confusion |

---

## Clarifying Questions

| Question | Answer | Impact on Decision |
|----------|--------|--------------------|
| Is nginx using SPA fallback? | Can't verify without SSH — behavior consistent with `try_files $uri $uri/ /index.html` | Confirms root cause hypothesis |
| Does `root` point to `dist/` or repo root? | Unknown without SSH — PHP `location /api/` may use different root | Need to check — if root is wrong, that's the real bug |
| How often does Sam deploy? | Captain's logs show ~monthly | Consolidation still worth it — monthly means you forget the process |
| Is there a staging nginx config too? | Staging at `ccan.crkid.com` — likely same setup | Fix should apply to both |

---

## Will Larson's Decision

**Scope:** Al's comprehensive approach with Tim's ruthlessness about dead config files. Fran's dissent addressed by making `ship.js` a wrapper around `deploy.js` instead of deleting it.

| Step | What | Why | Effort |
|------|------|-----|--------|
| 1 | SSH in, `cat /etc/nginx/sites-enabled/*` | Can't fix what you can't see | 5 min |
| 2 | Fix `try_files` to `$uri $uri.html $uri/ /404.html` | Fixes static files, 404 page, clean URLs | 10 min |
| 3 | Add security headers as `add_header` directives | Zero security headers in production currently | 15 min |
| 4 | Convert `_redirects` to nginx `return 301` rules | 30+ broken WordPress redirects hurting SEO | 20 min |
| 5 | Commit full nginx config to `deploy/nginx.conf` | Version control the most important config file | 5 min |
| 6 | Consolidate `deploy.js` to include nginx SCP + reload | One script that does everything | 30 min |
| 7 | Refactor `ship.js` to call `deploy.js` after push | Keep the UX, eliminate the divergence | 20 min |
| 8 | Delete `vercel.json`, `public/_headers`, `public/_redirects` | Dead config files give false confidence | 5 min |
| 9 | Apply same fixes to staging | Parity between environments | 15 min |

**Total: ~2 hours.**

### Decision Records

```
DECISION: Fix nginx try_files to $uri $uri.html $uri/ /404.html | VOTE: 4-0 | CONFIDENCE: 5.0 | DISSENT: NONE
DECISION: Get nginx config into repo at deploy/nginx.conf | VOTE: 4-0 | CONFIDENCE: 4.5 | DISSENT: NONE
DECISION: Consolidate deploy to single path (deploy.js) | VOTE: 4-0 | CONFIDENCE: 4.0 | DISSENT: NONE
DECISION: Refactor ship.js as wrapper around deploy.js | VOTE: 3-1 | CONFIDENCE: 3.75 | DISSENT: Fran: keep ship.js UX (addressed by wrapper)
DECISION: Convert _redirects and headers to nginx config | VOTE: 4-0 | CONFIDENCE: 4.5 | DISSENT: NONE
DECISION: Delete inert platform config files | VOTE: 3-1 | CONFIDENCE: 3.75 | DISSENT: Fran: could keep as docs (overruled)
DECISION: Do NOT migrate to Vercel | VOTE: 0-4 | CONFIDENCE: 4.5 | DISSENT: NONE
```

### What's explicitly deferred

| Item | Rationale | Revisit When |
|------|-----------|--------------|
| Move to Vercel/Netlify | PHP backend requires a server | If PHP backend is rewritten |
| CI/CD pipeline | Single developer, overkill at current cadence | If second developer joins or deploys exceed weekly |
| Automated tests | No test suite; separate initiative | Next feature sprint |

### Key Takeaways

> "You don't have a deploy pipeline. You have three partial deploy scripts and a prayer." — Tim

> "The nginx config is the single most important file for this site and it's the only file not in version control." — Al

> "Three header files, zero headers applied. That's not defense in depth, that's defense in theory." — Fran

> "Those 30+ WordPress redirects aren't being served. Every old Google result lands on the homepage." — Rob

---

## Files Referenced

| File | Role |
|------|------|
| `scripts/deploy.js` | Primary deploy script — most complete but missing nginx reload |
| `scripts/ship.js` | Convenience wrapper — commit+push+deploy but skips backup/permissions/reload |
| `CLAUDE.md` | Manual deploy commands — only path that reloads nginx |
| `vercel.json` | Security headers for Vercel (inert on nginx) |
| `public/_headers` | Security headers for Netlify (inert on nginx) |
| `public/_redirects` | WordPress redirects for Netlify (inert on nginx) |
| `public/robots.txt` | Blocks `/d4t4/` from crawlers (working correctly) |
| `astro.config.mjs` | Static build config — no SSR adapter |
| `public/d4t4/r3p0rts/ccansam-seo-report.html` | The report that triggered this investigation |

---

## Implementation Plan

1. **SSH into production** — `cat /etc/nginx/sites-enabled/*` to get current config
2. **Fix `try_files`** — change to `$uri $uri.html $uri/ /404.html`
3. **Add security headers** — convert from `vercel.json` to nginx `add_header` directives
4. **Add WordPress redirects** — convert `_redirects` to nginx `return 301` rules
5. **Commit nginx config** — save to `deploy/nginx.conf` in repo
6. **Update `deploy.js`** — add SCP of nginx config + `systemctl reload nginx`
7. **Refactor `ship.js`** — make it a wrapper that calls `deploy.js` after git push
8. **Delete dead configs** — remove `vercel.json`, `public/_headers`, `public/_redirects`
9. **Apply to staging** — same nginx fixes for `ccan.crkid.com`
10. **Verify** — test report URL, 404 page, old WordPress URLs, security headers

## Findings to Fix

| # | File | Lines | Description | Fix |
|---|------|-------|-------------|-----|
| F1 | nginx config (on VPS) | try_files | SPA fallback serves homepage for all unknown URLs | Change to `$uri $uri.html $uri/ /404.html` |
| F2 | nginx config (on VPS) | headers | Zero security headers applied | Add `add_header` directives from vercel.json |
| F3 | nginx config (on VPS) | redirects | 30+ WordPress redirects not working | Convert _redirects to `return 301` rules |
| F4 | `scripts/deploy.js` | 126 | Missing nginx reload | Add `systemctl reload nginx` |
| F5 | `scripts/ship.js` | 89-103 | Duplicate deploy logic, missing backup/permissions/reload | Refactor to call deploy.js |
| F6 | `scripts/ship.js` | 136 | `git add -A` risks committing sensitive files | Review .gitignore coverage |
| F7 | `vercel.json` | all | Inert on nginx — false confidence | Delete after migrating headers to nginx |
| F8 | `public/_headers` | all | Inert on nginx — false confidence | Delete after migrating headers to nginx |
| F9 | `public/_redirects` | all | Inert on nginx — false confidence | Delete after migrating redirects to nginx |
