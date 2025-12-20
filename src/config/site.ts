/**
 * Site Configuration
 * C-Can Sam - Martensville, SK
 * Saskatchewan's Premier Seacan Solutions Provider
 */

export const siteConfig = {
  // Basic Info
  name: "C-Can Sam",
  tagline: "Saskatchewan's Premier Seacan Solutions Provider",
  description: "Saskatoon new & used seacan shipping container rental, sales, and on and off-site storage. Perfect for construction sites, moving, farms, homes, and more!",
  url: "https://ccansam.com",

  // Contact Information
  contact: {
    email: "ccansam22@gmail.com",
    phone: "1-844-473-2226",
    phoneLocal: "1-306-281-4100",
    address: {
      street: "12 Peters Avenue",
      city: "Martensville",
      state: "SK",
      zip: "S0K 0A2",
      country: "Canada",
    },
  },

  // Social Media Links
  social: {
    twitter: "",
    linkedin: "",
    facebook: "https://www.facebook.com/ccansam",
    instagram: "https://www.instagram.com/ccansam_",
    youtube: "",
    whatsapp: "https://wa.me/13062814100",
  },

  // Business Hours
  hours: {
    monday: "9:00 AM - 5:00 PM",
    tuesday: "9:00 AM - 5:00 PM",
    wednesday: "9:00 AM - 5:00 PM",
    thursday: "9:00 AM - 5:00 PM",
    friday: "9:00 AM - 5:00 PM",
    saturday: "By Appointment",
    sunday: "Closed",
  },

  // Navigation Links
  navigation: [
    { name: "Home", href: "/" },
    {
      name: "Our Containers",
      href: "/storage-container-sales-and-rentals",
      children: [
        { name: "Buy", href: "/storage-containers-for-sale" },
        { name: "Rent", href: "/storage-container-rentals" },
        { name: "Lease", href: "/storage-container-leasing" },
      ]
    },
    { name: "On-Site Rentals", href: "/on-site-storage-rentals" },
    { name: "About", href: "/about" },
    { name: "Contact", href: "/contact" },
  ],

  // Footer Navigation (additional links)
  footerLinks: {
    company: [
      { name: "About Us", href: "/about" },
      { name: "Contact", href: "/contact" },
      { name: "Blog", href: "/blog" },
    ],
    services: [
      { name: "Container Sales", href: "/storage-container-sales-and-rentals" },
      { name: "On-Site Rentals", href: "/on-site-storage-rentals" },
      { name: "Off-Site Storage", href: "/storage-container-sales-and-rentals#offsite" },
      { name: "Service Areas", href: "/storage-container-sales-and-rentals#areas" },
    ],
    legal: [
      { name: "Privacy Policy", href: "/privacy" },
      { name: "Terms & Conditions", href: "/terms" },
    ],
  },

  // Default SEO Image (for social sharing)
  defaultOgImage: "/images/logo.png",

  // Google Analytics ID (leave empty to disable)
  googleAnalyticsId: "",

  // Copyright
  copyright: `© ${new Date().getFullYear()} C-Can Sam. All rights reserved.`,
};

export type SiteConfig = typeof siteConfig;
