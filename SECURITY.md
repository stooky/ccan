# Security Policy

## Security Model

C-Can Sam is a static Astro frontend with a PHP backend deployed on a self-hosted Ubuntu VPS behind nginx. The site serves shipping container sales and rental information. The attack surface is intentionally small: static HTML for public pages, PHP only for form handling, admin functions, and visitor tracking.

## Authentication

The admin panel is accessed via a secret URL path configured in `config.yaml` (`admin.secret_path`). There is no login system, session management, or user accounts. Access control relies entirely on the secrecy of this path.

## Input Validation

PHP form handlers (contact forms, quote requests) implement multiple layers of spam filtering:

- Honeypot fields to catch bots
- Rate limiting per IP address
- Phrase-based content filtering
- Server-side validation of all submitted fields

## Data Protection

- All data is stored in flat JSON files on disk (no database)
- No user accounts or passwords are stored
- The only PII collected is what visitors submit through contact/quote forms (name, email, phone, message)
- Analytics reports are served from an obscured path (`/d4t4/r3p0rts/`) with `noindex` directives
- No third-party analytics or tracking services

## Infrastructure

- **Web server:** nginx on Ubuntu VPS
- **SSL:** Let's Encrypt certificates via Certbot, auto-renewed
- **Security headers enforced:**
  - `Content-Security-Policy`
  - `X-Frame-Options: DENY`
  - `X-Content-Type-Options: nosniff`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Permissions-Policy` (restrictive)

## Known Limitations

- **No multi-factor authentication.** The admin panel has no MFA or even password-based auth.
- **Security through obscurity.** Admin and reporting paths rely on being hard to guess, not on access control.
- **No intrusion detection.** There is no WAF or automated alerting for suspicious activity.
- **Flat-file storage.** JSON files on disk lack the access controls and audit logging of a proper database.
- **Single-server deployment.** No redundancy or failover.

## Reporting Vulnerabilities

If you discover a security issue, please report it responsibly via email:

**ccansam22@gmail.com**

Include a description of the vulnerability, steps to reproduce, and any potential impact. You will receive a response within 7 days. Please do not publicly disclose the issue until it has been addressed.
