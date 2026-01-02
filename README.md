# C-Can Sam

A marketing site for a shipping container business. Astro generates static HTML. PHP handles forms. That's the whole thing.

**Production:** https://ccansam.com
**Staging:** https://ccan.crkid.com

## The philosophy

Most small business sites are over-engineered. This one isn't. It's static HTML with a PHP form handler, deployed to a $6/month server. Lighthouse scores are 100 across the board because there's nothing to optimize away—no client-side framework, no hydration, no JavaScript bundles.

The entire site rebuilds in under 3 seconds. Deploys take 10 seconds. There's no build queue, no CI pipeline to debug, no Docker containers. You push to git, SSH in, run two commands, done.

## Running locally

```bash
npm install
npm run dev      # localhost:4321
npm run build    # outputs to dist/
```

## How it's organized

```
config.yaml          ← Everything lives here
├── src/             ← Astro components, pages, content
├── api/             ← PHP (forms, admin, reviews)
├── public/          ← Images, favicons, static files
├── data/            ← Form submissions, review data (JSON)
└── deploy.sh        ← One-command server bootstrap
```

The `config.yaml` file is the single source of truth. Site name, phone number, analytics IDs, API keys, URL redirects—all in one place. The Astro frontend reads it through `src/config/site.ts`. The PHP backend reads it directly. Change something once, it changes everywhere.

This matters more than it sounds. Most sites have config scattered across environment variables, admin panels, hardcoded strings, and database rows. When the phone number changes, you're hunting through five different places. Here, you edit one line.

## What you'll change

**Business info** — `config.yaml` has the name, tagline, contact details, hours, and social links. Start here.

**Pages** — File-based routing. `src/pages/about.astro` becomes `/about/`. The three product pages are separate files because there are only three products—no need for dynamic routes or a CMS.

**Blog** — Markdown files in `src/content/blog/`. Astro's content collections handle frontmatter validation and rendering.

**Analytics** — GA4 and GTM IDs in `config.yaml`. Both respect cookie consent—scripts only load after the user accepts.

**Email** — Uses Resend API (free tier: 3,000 emails/month). Add your API key to config. Falls back to PHP `mail()` if no key, but that usually lands in spam.

**Admin** — Set `admin.secret_path` to something unguessable. View submissions at `/api/admin.php?key=YOUR_SECRET`. Manage reviews from the same panel.

## The form system

Contact forms are a solved problem, but most solutions are either expensive (Formspree, Netlify Forms) or unreliable (PHP mail). This one:

1. Validates client-side (immediate feedback)
2. Checks honeypot field (catches bots)
3. Rate limits by IP (10/hour)
4. Sends via Resend API (actually reaches inboxes)
5. Logs to JSON (you own your data)

Test locally without sending email:
```bash
node api/contact-local.js
```

## Reviews

Google reviews display on relevant pages. The admin panel has a "Tag Reviews" feature that uses AI to match reviews to pages based on content. A review mentioning "40ft container" goes on the 40ft product page. A review about the rental experience goes on the rentals page.

Review layouts: `featured` (large cards), `standard` (with nav), `3-row` (three across), `compact` (minimal). Configure per-page in `config.yaml`.

## Deploying

First time on a fresh Ubuntu 24 server:
```bash
curl -O https://raw.githubusercontent.com/stooky/ccan/storage-containers/deploy.sh
DOMAIN=ccansam.com EMAIL=you@example.com sudo bash deploy.sh
```

This installs Node 20, nginx, PHP-FPM, Certbot. Configures SSL, firewall, redirects. Takes about 2 minutes.

Updates:
```bash
ssh root@ccansam.com "cd /var/www/ccan && git pull && npm run build"
```

The deploy script handles 27 redirects from the old WordPress URL structure. They're in nginx config via `config.yaml`, not application code. Old links and search results keep working.

## Cookie consent

GDPR-compliant. Four categories: necessary, analytics, marketing, preferences. Analytics scripts inject dynamically after consent. Preferences persist in localStorage. Users can change anytime via footer link.

The implementation is simpler than most cookie solutions because it's built into the site rather than loaded as a third-party script. No external requests, no GDPR-violating irony.

## Performance

Lighthouse 100s aren't a goal, they're a side effect. When you serve static HTML with responsive images and no JavaScript framework, there's nothing to slow down. The approach is:

- Static HTML (no hydration)
- Responsive images with lazy loading
- Font preloading
- Gzip compression
- No third-party scripts until consent

GTM handles conditional loading for heavy widgets. The Vendasta chat widget, for example, only loads on desktop—mobile users never download it.

## Stash/restore

This codebase supports multiple business verticals. The HVAC version (Fire & Frost Mechanical) is stashed in `backup/stash/fireandfrostmechanical.ca/`. Swap configs:

```bash
npm run stash     # Archive current config
npm run restore   # Restore from backup
```

Useful when one codebase serves multiple clients with different brandings.

## Data backup

Form submissions and reviews live in `data/` as JSON. Back them up:

```bash
npm run backup    # Emails a zip to admin
```

Or just copy `data/submissions.json` and `data/reviews.json` manually. They're plain text files.

## Stack

| Layer | Choice | Why |
|-------|--------|-----|
| Framework | Astro 5 | Static output, zero JS by default |
| Styling | Tailwind 4 | Utility classes, no CSS files to maintain |
| Types | TypeScript | Catches config errors at build time |
| Backend | PHP 8 | Runs anywhere, no runtime to manage |
| Email | Resend | Actually delivers, generous free tier |
| Server | nginx + Ubuntu 24 | Boring, reliable, cheap |
| SSL | Let's Encrypt | Free, auto-renewing |
| Analytics | GA4 + GTM | Industry standard, conditional loading |

## Security

Not comprehensive, but covers the basics:

- Honeypot field catches form bots
- Rate limiting prevents abuse
- Input sanitization in PHP
- Secret URL for admin panel
- nginx blocks access to `data/` and `config.yaml`
- HTTPS with HSTS

The philosophy: make the obvious attacks fail, don't pretend you're defending against nation-states.

---

Proprietary. All rights reserved.
