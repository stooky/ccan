# SMB Website Boilerplate

A modern, production-ready boilerplate for creating websites for Small and Medium Businesses (SMBs). Built with Astro, Tailwind CSS 4, and TypeScript.

## Features

- **Modern Stack**: Astro 5, Tailwind CSS 4, TypeScript
- **SEO Ready**: Meta tags, Open Graph, Twitter cards, sitemap
- **Responsive Design**: Mobile-first approach
- **Blog System**: Markdown-based with content collections
- **Component Library**: Hero, CTA, Cards, Forms, and more
- **Performance**: Static site generation, minimal JavaScript
- **Accessible**: Semantic HTML, focus states, skip links

## Quick Start

```bash
# Install dependencies
npm install

# Start development server
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview
```

The dev server runs at `http://localhost:4321`

## Project Structure

```
src/
├── components/          # Reusable UI components
│   ├── BlogCard.astro
│   ├── Card.astro
│   ├── ContactForm.astro
│   ├── CTA.astro
│   ├── FeatureGrid.astro
│   ├── Footer.astro
│   ├── Hero.astro
│   ├── Navigation.astro
│   ├── Section.astro
│   └── Testimonial.astro
├── config/
│   └── site.ts          # Site-wide configuration
├── content/
│   └── blog/            # Blog posts (markdown)
├── layouts/
│   └── Layout.astro     # Main layout with SEO
├── pages/
│   ├── index.astro      # Home page
│   ├── about.astro      # About page
│   ├── services.astro   # Services page
│   ├── contact.astro    # Contact page
│   └── blog/
│       ├── index.astro  # Blog listing
│       └── [...slug].astro
├── styles/
│   └── global.css       # Global styles & CSS variables
└── content.config.ts    # Content collections schema
```

## Configuration

### 1. Update Site Config

Edit `src/config/site.ts` with your client's information:

```typescript
export const siteConfig = {
  name: "Your Business Name",
  description: "Your business description",
  contact: {
    email: "hello@example.com",
    phone: "+1 (555) 123-4567",
    // ...
  },
  // ...
};
```

### 2. Customize Brand Colors

Edit `src/styles/global.css` to change the primary color palette:

```css
@theme {
  --color-primary-500: #3b82f6;  /* Your brand color */
  --color-primary-600: #2563eb;
  /* ... */
}
```

### 3. Update Content

- Replace placeholder text in pages
- Add real images to `public/images/`
- Create blog posts in `src/content/blog/`
- Replace `public/favicon.svg` with client's favicon
- Add proper `public/og-image.png` (1200x630)

## Components

### Hero
```astro
<Hero
  title="Your Headline"
  subtitle="Your subheadline"
  primaryCta={{ text: "Get Started", href: "/contact" }}
  secondaryCta={{ text: "Learn More", href: "/about" }}
/>
```

### Section
```astro
<Section
  title="Section Title"
  subtitle="Section description"
  background="gray"
>
  <!-- Content -->
</Section>
```

### CTA
```astro
<CTA
  title="Ready to Start?"
  description="Contact us today"
  primaryButton={{ text: "Contact", href: "/contact" }}
  variant="primary"
/>
```

### Card
```astro
<Card
  title="Card Title"
  description="Card description"
  href="/link"
  variant="bordered"
/>
```

## Blog Posts

Create markdown files in `src/content/blog/`:

```markdown
---
title: "Post Title"
description: "Post description"
pubDate: 2024-12-01
author: "Author Name"
tags: ["Tag1", "Tag2"]
---

Your content here...
```

## Deployment

### Vercel (Recommended)

```bash
npm i -g vercel
vercel
```

### Netlify

```bash
npm i -g netlify-cli
netlify deploy
```

### Static Hosting

```bash
npm run build
# Upload dist/ to your host
```

## Customization Checklist

- [ ] Update `src/config/site.ts`
- [ ] Change brand colors in `global.css`
- [ ] Replace favicon and OG image
- [ ] Update page content
- [ ] Add real images
- [ ] Configure contact form action
- [ ] Set up analytics (optional)
- [ ] Add Privacy Policy / Terms pages

## Documentation

See `docs/SETUP.md` for comprehensive setup and customization guide.

## License

MIT
