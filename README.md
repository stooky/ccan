# C-Can Sam

A marketing site for a shipping container business. Astro generates static HTML. PHP handles forms and APIs. That is the whole thing.

**Production:** https://ccansam.com
**Staging:** https://ccan.crkid.com

---

## Table of Contents

- [Philosophy](#philosophy)
- [Quick Start](#quick-start)
- [Architecture](#architecture)
- [Configuration](#configuration)
- [Deploying](#deploying)
- [Admin Panel](#admin-panel)
- [Inventory System](#inventory-system)
- [Reviews System](#reviews-system)
- [Blog](#blog)
- [Form System & Spam Protection](#form-system--spam-protection)
- [Data Management](#data-management)
- [Performance](#performance)
- [Security](#security)
- [Stack Decisions](#stack-decisions)
- [What Is Not Here](#what-is-not-here)
- [Troubleshooting](#troubleshooting)

---

## Philosophy

Most small business sites are over-engineered. This one is not.

It is static HTML with PHP endpoints, deployed to a $6/month server. Lighthouse scores are 100 across the board because there is nothing to optimize away—no client-side framework, no hydration, no JavaScript bundles.

The entire site rebuilds in under 3 seconds. Deploys take 15 seconds. There is no build queue, no CI pipeline to debug, no Docker containers. You run one command, it SSHs in, pulls, builds, done.

**The goal is a site that works reliably for years with minimal maintenance.** Every dependency is a future liability. Every abstraction is complexity to debug at 2am.

---

## Quick Start

```bash
# Install dependencies
npm install

# Run development server
npm run dev          # → localhost:4321

# Build for production
npm run build        # → outputs to dist/

# Deploy
npm run deploy:staging   # → ccan.crkid.com
npm run deploy:prod      # → ccansam.com
```

First time on a fresh Ubuntu 24 server:
```bash
curl -O https://raw.githubusercontent.com/stooky/ccan/storage-containers/deploy.sh
DOMAIN=ccansam.com EMAIL=you@example.com sudo bash deploy.sh
```

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         Browser                                  │
└─────────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                    nginx (Ubuntu 24.04)                         │
│  • SSL termination (Let's Encrypt)                              │
│  • Static file serving from /dist                               │
│  • PHP-FPM proxy for /api/*                                     │
│  • Gzip compression                                             │
└─────────────────────────────────────────────────────────────────┘
                              │
              ┌───────────────┴───────────────┐
              ▼                               ▼
┌─────────────────────────┐     ┌─────────────────────────────────┐
│     Static HTML         │     │          PHP 8.x                │
│   (Astro-generated)     │     │                                 │
│                         │     │  api/contact.php  → Email       │
│  • Product pages        │     │  api/quote.php    → Email       │
│  • Blog posts           │     │  api/admin.php    → Dashboard   │
│  • Landing pages        │     │  api/backup.php   → Backups     │
└─────────────────────────┘     └─────────────────────────────────┘
                                              │
                                              ▼
                                ┌─────────────────────────────────┐
                                │         JSON Files              │
                                │                                 │
                                │  data/inventory.json            │
                                │  data/submissions.json          │
                                │  data/reviews.json              │
                                │  data/quote-requests.json       │
                                │  data/spam-log.json             │
                                └─────────────────────────────────┘
```

### Request Flow

1. **Static pages** (99% of requests): nginx serves pre-built HTML directly from `/dist`. No PHP, no database, sub-10ms responses.

2. **Form submissions**: POST to `/api/*.php` → PHP validates, checks spam filters, sends via Resend API, logs to JSON.

3. **Admin operations**: Authenticated requests to `admin.php` manage reviews, trigger rebuilds, create backups.

---

## Configuration

`config.yaml` is the single source of truth. Site name, phone number, analytics IDs, API keys, email settings—all in one place.

```yaml
# Site identity
site:
  name: "C-Can Sam"
  tagline: "Saskatchewan's Container Source"
  phone: "1-306-555-1234"

# Email delivery
email:
  resend_api_key: "re_xxxxx"
  from_email: "C-Can Sam <noreply@ccansam.com>"

# Admin panel
admin:
  secret_path: "your-secret-key-here"
  openai_api_key: "sk-xxxxx"  # For AI review tagging

# Analytics
analytics:
  gtm_id: "GTM-XXXXXX"

# Security settings
security:
  honeypot_field: "website_url"
  min_form_time: 3
  rate_limit_max: 10
  rate_limit_window: 3600
  # ... spam protection settings
```

**Local overrides:** Create `config.local.yaml` for server-specific settings (API keys, paths). It merges on top of `config.yaml` and is gitignored.

### Data Versioning

The config includes a version number for data migrations:

```yaml
version: 2  # Increment when data format changes
```

When you deploy and the version changes, `scripts/migrate.js` runs automatically to transform data files.

---

## Deploying

```bash
npm run deploy:staging   # → ccan.crkid.com (develop branch)
npm run deploy:prod      # → ccansam.com (main branch)
```

### What Deploy Does

1. **Backs up data files** to `data/backups/{timestamp}/`
2. **Checks data version** from config.yaml
3. **Pulls latest code** from git
4. **Runs migrations** if version changed
5. **Cleans old backups** (keeps last 10)
6. **Builds the site** via Astro
7. **Fixes permissions** for www-data (PHP/web server)

### Deployment Architecture

```
┌──────────────────┐         SSH          ┌──────────────────┐
│   Your Machine   │ ──────────────────▶  │   Production     │
│                  │                       │   Server         │
│  npm run deploy  │                       │                  │
│                  │                       │  1. Backup data  │
└──────────────────┘                       │  2. Git pull     │
                                           │  3. npm build    │
                                           │  4. Fix perms    │
                                           └──────────────────┘
```

If there are local changes on the server (happens occasionally with config tweaks), deploy will fail—SSH in and `git stash` first.

---

## Admin Panel

Access at `/api/admin.php?key=YOUR_SECRET` (set `admin.secret_path` in config).

### Tabs

| Tab | Purpose |
|-----|---------|
| **Submissions** | View contact form entries with timestamps and content |
| **Quotes** | View inventory quote requests with customer details |
| **Reviews** | Manage Google reviews, add manual reviews, AI-tag to pages |
| **Backup** | Create/download/email backups of all data files |

### Features

**Reviews Management:**
- View all reviews with ratings and dates
- Add new reviews manually (for testimonials from other sources)
- Edit existing reviews
- Delete reviews with confirmation
- AI-powered tagging to match reviews to relevant pages

**Backup System:**
- Creates `.tgz` archives of all data files
- Emails backups via Resend API
- Keeps last 3 backups with rotation
- Shows file stats and record counts
- Warns when backup size exceeds 2MB

**Rebuild Site:**
- Triggers `astro build` directly from admin panel
- Shows real-time build output
- Useful after editing reviews or config

### The Admin Philosophy

The admin is intentionally minimal. It is a PHP file that reads JSON, not a React dashboard with auth flows. For a site with one admin, this is plenty.

---

## Inventory System

The inventory checker at `/inventory-checker/` displays live container stock from `data/inventory.json`.

### Design Decisions

**Prices are hidden from customers.** The JSON contains supplier pricing, but the frontend only shows unit, condition, location, and quantity. When a customer clicks "Get Quote," they fill out name/email, and the business owner receives an email with the full details including cost—so they can calculate markup and delivery.

**No database.** Inventory is a flat JSON file. For 80 containers updated weekly, this is simpler than Postgres. Edit it directly or through the admin panel.

**Quote requests are logged.** Every request saves to `data/quote-requests.json` with timestamp, customer info, and item details. Analytics without complexity.

### Inventory JSON Structure

```json
{
  "items": [
    {
      "id": "20-std-regina-001",
      "size": "20ft",
      "condition": "One-Trip",
      "location": "Regina, SK",
      "quantity": 5,
      "supplierCost": 2800,
      "notes": "Blue paint, recent arrival"
    }
  ],
  "lastUpdated": "2026-01-15"
}
```

---

## Reviews System

Google reviews display on relevant pages throughout the site.

### How Reviews Work

1. **Import:** Reviews are added via admin panel (manual entry or future Google import)
2. **Tagging:** AI matches reviews to pages based on content ("40ft container" → 40ft product page)
3. **Display:** Tagged reviews show on their assigned pages with appropriate layouts
4. **Rebuild:** After adding/editing reviews, rebuild the site to update static pages

### Review Layouts

Configure per-page in `config.yaml`:

| Layout | Use Case |
|--------|----------|
| `featured` | Hero section, large cards |
| `standard` | Default grid layout |
| `3-row` | Three reviews in a row |
| `compact` | Sidebar or footer placement |

### AI Review Tagging

The "Tag Reviews" feature uses OpenAI to analyze review content and match to relevant pages:

- Examines keywords, product mentions, service types
- Considers existing page assignments
- Suggests confidence scores
- Requires `admin.openai_api_key` in config

---

## Blog

Markdown files in `src/content/blog/`.

### Frontmatter Schema

```yaml
---
title: "Your Title"
description: "SEO description (155 chars)"
pubDate: 2026-01-06
author: "C-Can Sam"
tags: ["shipping containers", "storage"]
image: "/images/blog/your-image.jpg"
imageAlt: "Descriptive alt text"
draft: false
---
```

### Features

- **Markdown + HTML:** Blog posts support embedded HTML for custom components
- **FAQ sections:** Use native `<details>` elements with scoped styles—no JavaScript
- **Image processing:** Sharp handles resizing, `heic-convert` handles iPhone photos
- **Draft mode:** Set `draft: true` to hide from production

---

## Form System & Spam Protection

Contact forms POST to `api/contact.php`. Quote requests use `api/quote.php`. Both:

1. Validate server-side (never trust the client)
2. Run through multi-layer spam protection
3. Send via Resend API (actually delivers to inboxes)
4. Log to JSON (you own your data)

Falls back to PHP `mail()` if no Resend key, but that usually lands in spam.

### Spam Protection Layers

All configurable in `config.yaml` under `security:`.

#### Basic Protection

| Layer | What It Does | Default |
|-------|--------------|---------|
| **Honeypot** | Hidden field bots fill out, humans don't see | `website_url` |
| **Time-based** | Rejects forms submitted under N seconds | 3 seconds |
| **Rate limiting** | Max submissions per IP in time window | 10 per hour |
| **Content filter** | Blocks spam phrases, excessive URLs, all-caps | See config |
| **Disposable emails** | Blocks throwaway domains | ~20 domains |

#### Advanced Bot Detection

Catches sophisticated bots that bypass basic filters.

| Layer | What It Does |
|-------|--------------|
| **Gibberish detection** | Entropy analysis catches random strings |
| **Name validation** | Checks vowel ratio, consonant clusters |
| **Phone validation** | Requires valid Canadian area codes |
| **Message word check** | Requires at least one real English word |
| **Gmail dot pattern** | Flags emails with 3+ dots |

**Example catches:**
- Name "RLuWJgVLqRmIFixr" → fails (random caps, low vowels)
- Message "txKSMOAQNXRvxXvezHI" → fails (no real words)
- Phone "5996424987" → fails (599 is Caribbean)
- Email "x.o.x.o.x@gmail.com" → fails (4 dots)

**Spam logging:** All attempts logged to `data/spam-log.json`. Legitimate-looking rejections pretend to succeed (no feedback for spammers).

---

## Data Management

### File Locations

| File | Purpose | Backed Up |
|------|---------|-----------|
| `data/inventory.json` | Container stock | Yes |
| `data/reviews.json` | Google reviews | Yes |
| `data/submissions.json` | Contact form logs | Yes |
| `data/quote-requests.json` | Quote request logs | Yes |
| `data/spam-log.json` | Blocked spam attempts | Yes |
| `data/rate-limits.json` | IP rate limit tracking | No |
| `config.yaml` | Site configuration | Yes |

### Backup System

From the admin panel, create backups that:

- Archive all data files to `.tgz`
- Email the archive via Resend
- Auto-rotate (keeps last 3)
- Show file sizes and record counts

### Restoring from Backup

```bash
# Extract the backup
tar -xzf ccansam-backup-2026-01-17-143052.tgz

# Upload to server
scp -r data/ config.yaml root@ccansam.com:/var/www/ccan/

# Or commit to repo
git add data/ config.yaml
git commit -m "Restore from backup"
```

### Data Versioning & Migrations

When data structures change:

1. Increment `version` in `config.yaml`
2. Add migration logic to `scripts/migrate.js`
3. Deploy—migrations run automatically

```javascript
// scripts/migrate.js example
if (oldVersion < 2 && newVersion >= 2) {
  // Add 'source' field to reviews
  reviews.forEach(r => r.source = r.source || 'Google');
}
```

---

## Performance

Lighthouse 100s are not a goal, they are a side effect of not doing stupid things:

| Technique | Impact |
|-----------|--------|
| Static HTML | No hydration overhead |
| Responsive images | WebP with lazy loading |
| System font stack | Zero web font requests |
| No third-party scripts | Nothing blocking render |
| Gzip compression | nginx handles it |

**Total JavaScript shipped:** ~2KB for mobile nav toggle. Everything else is HTML and CSS.

### Build Performance

```
Full build:     ~3 seconds
Deploy time:    ~15 seconds
TTFB:           <50ms (static files)
```

---

## Security

Covers the basics without pretending to be Fort Knox.

### Application Security

- Input sanitization in all PHP endpoints
- Secret URL for admin (not auth, but sufficient for single-user)
- Form submissions rate-limited per IP
- Spam attempts logged, not revealed to attackers

### Infrastructure Security

- nginx blocks direct access to `data/`, `config.yaml`, `.git/`
- HTTPS with HSTS headers
- Let's Encrypt with auto-renewal
- No user accounts = no passwords to leak

### File Permissions

The deploy script automatically fixes permissions:

```bash
chown -R www-data:www-data dist/ data/ public/ config.yaml
```

This ensures PHP (running as www-data) can:
- Write to data files
- Create backups
- Trigger rebuilds

---

## Stack Decisions

| Layer | Choice | Why |
|-------|--------|-----|
| Framework | Astro 5 | Static output, zero JS by default |
| Styling | Tailwind 4 | Utility classes, purged in production |
| Types | TypeScript | Catches config errors at build time |
| Backend | PHP 8 | Runs anywhere, no runtime to manage |
| Email | Resend | Actually delivers, generous free tier |
| Server | nginx + Ubuntu 24 | Boring, reliable, $6/month |
| SSL | Let's Encrypt | Free, auto-renewing via Certbot |
| AI | OpenAI | Review tagging (optional) |

### Why Not [Alternative]?

**Why not Next.js/Nuxt/SvelteKit?**
Server components add complexity for a site that changes once a week. Static HTML is simpler and faster.

**Why not a headless CMS?**
Three product pages don't need a database. Markdown + YAML is version-controlled and free.

**Why not Vercel/Netlify?**
A $6 VPS handles this traffic easily and gives full control. No vendor lock-in, no surprise bills.

**Why not PostgreSQL/MySQL?**
JSON files for <1000 records is simpler. No connection pooling, no migrations framework, no ORM.

---

## What Is Not Here

Things intentionally omitted:

| Omission | Reason |
|----------|--------|
| **CMS** | Three product pages don't need a database |
| **User auth** | One admin, one secret URL |
| **Client-side routing** | Every page is a full HTML document |
| **Build pipeline** | SSH + `npm run build` is the pipeline |
| **Database** | JSON files at this scale |
| **Cookie consent** | Not required under Canadian PIPEDA |
| **Preview deployments** | Staging server serves that purpose |

---

## Troubleshooting

### Deploy fails with "local changes"

```bash
ssh root@ccansam.com
cd /var/www/ccan
git stash
exit
npm run deploy:prod
```

### Backup creation fails

Usually permissions. SSH in and run:

```bash
chown -R www-data:www-data /var/www/ccan/data
```

### Build fails from admin panel

Check that astro is accessible:

```bash
ssh root@ccansam.com
cd /var/www/ccan
./node_modules/.bin/astro build
```

If it works manually but fails from PHP, it's likely a permission issue with `dist/` or environment variables.

### Reviews not showing after edit

Reviews are baked into static HTML at build time. Click "Rebuild Site" in admin or redeploy.

### Forms not sending email

1. Check `email.resend_api_key` in config.yaml
2. Verify the domain is verified in Resend dashboard
3. Check `data/submissions.json`—if entries appear, the form works but email failed

### Rate limited during testing

Delete or truncate `data/rate-limits.json` to reset.

---

## Multi-Tenant Support

This codebase supports multiple business verticals. Stash the current config, restore another:

```bash
npm run stash     # Archives current config to backup/stash/
npm run restore   # Interactive restore from backup
```

The HVAC vertical (Fire & Frost Mechanical) lives in `backup/stash/`. Same codebase, different branding, instant swap.

---

## Project Structure

```
├── config.yaml              # Single source of truth
├── config.local.yaml        # Server-specific overrides (gitignored)
├── src/
│   ├── pages/               # File-based routing
│   ├── content/blog/        # Markdown blog posts
│   ├── components/          # Reusable Astro components
│   ├── layouts/             # Page layouts
│   └── config/site.ts       # TypeScript interface to config.yaml
├── api/
│   ├── contact.php          # Contact form handler
│   ├── quote.php            # Inventory quote requests
│   ├── admin.php            # Admin panel (all tabs)
│   └── backup.php           # Backup API
├── scripts/
│   ├── deploy.js            # SSH deployment script
│   └── migrate.js           # Data migration runner
├── data/
│   ├── inventory.json       # Live container inventory
│   ├── reviews.json         # Google reviews
│   ├── submissions.json     # Contact form logs
│   ├── quote-requests.json  # Quote request logs
│   └── backups/             # Backup archives (gitignored)
├── public/                  # Static assets
└── dist/                    # Build output (gitignored)
```

---

Proprietary. All rights reserved.
