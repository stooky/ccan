# C-Can Sam

Static website for C-Can Sam, a shipping container sales and rental business in Saskatchewan, Canada.

**Production:** https://ccansam.com
**Staging:** https://ccan.crkid.com

## Quick Start

```bash
npm install
npm run dev      # Development server at localhost:4321
npm run build    # Production build to dist/
```

## Architecture

This is a static site generator with a PHP backend for form handling. The frontend builds to static HTML at deploy time; the backend runs on the server.

```
├── config.yaml              # All settings (site info, email, admin)
├── deploy.sh                # One-command Ubuntu deployment
├── api/
│   ├── contact.php          # Form handler → Resend email + JSON log
│   └── admin.php            # Submissions viewer (secret URL)
├── src/
│   ├── config/site.ts       # Loads config.yaml for Astro
│   ├── components/          # Astro components
│   ├── content/blog/        # Markdown blog posts
│   ├── layouts/Layout.astro # Base HTML template
│   ├── pages/               # File-based routing
│   └── styles/global.css    # Tailwind + custom CSS
└── public/                  # Static assets (images, favicons)
```

## Configuration

**All settings are in `config.yaml`** — the single source of truth for both frontend and backend.

### Site Settings

```yaml
site:
  name: "C-Can Sam"
  tagline: "Saskatchewan's Premier Seacan Solutions Provider"
  url: "https://ccansam.com"

contact:
  email: "ccansam22@gmail.com"
  phone: "1-844-473-2226"
  address:
    street: "12 Peters Avenue"
    city: "Martensville"
    state: "SK"

hours:
  monday: "9:00 AM - 5:00 PM"
  # ...

social:
  facebook: "https://www.facebook.com/ccansam"
  whatsapp: "https://wa.me/13062814100"
```

### Navigation

```yaml
navigation:
  main:
    - name: "Home"
      href: "/"
    - name: "Our Containers"
      href: "/storage-container-sales-and-rentals"
      children:
        - name: "Buy"
          href: "/storage-containers-for-sale"
        - name: "Rent"
          href: "/storage-container-rentals"
  footer:
    services: [ ... ]   # Buy, Rent, Lease, On-Site
    products: [ ... ]   # Container types (20ft, 40ft, High Cube)
    company: [ ... ]    # About, Blog, Referrals, Contact
    legal: [ ... ]      # Privacy, Terms
```

### Analytics

```yaml
analytics:
  # Google Analytics 4
  google_analytics:
    enabled: true                    # Set to true to enable
    measurement_id: "G-XXXXXXXXXX"   # From GA4 Admin > Data Streams

  # Google Tag Manager
  google_tag_manager:
    enabled: true                    # Set to true to enable
    container_id: "GTM-XXXXXXX"      # From tagmanager.google.com
```

Both GA4 and GTM only load after user accepts "Analytics" cookies (GDPR compliant).

### GTM: Mobile Device Detection

Third-party widgets (like chat) can hurt mobile performance and UX. Use this GTM variable to conditionally load tags on desktop only.

**Create Variable** (Variables → New → Custom JavaScript):

Name: `Device Type`

```javascript
function() {
  var ua = navigator.userAgent || '';
  var isMobileUA = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua);
  var isSmallScreen = window.innerWidth < 768;
  var isTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
  return (isMobileUA || (isSmallScreen && isTouch)) ? 'mobile' : 'desktop';
}
```

**Create Trigger** (Triggers → New → Page View → Some Page Views):

Name: `Desktop Only - All Pages`

Condition: `Device Type` equals `desktop`

**Usage:** Replace "All Pages" trigger with "Desktop Only - All Pages" on any tag that should skip mobile devices.

### Backend Settings

```yaml
contact_form:
  recipient_email: "you@example.com"
  subject_prefix: "[C-Can Sam Contact]"

email:
  resend_api_key: "re_..."        # From resend.com
  from_email: "C-Can Sam <onboarding@resend.dev>"

admin:
  secret_path: "your-secret-here"  # Admin panel access key
```

## Pages

| Route | Source | Description |
|-------|--------|-------------|
| `/` | `index.astro` | Homepage |
| `/about/` | `about.astro` | About page |
| `/contact/` | `contact.astro` | Contact form |
| `/storage-containers-for-sale/` | `storage-containers-for-sale.astro` | Sales page |
| `/storage-container-rentals/` | `storage-container-rentals.astro` | Rentals page |
| `/on-site-storage-rentals/` | `on-site-storage-rentals.astro` | On-site storage |
| `/containers/20ft-standard/` | `containers/20ft-standard.astro` | Product detail |
| `/containers/40ft-standard/` | `containers/40ft-standard.astro` | Product detail |
| `/containers/40ft-high-cube/` | `containers/40ft-high-cube.astro` | Product detail |
| `/blog/` | `blog/index.astro` | Blog listing |
| `/blog/[slug]/` | `blog/[...slug].astro` | Blog posts (dynamic) |
| `/terms/` | `terms.astro` | Terms & conditions |
| `/privacy/` | `privacy.astro` | Privacy policy |
| `/referrals/` | `referrals.astro` | Referral program |

## Components

| Component | Purpose |
|-----------|---------|
| `Navigation.astro` | Header with mobile hamburger menu |
| `Footer.astro` | 6-column footer: brand, services, products, company, contact |
| `Hero.astro` | Full-width hero with background image |
| `Section.astro` | Content section wrapper |
| `CTA.astro` | Call-to-action block |
| `ContactForm.astro` | Form with honeypot, validation, submission |
| `CookieConsent.astro` | GDPR cookie banner with preferences modal |
| `BlogCard.astro` | Blog post preview card |

## Blog Posts

Create Markdown files in `src/content/blog/`:

```markdown
---
title: "5 Smart Tips for Buying Containers"
description: "What to look for when purchasing a shipping container."
pubDate: 2025-01-15
author: "C-Can Sam"
tags: ["buying", "tips"]
image: "/images/blog/buying-tips.jpg"
---

Content here...
```

## Cookie Consent

GDPR-compliant banner with four cookie categories:

- **Essential** — Always on (site functionality)
- **Analytics** — Google Analytics (loads only after consent)
- **Marketing** — Ad tracking
- **Functional** — Personalization

Preferences stored in `localStorage`. Users can change via "Cookie Settings" in footer.

## Contact Form

The form at `/contact/` submits to `/api/contact.php`:

1. Validates required fields
2. Checks honeypot (spam prevention)
3. Rate-limits by IP (10/hour default)
4. Sends email via Resend API
5. Logs to `data/submissions.json`

### Admin Panel

View submissions at:

```
https://your-domain/api/admin.php?key=YOUR_SECRET_PATH
```

Features: pagination, CSV export, delete, stats.

### Local Development

For testing forms locally without sending email:

```bash
node api/contact-local.js  # Runs on port 3001
```

## Deployment

### First Time (Ubuntu 24)

```bash
ssh root@your-server

# Download and run
curl -O https://raw.githubusercontent.com/stooky/ccan/storage-containers/deploy.sh
DOMAIN=ccansam.com EMAIL=you@example.com sudo bash deploy.sh
```

The script:
1. Installs Node.js 20, nginx, PHP-FPM, Certbot
2. Clones the repository
3. Runs `npm ci && npm run build`
4. Configures nginx with SSL and redirects
5. Sets up firewall (UFW)

### Updates

```bash
ssh root@your-server "cd /var/www/ccan && git pull && npm run build"
```

Or push and deploy:

```bash
git push origin storage-containers
ssh root@your-server "cd /var/www/ccan && git pull && npm run build"
```

### URL Redirects

Old WordPress URLs redirect to new paths (configured in nginx via `deploy.sh`):

| Old URL | New URL |
|---------|---------|
| `/homepage/` | `/` |
| `/rentals/` | `/storage-container-rentals/` |
| `/our-containers/20ft-standard-container/` | `/containers/20ft-standard/` |
| `/terms-and-conditions/` | `/terms/` |

27 redirects total. See `deploy.sh` for the full list.

## Performance

Lighthouse scores: 100/100 across all categories.

Key optimizations:
- Static HTML (no client-side framework)
- Responsive images with `<picture>` and WebP
- Font preloading with `display=swap`
- Gzip compression (nginx)
- Cache headers: 30 days for static assets
- Critical CSS inlined by Astro

## Tech Stack

| Layer | Technology |
|-------|------------|
| Framework | Astro 5 |
| Styling | Tailwind CSS 4 |
| Language | TypeScript |
| Backend | PHP 8.x |
| Email | Resend API |
| Server | nginx on Ubuntu 24 |
| SSL | Let's Encrypt (Certbot) |

## Scripts

| Command | Description |
|---------|-------------|
| `npm run dev` | Start dev server |
| `npm run build` | Production build |
| `npm run preview` | Preview production build |
| `npm run stash` | Save current config to backup |
| `npm run restore` | Restore config from backup |

## Security

- Honeypot field blocks bots
- Rate limiting prevents abuse
- Input sanitization (PHP `htmlspecialchars`)
- Admin panel requires secret key
- `data/` directory blocked in nginx
- `config.yaml` blocked in nginx
- HTTPS enforced with HSTS

## License

Proprietary. All rights reserved.
