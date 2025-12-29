# C-Can Sam

Saskatchewan's Premier Seacan Solutions Provider - Shipping container sales, rentals, and storage.

Built with **Astro 5**, **Tailwind CSS 4**, and **TypeScript**.

---

## Quick Start

### Local Development

```bash
# Install dependencies
npm install

# Start dev server
npm run dev

# (Optional) Start local form handler for testing contact form
node api/contact-local.js
```

The site runs at `http://localhost:4321`. The local form handler runs at `http://localhost:3001`.

### Production Build

```bash
npm run build
```

Output is in the `dist/` folder.

---

## Project Structure

```
├── api/                    # Backend API (PHP for production, JS for local dev)
│   ├── contact.php         # Contact form handler (production)
│   ├── admin.php           # Admin panel for viewing submissions
│   └── contact-local.js    # Local dev form handler (Node.js)
├── data/                   # Form submission logs (gitignored)
├── public/                 # Static assets
│   └── images/
├── src/
│   ├── components/         # Astro components
│   ├── config/             # Site configuration
│   ├── content/            # Blog posts (Markdown)
│   ├── layouts/            # Page layouts
│   ├── pages/              # Routes
│   └── styles/             # Global CSS
├── config.yaml             # Server-side configuration
├── deploy.sh               # Ubuntu deployment script
└── update.sh               # Site update script
```

---

## Configuration

### Site Config (`src/config/site.ts`)

Main site configuration including:
- Business info (name, contact, hours)
- Navigation structure
- Social media links
- SEO defaults

### Server Config (`config.yaml`)

Server-side configuration for the contact form and admin panel. **This file lives on the server** at `/var/www/ccan/config.yaml`.

```yaml
# Contact Form Settings
contact_form:
  recipient_email: "your-email@example.com"  # Where submissions are sent
  subject_prefix: "[C-Can Sam Contact]"

# Email Sending (Resend - free 3,000/month)
email:
  resend_api_key: "re_xxxxxxxxx"              # ⚠️ Get from resend.com
  from_email: "Site <onboarding@resend.dev>"  # Or your verified domain

# Admin Panel Settings
admin:
  secret_path: "your-secret-key-here"  # ⚠️ CHANGE THIS!
  per_page: 50

# Logging & Security
logging:
  submissions_file: "data/submissions.json"
security:
  honeypot_field: "website_url"
  rate_limit: 10
```

**⚠️ IMPORTANT:** After deployment, edit `config.yaml` on the server:

```bash
nano /var/www/ccan/config.yaml
```

1. Set `recipient_email` to your email
2. Change `secret_path` to something unique
3. Add your Resend API key (see below)

### Email Setup with Resend

[Resend](https://resend.com) provides reliable email delivery. Free tier: 3,000 emails/month.

**Quick setup (5 minutes):**

1. Sign up at https://resend.com
2. Go to **API Keys** → Create new key → Copy it
3. Add to `config.yaml`:
   ```yaml
   email:
     resend_api_key: "re_your_key_here"
     from_email: "C-Can Sam <onboarding@resend.dev>"
   ```

**For production**, verify your domain in Resend and update `from_email`:
```yaml
from_email: "C-Can Sam <noreply@ccansam.com>"
```

Without Resend configured, the form falls back to PHP `mail()` which often lands in spam.

---

## Contact Form & Admin Panel

### How It Works

- **Production:** PHP handler (`/api/contact.php`) sends email + logs to JSON
- **Local dev:** Node.js handler (`api/contact-local.js`) logs only (no email)

### Admin Panel Access

The admin panel lets you view all contact form submissions.

**URL Format:**
```
https://yourdomain.com/api/admin.php?key=YOUR_SECRET_KEY
```

**Example** (using default config):
```
https://ccan.crkid.com/api/admin.php?key=ccan-admin-2024
```

The `key` parameter must match `admin.secret_path` in `config.yaml`.

**To change the admin key:**
```bash
# On the server
nano /var/www/ccan/config.yaml
# Change: secret_path: "your-new-secret-key"
# Save and exit - no restart needed
```

### Admin Features

- View all submissions with pagination
- Export to CSV
- Delete individual submissions
- Stats (total, today, last 7 days)

### Testing Locally

1. Start the dev server: `npm run dev`
2. In another terminal: `node api/contact-local.js`
3. Submit a test form on the contact page
4. Check the console and `data/submissions.json`

---

## Deployment

### Initial Deployment (Ubuntu 24)

```bash
# On your server
curl -O https://raw.githubusercontent.com/stooky/ccan/storage-containers/deploy.sh
sudo bash deploy.sh
```

The script will:
1. Install dependencies (Node.js, nginx, PHP-FPM, certbot)
2. Clone the repository
3. Build the site
4. Configure nginx with SSL
5. Set up PHP for form handling
6. Configure firewall

### Updating the Site

```bash
cd /var/www/ccan
sudo bash update.sh
```

The update script handles:
- Git pull with conflict detection
- npm install
- Build
- Permission reset
- nginx reload

---

## Blog

Blog posts are in `src/content/blog/` as Markdown files.

### Creating a New Post

```markdown
---
title: "Your Post Title"
description: "Brief description for SEO"
pubDate: 2024-01-15
author: "C-Can Sam"
tags: ["shipping containers", "storage"]
image: "/images/blog/your-image.jpg"
imageAlt: "Description of image"
draft: false
---

Your content here...
```

### Image Optimization

For best performance, create responsive versions:
- `image-400w.webp`
- `image-800w.webp`
- `image-1200w.webp`
- `image.jpg` (fallback)

---

## Commands

| Command | Description |
|---------|-------------|
| `npm run dev` | Start development server |
| `npm run build` | Build for production |
| `npm run preview` | Preview production build |
| `node api/contact-local.js` | Run local form handler |

---

## Tech Stack

- **[Astro 5](https://astro.build)** - Static site framework
- **[Tailwind CSS 4](https://tailwindcss.com)** - Utility-first CSS
- **PHP 8.x** - Contact form handling (production)
- **nginx** - Web server
- **Let's Encrypt** - SSL certificates

---

## Security

- Honeypot field for spam prevention
- Rate limiting (configurable)
- Input sanitization
- CSRF protection via same-origin policy
- Admin panel behind secret URL
- Data directory blocked from web access
- Config file blocked from web access

---

## License

Proprietary - C-Can Sam
