# Current Sprint — Bolt 1

**Goal:** Fix deploy/build pipeline and nginx configuration
**Status:** Planning
**Opened:** 2026-04-05
**Last commit:** b6ab489

## Items

| # | Item | Size | Status | Notes |
|---|------|------|--------|-------|
| 1 | SSH in, read nginx config | S | pending | Needed before any other fix |
| 2 | Fix nginx try_files directive | S | pending | `$uri $uri.html $uri/ /404.html` |
| 3 | Add security headers to nginx | M | pending | Convert from vercel.json |
| 4 | Convert _redirects to nginx rules | M | pending | 30+ WordPress redirects |
| 5 | Commit nginx config to repo | S | pending | deploy/nginx.conf |
| 6 | Consolidate deploy.js (add nginx SCP + reload) | M | pending | Single deploy path |
| 7 | Refactor ship.js as wrapper | S | pending | Keep UX, call deploy.js |
| 8 | Delete dead config files | S | pending | vercel.json, _headers, _redirects |
| 9 | Apply fixes to staging | M | pending | ccan.crkid.com parity |

## Blockers

| Blocker | Age | Ticket | Notes |
|---------|-----|--------|-------|
| SSH access needed | 0d | — | Can't fix nginx without server access |

## Metrics

- Commits this bolt: 0
- Tests: 0 (no test suite)
- Deploys: 0
