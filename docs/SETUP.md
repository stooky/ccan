# SMB Website Boilerplate - Setup Documentation

A modern, production-ready boilerplate for creating websites for Small and Medium Businesses (SMBs). Built with Astro, Tailwind CSS, and TypeScript.

---

## Technology Stack

| Technology | Version | Purpose |
|------------|---------|---------|
| Astro | 5.x | Static site framework |
| Tailwind CSS | 4.x | Utility-first styling |
| TypeScript | 5.x | Type safety |
| Vite | (via Astro) | Build tooling |

### Why This Stack?

- **Astro**: Excellent static site generation, content collections for blogs, minimal JavaScript by default, fast builds
- **Tailwind CSS 4**: Modern styling with Vite plugin integration, design system out of the box
- **TypeScript**: Type-safe development, better IDE support, fewer runtime errors

---

## Project Structure

```
smb-boilerplate/
├── src/
│   ├── components/          # Reusable UI components
│   │   ├── Navigation.astro
│   │   ├── Footer.astro
│   │   ├── Hero.astro
│   │   ├── CTA.astro
│   │   └── Card.astro
│   ├── content/             # Content collections
│   │   └── blog/
│   │       └── *.md
│   ├── layouts/             # Page layouts
│   │   └── Layout.astro
│   ├── pages/               # Route pages
│   │   ├── index.astro      # Home page
│   │   ├── about.astro      # About page
│   │   ├── contact.astro    # Contact page
│   │   ├── services.astro   # Services page
│   │   └── blog/
│   │       ├── index.astro
│   │       └── [...slug].astro
│   ├── styles/
│   │   └── global.css       # Global styles
│   └── content.config.ts    # Content collection schema
├── public/                  # Static assets
│   ├── favicon.svg
│   └── images/
├── docs/                    # Documentation
│   └── SETUP.md
├── astro.config.mjs
├── tailwind.config.mjs
├── tsconfig.json
└── package.json
```

---

## Core Pages for SMB Websites

### Standard Pages
1. **Home (/)** - Hero, value proposition, services overview, testimonials, CTA
2. **About (/about)** - Company/founder story, team, mission/values
3. **Services (/services)** - Service offerings with descriptions and pricing
4. **Contact (/contact)** - Contact form, location, business hours
5. **Blog (/blog)** - Content marketing, SEO, thought leadership

### Optional Pages
- **Portfolio/Work** - Case studies, project gallery
- **FAQ** - Frequently asked questions
- **Pricing** - Detailed pricing tiers
- **Booking** - Appointment scheduling (Calendly, Cal.com integration)
- **Testimonials** - Customer reviews and social proof

---

## Setup Instructions

### Prerequisites
- Node.js 18+
- npm or pnpm

### Installation

```bash
# Clone the boilerplate
git clone <repository-url> my-smb-site
cd my-smb-site

# Install dependencies
npm install

# Start development server
npm run dev
```

The dev server runs at `http://localhost:4321`

### Available Scripts

```bash
npm run dev      # Start development server
npm run build    # Build for production
npm run preview  # Preview production build
npm run astro    # Run Astro CLI commands
```

---

## Configuration

### Site Configuration

Create a `src/config/site.ts` file for site-wide settings:

```typescript
export const siteConfig = {
  name: "Business Name",
  description: "Your business description for SEO",
  url: "https://yourdomain.com",

  // Contact Info
  email: "hello@yourdomain.com",
  phone: "+1 (555) 123-4567",
  address: "123 Main St, City, State 12345",

  // Social Links
  social: {
    twitter: "https://twitter.com/yourbusiness",
    linkedin: "https://linkedin.com/company/yourbusiness",
    facebook: "https://facebook.com/yourbusiness",
    instagram: "https://instagram.com/yourbusiness",
  },

  // Business Hours
  hours: {
    weekdays: "9:00 AM - 5:00 PM",
    saturday: "10:00 AM - 2:00 PM",
    sunday: "Closed",
  },

  // Navigation
  navigation: [
    { name: "Home", href: "/" },
    { name: "About", href: "/about" },
    { name: "Services", href: "/services" },
    { name: "Blog", href: "/blog" },
    { name: "Contact", href: "/contact" },
  ],
};
```

### Tailwind Configuration

The boilerplate uses Tailwind CSS 4 with the Vite plugin. Customize your design tokens in `tailwind.config.mjs`:

```javascript
export default {
  content: ['./src/**/*.{astro,html,js,jsx,md,mdx,svelte,ts,tsx,vue}'],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f0f9ff',
          // ... define your brand colors
          900: '#0c4a6e',
        },
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        heading: ['Poppins', 'sans-serif'],
      },
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
    require('@tailwindcss/forms'),
  ],
};
```

---

## Content Collections

### Blog Posts

Blog posts use Astro's content collections with frontmatter validation.

**Schema** (`src/content.config.ts`):
```typescript
import { defineCollection, z } from 'astro:content';

const blog = defineCollection({
  type: 'content',
  schema: z.object({
    title: z.string(),
    description: z.string(),
    pubDate: z.coerce.date(),
    updatedDate: z.coerce.date().optional(),
    author: z.string().default('Team'),
    image: z.string().optional(),
    tags: z.array(z.string()).default([]),
    draft: z.boolean().default(false),
  }),
});

export const collections = { blog };
```

**Creating a blog post** (`src/content/blog/my-post.md`):
```markdown
---
title: "Your Post Title"
description: "A brief description for SEO and previews"
pubDate: 2024-12-01
author: "Author Name"
image: "/images/blog/post-image.jpg"
tags: ["business", "tips"]
---

Your content here...
```

---

## SEO Configuration

### Head Meta Tags

Add to your Layout component:

```astro
---
interface Props {
  title: string;
  description?: string;
  image?: string;
}

const {
  title,
  description = siteConfig.description,
  image = '/og-image.png'
} = Astro.props;
---

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width" />
  <meta name="description" content={description} />

  <!-- Open Graph -->
  <meta property="og:title" content={title} />
  <meta property="og:description" content={description} />
  <meta property="og:image" content={image} />
  <meta property="og:type" content="website" />

  <!-- Twitter -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:title" content={title} />
  <meta name="twitter:description" content={description} />

  <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
  <title>{title} | {siteConfig.name}</title>
</head>
```

### Sitemap

Install the Astro sitemap integration:

```bash
npx astro add sitemap
```

---

## Deployment

### Recommended Platforms

| Platform | Pros | Best For |
|----------|------|----------|
| **Vercel** | Easy setup, preview deployments, edge functions | Most projects |
| **Netlify** | Similar to Vercel, good forms support | Projects needing forms |
| **Cloudflare Pages** | Fast global CDN, generous free tier | High-traffic sites |

### Vercel Deployment

```bash
# Install Vercel CLI
npm i -g vercel

# Deploy
vercel
```

Or connect your GitHub repo in the Vercel dashboard for automatic deployments.

### Build Output

```bash
npm run build
```

Output goes to `dist/` directory - can be deployed to any static host.

---

## Common Integrations

### Contact Forms
- **Formspree** - Simple, no backend needed
- **Netlify Forms** - If hosting on Netlify
- **Resend** - For custom email handling

### Booking/Scheduling
- **Calendly** - Embed scheduling widget
- **Cal.com** - Open source alternative

### Analytics
- **Plausible** - Privacy-focused, lightweight
- **Fathom** - Similar to Plausible
- **Google Analytics** - Most comprehensive (with privacy considerations)

### CMS (Optional)
- **Decap CMS** - Git-based, free
- **Sanity** - Flexible, good free tier
- **Contentful** - Enterprise-grade

---

## Customization Checklist

When adapting this boilerplate for a new SMB client:

### Brand & Design
- [ ] Update color palette in Tailwind config
- [ ] Replace logo in Navigation component
- [ ] Add custom fonts
- [ ] Replace placeholder images
- [ ] Create/customize favicon

### Content
- [ ] Update site configuration (`src/config/site.ts`)
- [ ] Write home page copy
- [ ] Create about page content
- [ ] Add services/offerings
- [ ] Write initial blog posts

### Technical
- [ ] Set up contact form
- [ ] Configure analytics
- [ ] Add SEO meta tags
- [ ] Generate sitemap
- [ ] Set up deployment
- [ ] Configure domain/DNS

### Legal (Important!)
- [ ] Privacy Policy page
- [ ] Terms of Service page
- [ ] Cookie consent banner (if using cookies)

---

## Development Workflow

### Recommended Process

1. **Clone & Configure** - Set up site config with client info
2. **Design Customization** - Colors, fonts, brand elements
3. **Content Creation** - Work with client on copy
4. **Development** - Build custom features as needed
5. **Testing** - Mobile responsiveness, forms, links
6. **Deployment** - Set up hosting and domain
7. **Handoff** - Documentation for client updates

### File Naming Conventions

- Components: `PascalCase.astro` (e.g., `HeroSection.astro`)
- Pages: `kebab-case.astro` (e.g., `about-us.astro`)
- Styles: `kebab-case.css`
- Config: `camelCase.ts`

---

## Performance Considerations

Astro is fast by default, but keep these in mind:

- **Images**: Use `<Image />` component from `astro:assets` for optimization
- **Fonts**: Self-host or use `font-display: swap`
- **Third-party scripts**: Load async/defer, consider partytown
- **CSS**: Tailwind purges unused styles automatically

---

## Support & Resources

- [Astro Documentation](https://docs.astro.build)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Astro Discord Community](https://astro.build/chat)

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2024-12-01 | Initial boilerplate based on ex10x architecture |
