# Page Management Architecture — Staff Engineer Panel Analysis

**Date:** 2026-04-13
**Panel:** Tim (SpaceX), Rob (Roblox), Fran (Meta), Al (Vercel/Astro), Will Larson (Moderator)
**Trigger:** Review how pages are built/tracked for speed and accuracy with AI agents

---

## Problem Statement

34 pages with no central registry. City pages are 250-line copy-paste jobs. Titles, descriptions, FAQs, pricing all hardcoded per-page. Adding a city = create file + 3 config edits. Agent-unfriendly.

## Current State

| Concern | Status |
|---------|--------|
| Page registry | Missing — filesystem is canonical |
| City pages (9) | Full copies, ~15 string substitutions each |
| Product pages (4) | Full copies, partially config-driven (pricing) |
| Titles/descriptions | Hardcoded per page file |
| FAQs | Hardcoded inline arrays per page |
| Reviews | Config-driven (good pattern) |
| Nav registration | Manual 2-place edit in config.yaml |
| Schema selection | Manual import per page |

## Panel Decision

**Use Astro content collections for city + product pages.** Dynamic routes with `getStaticPaths()` from typed YAML data. Auto-generate nav from collections. Split config.yaml.

### Implementation Plan (~10 hours)

| Phase | What | Effort |
|-------|------|--------|
| 1 | Cities content collection + dynamic route → delete 9 files | 4.5h |
| 2 | Products content collection + dynamic route → delete 4 files | 2.5h |
| 3 | Auto-generate nav from collections | 1.5h |
| 4 | Lightweight page manifest for non-dynamic pages | 1.5h |

### Agent Workflow After Implementation

| Operation | Before | After |
|-----------|--------|-------|
| Add city page | Create 250-line file + 3 config edits | Add 1 YAML file to src/content/cities/ |
| Update city pricing | Find/replace across files | Edit collection entry |
| Audit all titles | Grep 34 files | Read collection entries |
| Verify nav matches pages | Manual diff | Build-time validation |
| Add schema to page type | Edit each page | Edit shared template |

### Key Decision Records

```
DECISION: Dynamic routes for city + product pages | VOTE: 4-0 | CONFIDENCE: 5.0
DECISION: Use Astro content collections | VOTE: 2-2 (Will breaks) | CONFIDENCE: 3.25
DECISION: Auto-generate nav from collections | VOTE: 3-1 | CONFIDENCE: 4.25
DECISION: FAQs in YAML frontmatter | VOTE: 3-1 | CONFIDENCE: 3.75
```
