# C-Can Sam

A marketing site for a shipping container business. Astro generates static HTML. PHP handles forms and APIs. That is the whole thing.

**Production:** https://ccansam.com  
**Staging:** https://ccan.crkid.com

## The philosophy

Most small business sites are over-engineered. This one is not. It is static HTML with PHP endpoints, deployed to a $6/month server. Lighthouse scores are 100 across the board because there is nothing to optimize away—no client-side framework, no hydration, no JavaScript bundles.

The entire site rebuilds in under 3 seconds. Deploys take 15 seconds. There is no build queue, no CI pipeline to debug, no Docker containers. You run one command, it SSHs in, pulls, builds, done.

## Running locally

```bash
npm install
npm run dev      # localhost:4321
npm run build    # outputs to dist/
```

## Deploying

```bash
npm run deploy:staging   # ccan.crkid.com
npm run deploy:prod      # ccansam.com
```

Both commands SSH into the server, pull latest changes, and rebuild. If there are local changes on the server (happens occasionally with config tweaks), it will fail—SSH in and `git stash` first.

First time on a fresh Ubuntu 24 server:
```bash
curl -O https://raw.githubusercontent.com/stooky/ccan/storage-containers/deploy.sh
DOMAIN=ccansam.com EMAIL=you@example.com sudo bash deploy.sh
```

## How it is organized

```
config.yaml          <- Single source of truth
src/
  pages/             <- File-based routing
  content/blog/      <- Markdown blog posts
  components/        <- Reusable Astro components
  layouts/           <- Page layouts
  config/site.ts     <- TypeScript interface to config.yaml
api/
  contact.php        <- Contact form handler
  quote.php          <- Inventory quote requests
  admin.php          <- Admin panel
data/
  inventory.json     <- Live container inventory
  submissions.json   <- Contact form logs
  quote-requests.json <- Quote request logs
public/              <- Images, favicons, static files
```

The `config.yaml` file is the single source of truth. Site name, phone number, analytics IDs, API keys, email settings—all in one place. Change something once, it changes everywhere.

## Inventory system

The inventory checker at `/inventory-checker/` displays live container stock from `data/inventory.json`. Key design decisions:

**Prices are hidden from customers.** The JSON contains supplier pricing, but the frontend only shows unit, condition, location, and quantity. When a customer clicks "Get Quote," they fill out name/email, and the business owner receives an email with the full details including cost—so they can calculate markup and delivery.

**No database.** Inventory is a flat JSON file. For 80 containers updated weekly, this is simpler than Postgres. Edit it directly or build an admin interface later.

**Quote requests are logged.** Every request saves to `data/quote-requests.json` with timestamp, customer info, and item details. Analytics without complexity.

## Blog

Markdown files in `src/content/blog/`. Frontmatter schema:

```yaml
---
title: "Your Title"
description: "SEO description"
pubDate: 2026-01-06
author: "C-Can Sam"
tags: ["shipping containers", "storage"]
image: "/images/blog/your-image.jpg"
imageAlt: "Descriptive alt text"
draft: false
---
```

Blog posts support embedded HTML for custom components. The FAQ sections use native `<details>` elements with scoped `<style>` blocks—no JavaScript required, works everywhere.

**Image processing:** Blog images need multiple sizes. The `heic-convert` dev dependency handles iPhone photos. Sharp (bundled with Astro) handles resizing.

## Analytics

GTM handles everything. The container ID is in `config.yaml`. GA4 runs inside GTM, not as a separate script—this gives you one tag to manage and better control over what fires when.

**No cookie consent banner.** This site operates in Canada under PIPEDA, which does not require opt-in consent for analytics. The GDPR-style cookie banner was removed to avoid asking permission we do not legally need. If you are deploying in the EU, you will need to add it back—the old `CookieConsent.astro` component is in git history.

## The form system

Contact forms POST to `api/contact.php`. The quote system uses `api/quote.php`. Both:

1. Validate server-side (never trust the client)
2. Run through multi-layer spam protection (see Security below)
3. Send via Resend API (actually delivers to inboxes)
4. Log to JSON (you own your data)

Falls back to PHP `mail()` if no Resend key, but that usually lands in spam.

## Admin panel

Access at `/api/admin.php?key=YOUR_SECRET` (set `admin.secret_path` in config). From here you can:

- View contact form submissions
- View quote requests
- Manage Google reviews
- Tag reviews to pages using AI matching

The admin is intentionally minimal. It is a PHP file that reads JSON, not a React dashboard with auth flows. For a site with one admin, this is plenty.

## Reviews

Google reviews display on relevant pages. The admin panel "Tag Reviews" feature uses AI to match reviews to pages based on content. A review mentioning "40ft container" goes on the 40ft product page.

Layouts: `featured`, `standard`, `3-row`, `compact`. Configure per-page in `config.yaml`.

## Multi-tenant support

This codebase supports multiple business verticals. Stash the current config, restore another:

```bash
npm run stash     # Archives current config to backup/stash/
npm run restore   # Interactive restore from backup
```

The HVAC vertical (Fire & Frost Mechanical) lives in `backup/stash/`. Same codebase, different branding, instant swap.

## Performance

Lighthouse 100s are not a goal, they are a side effect of not doing stupid things:

- Static HTML (no hydration overhead)
- Responsive images with lazy loading and WebP
- System font stack (no web font requests)
- No third-party scripts blocking render
- Gzip compression via nginx

Total JavaScript shipped to the client: ~2KB for mobile nav toggle. Everything else is HTML and CSS.

## Stack

| Layer | Choice | Why |
|-------|--------|-----|
| Framework | Astro 5 | Static output, zero JS by default |
| Styling | Tailwind 4 | Utility classes, purged in production |
| Types | TypeScript | Catches config errors at build time |
| Backend | PHP 8 | Runs anywhere, no runtime to manage |
| Email | Resend | Actually delivers, generous free tier |
| Server | nginx + Ubuntu 24 | Boring, reliable, $6/month |
| SSL | Let's Encrypt | Free, auto-renewing via Certbot |

## Security

Covers the basics without pretending to be Fort Knox.

### Spam Protection (Multi-Layer)

Forms go through multiple invisible checks. All are configurable in `config.yaml` under `security:`.

#### Basic Protection

| Layer | What it does | Default |
|-------|--------------|---------|
| **Honeypot** | Hidden field that bots fill out, humans don't see | `website_url` field |
| **Time-based** | Rejects forms submitted in under N seconds (bots are instant) | 3 seconds minimum |
| **Rate limiting** | Max submissions per IP within time window | 10 per hour |
| **Content filter** | Blocks messages with spam phrases, excessive URLs, all-caps | See config for phrase list |
| **Disposable emails** | Blocks throwaway email domains (mailinator, tempmail, etc.) | ~20 domains blocked |

#### Advanced Bot Detection

Catches sophisticated bots that bypass basic filters (gibberish names/messages, fake phone numbers).

| Layer | What it does | Config Key |
|-------|--------------|------------|
| **Gibberish detection** | Entropy analysis catches random strings like "RLuWJgVL" | `gibberish_detection: true` |
| **Name validation** | Checks vowel ratio, consonant clusters, random caps | `name_validation: true` |
| **Phone validation** | Requires valid Canadian area codes (306, 639, etc.) | `phone_area_code_validation: true` |
| **Message word check** | Requires at least one real English word | `message_word_validation: true` |
| **Gmail dot pattern** | Flags emails with 3+ dots (e.g., `x.o.x.o@gmail.com`) | `gmail_dot_limit: 3` |

**How it catches spam:**
- Name "RLuWJgVLqRmIFixr" fails: random caps, low vowel ratio
- Message "txKSMOAQNXRvxXvezHI" fails: no real words, high entropy
- Phone "5996424987" fails: invalid area code (599 is Caribbean)
- Email "xoxob.a.ya.qo8.3@gmail.com" fails: 4 dots in username

Spam attempts are logged to `data/spam-log.json` for analysis. Legitimate-looking rejections pretend to succeed (no feedback for spammers).

**Adding to blocklists:** Edit `security.spam_phrases` or `security.disposable_domains` in config.yaml.

**Adding area codes:** Edit `security.valid_area_codes` to include more Canadian/US area codes as needed.

### Infrastructure Security

- Input sanitization in all PHP endpoints
- Secret URL for admin (not auth, but sufficient for single-user)
- nginx blocks direct access to `data/`, `config.yaml`, `.git/`
- HTTPS with HSTS headers
- No user accounts = no passwords to leak

## What is not here

Things intentionally omitted:

- **CMS** — Three product pages do not need a database. Markdown is fine.
- **User auth** — One admin, one secret URL. Simple.
- **Client-side routing** — Every page is a full HTML document. Fast, accessible, works without JS.
- **Build pipeline** — No GitHub Actions, no Vercel, no preview deployments. SSH and `npm run build` is the pipeline.
- **Database** — JSON files for everything. At this scale, SQLite would be over-engineering.

The goal is a site that works reliably for years with minimal maintenance. Every dependency is a future liability. Every abstraction is complexity to debug at 2am.

---

Proprietary. All rights reserved.
