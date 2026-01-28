// @ts-check
import { defineConfig } from 'astro/config';
import tailwindcss from '@tailwindcss/vite';
import sitemap from '@astrojs/sitemap';

// https://astro.build/config
export default defineConfig({
  site: 'https://ccansam.com',
  integrations: [
    sitemap({
      // Custom priority for important pages
      serialize(item) {
        // Homepage - highest priority
        if (item.url === 'https://ccansam.com/') {
          item.priority = 1.0;
          item.changefreq = 'weekly';
        }
        // Main service pages
        else if (
          item.url.includes('/seacan-storage-containers-for-sale') ||
          item.url.includes('/storage-container-rentals') ||
          item.url.includes('/on-site-storage-rentals') ||
          item.url.includes('/shipping-containers')
        ) {
          item.priority = 0.9;
          item.changefreq = 'weekly';
        }
        // City landing pages
        else if (
          item.url.includes('/containers-saskatoon') ||
          item.url.includes('/containers-regina') ||
          item.url.includes('/containers-prince-albert') ||
          item.url.includes('/containers-moose-jaw') ||
          item.url.includes('/containers-swift-current')
        ) {
          item.priority = 0.85;
          item.changefreq = 'weekly';
        }
        // Individual container pages
        else if (item.url.includes('/containers/')) {
          item.priority = 0.8;
          item.changefreq = 'monthly';
        }
        // About and contact
        else if (
          item.url.includes('/about') ||
          item.url.includes('/contact')
        ) {
          item.priority = 0.7;
          item.changefreq = 'monthly';
        }
        // Blog posts
        else if (item.url.includes('/blog/')) {
          item.priority = 0.6;
          item.changefreq = 'monthly';
        }
        // Utility pages (terms, privacy)
        else if (
          item.url.includes('/terms') ||
          item.url.includes('/privacy')
        ) {
          item.priority = 0.3;
          item.changefreq = 'yearly';
        }
        // Default priority
        else {
          item.priority = 0.5;
          item.changefreq = 'monthly';
        }
        return item;
      },
    }),
  ],
  build: {
    // Inline CSS to eliminate render-blocking stylesheet requests
    inlineStylesheets: 'always',
  },
  vite: {
    plugins: [tailwindcss()],
  },
  image: {
    // Use sharp for image optimization
    service: {
      entrypoint: 'astro/assets/services/sharp',
      config: {
        limitInputPixels: false,
      },
    },
    // Default formats for optimization
    domains: [],
  },
});
