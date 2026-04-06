# Current Sprint — Bolt 4

**Goal:** Fix Critical and High security findings from audit
**Status:** In Progress
**Opened:** 2026-04-05
**Source:** Security Audit (20260405-180000) — 2 Critical, 4 High

## Items

| # | Item | Size | Status | Finding |
|---|------|------|--------|---------|
| 1 | Rotate admin secret, remove from config.yaml | S | pending | C1 |
| 2 | Replace PHP fallback defaults with fail-closed | S | pending | H3 |
| 3 | Redact admin secret from captain's logs | S | pending | C1 |
| 4 | Fix YAML injection in save_products | M | pending | C2 |
| 5 | Restrict CORS on all PHP endpoints | S | pending | M1, M2 |
| 6 | Fix HTML injection in quote.php email | S | pending | M3 |
| 7 | Run npm audit fix | S | pending | H4 |
| 8 | Add admin action audit logging | M | pending | M5 |
| 9 | Validate formType against allowlist | S | pending | M6 |

## Blockers

None.

## Metrics

- Commits this bolt: 0
- Findings fixed: 0/17
