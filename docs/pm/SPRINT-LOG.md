# Sprint Log

Archive of completed bolts.

---

## Bolt 1 — Fix Deploy/Build Pipeline

**Goal:** Fix deploy/build pipeline and nginx configuration
**Opened:** 2026-04-05
**Closed:** 2026-04-05
**Status:** COMPLETE

### Outcome
Fixed nginx try_files, added security headers (CSP, Permissions-Policy, X-Frame-Options DENY), consolidated deploy scripts, removed dead config files (vercel.json, _headers, _redirects), moved admin secret out of CLAUDE.md, fixed package.json identity.

### Metrics
- Commits: 2 (9e0ff44, e34a8d3)
- Tagged: v1.1.0
- Deploys: 4 (2 prod, 2 staging)
- Items completed: 9/9

### Retro
- **What worked:** Staff engineer panel identified root cause quickly; parallel agent execution for audits
- **What didn't:** SSH key with space in filename (`id_rsa 1`) caused 20 min delay; CSP blocked Chart.js CDN (missed on first pass)
- **Lesson:** Always test with actual page content after adding CSP headers, not just curl -I
