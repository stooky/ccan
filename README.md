# C-Can Sam

A static marketing site for a shipping container business. Astro generates HTML at build time; PHP handles form submissions. That's it.

**Production:** https://ccansam.com
**Staging:** https://ccan.crkid.com

## Running it

```bash
npm install
npm run dev      # localhost:4321
npm run build    # outputs to dist/
```

## How the pieces fit together

```
config.yaml          ← Single source of truth for everything
├── src/             ← Astro components, pages, content
├── api/             ← PHP backend (forms, admin)
├── public/          ← Images, favicons
└── deploy.sh        ← One-command server setup
```

The config file drives both the frontend (via `src/config/site.ts`) and the backend (PHP reads it directly). Change the business name, phone number, or email recipient in one place.

## What you'll actually need to change

**Business info** — `config.yaml` has everything: name, tagline, contact details, hours, social links.

**Navigation** — Same file. Main nav, footer sections, all defined declaratively.

**Analytics** — GA4 and GTM IDs live in `config.yaml`. Both respect cookie consent.

**Email** — Get a Resend API key, add it to config. The default `from_email` uses their sandbox domain for testing.

**Admin access** — Set `admin.secret_path` to something unguessable. Access submissions at `/api/admin.php?key=YOUR_SECRET`.

## Pages

The site has 12 pages. File-based routing means `src/pages/about.astro` becomes `/about/`.

Product pages (`/containers/20ft-standard/`, etc.) are separate files, not dynamic routes. There are only three products — no need for a CMS.

Blog posts live in `src/content/blog/` as Markdown. Astro's content collections handle the rest.

## The form system

Contact form flow:
1. Client-side validation
2. Honeypot check (spam)
3. Rate limiting (10/hour per IP)
4. Email via Resend
5. Log to JSON file

Test locally without sending email:
```bash
node api/contact-local.js
```

## Deploying

First time on a fresh Ubuntu 24 box:
```bash
curl -O https://raw.githubusercontent.com/stooky/ccan/storage-containers/deploy.sh
DOMAIN=ccansam.com EMAIL=you@example.com sudo bash deploy.sh
```

This installs everything: Node 20, nginx, PHP-FPM, Certbot. Configures SSL, redirects, firewall.

Subsequent deploys:
```bash
ssh root@server "cd /var/www/ccan && git pull && npm run build"
```

The deploy script includes 27 redirects from the old WordPress URL structure. They're in nginx config, not application code.

## Performance

Lighthouse 100s across the board. The approach is boring: static HTML, responsive images, font preloading, gzip. No client-side framework, no hydration, no JavaScript bundles to optimize.

## Cookie consent

GDPR-compliant banner with four categories. Analytics scripts only load after consent. Preferences persist in localStorage. Users can change via footer link.

## GTM mobile detection

Third-party widgets hurt mobile performance. Create a "Device Type" variable in GTM:

```javascript
function() {
  var ua = navigator.userAgent || '';
  var isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua);
  var isSmall = window.innerWidth < 768;
  var isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
  return (isMobile || (isSmall && isTouch)) ? 'mobile' : 'desktop';
}
```

Then create a "Desktop Only" trigger and use it instead of "All Pages" for heavy tags.

## Stash/restore

The site structure supports multiple verticals. Swap configs:

```bash
npm run stash     # Save current config to backup/
npm run restore   # Restore from backup/
```

## Backup/restore data

The site stores form submissions and reviews in JSON files under `data/`. Back these up periodically:

```bash
npm run backup    # Emails a zip to admin (requires Resend API key)
```

The email includes both files and a count summary. To restore:

1. Unzip the attachment
2. Upload to `/var/www/ccan/data/` on the server (or commit to `data/` in the repo)
3. Files are immediately active — no restart needed

**Manual backup:** Just copy `data/submissions.json` and `data/reviews.json` somewhere safe.

## Stack

Astro 5, Tailwind 4, TypeScript, PHP 8, Resend, nginx, Let's Encrypt.

## Security

Honeypot, rate limiting, input sanitization, secret admin URL, nginx blocks `data/` and `config.yaml`, HTTPS with HSTS.

---

Proprietary. All rights reserved.
