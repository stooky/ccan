/**
 * Site Configuration
 * Update these values for each new SMB client
 */

export const siteConfig = {
  // Basic Info
  name: "Business Name",
  tagline: "Your compelling tagline here",
  description: "A brief description of your business for SEO purposes. Keep it under 160 characters.",
  url: "https://example.com",

  // Contact Information
  contact: {
    email: "hello@example.com",
    phone: "+1 (555) 123-4567",
    address: {
      street: "123 Main Street",
      city: "Anytown",
      state: "ST",
      zip: "12345",
      country: "USA",
    },
  },

  // Social Media Links
  social: {
    twitter: "https://twitter.com/yourbusiness",
    linkedin: "https://linkedin.com/company/yourbusiness",
    facebook: "https://facebook.com/yourbusiness",
    instagram: "https://instagram.com/yourbusiness",
    youtube: "", // Leave empty if not used
  },

  // Business Hours
  hours: {
    monday: "9:00 AM - 5:00 PM",
    tuesday: "9:00 AM - 5:00 PM",
    wednesday: "9:00 AM - 5:00 PM",
    thursday: "9:00 AM - 5:00 PM",
    friday: "9:00 AM - 5:00 PM",
    saturday: "Closed",
    sunday: "Closed",
  },

  // Navigation Links
  navigation: [
    { name: "Home", href: "/" },
    { name: "About", href: "/about" },
    { name: "Services", href: "/services" },
    { name: "Blog", href: "/blog" },
    { name: "Contact", href: "/contact" },
  ],

  // Footer Navigation (additional links)
  footerLinks: {
    company: [
      { name: "About Us", href: "/about" },
      { name: "Our Team", href: "/about#team" },
      { name: "Careers", href: "/careers" },
    ],
    services: [
      { name: "Service One", href: "/services#service-one" },
      { name: "Service Two", href: "/services#service-two" },
      { name: "Service Three", href: "/services#service-three" },
    ],
    legal: [
      { name: "Privacy Policy", href: "/privacy" },
      { name: "Terms of Service", href: "/terms" },
    ],
  },

  // Default SEO Image (for social sharing)
  defaultOgImage: "/og-image.png",

  // Google Analytics ID (leave empty to disable)
  googleAnalyticsId: "",

  // Copyright
  copyright: `© ${new Date().getFullYear()} Business Name. All rights reserved.`,
};

export type SiteConfig = typeof siteConfig;
