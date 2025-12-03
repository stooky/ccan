/**
 * Plumbing Vertical Master Configuration
 *
 * This is the main configuration file for Plumbing contractor websites.
 * Toggle features on/off and customize content for each client.
 *
 * PREREQUISITES are noted for features that depend on other features or external setup.
 */

import type { HomeServicesConfig } from './types';

export const plumbingConfig: HomeServicesConfig = {
  // ============================================================================
  // VERTICAL IDENTITY
  // ============================================================================
  vertical: 'plumbing',
  verticalLabel: 'Plumbing',
  verticalTagline: 'Residential & Commercial Plumbing Services',

  // ============================================================================
  // BUSINESS INFORMATION
  // Required: These must be filled out for the site to function
  // ============================================================================
  business: {
    name: 'ABC Plumbing',
    phone: '(555) 987-6543',
    email: 'info@abcplumbing.com',
    address: {
      street: '456 Oak Street',
      city: 'Springfield',
      state: 'IL',
      zip: '62701',
    },
    license: 'IL Plumbing License #67890',
    yearEstablished: 2008,
  },

  // ============================================================================
  // FEATURE TOGGLES
  // Enable/disable major site features
  // ============================================================================
  features: {
    // Emergency Services Banner
    emergency: {
      enabled: true,
    },

    // Service Area Map & Pages
    serviceArea: {
      enabled: true,
    },

    // Maintenance Plans (less common for plumbing)
    maintenancePlans: {
      enabled: false,
    },

    // Financing Options
    // PREREQUISITE: Must have financing partner account
    financing: {
      enabled: true,
      prerequisite: 'Requires active financing partner account',
    },

    // Before/After Gallery
    // PREREQUISITE: Requires actual project photos
    gallery: {
      enabled: true,
      prerequisite: 'Requires minimum 3 project photos',
    },

    // Reviews Integration
    // PREREQUISITE: Must have Google Business Profile
    reviews: {
      enabled: true,
      prerequisite: 'Requires Google Business Profile with minimum 5 reviews',
    },

    // Blog
    blog: {
      enabled: true,
    },

    // Coupons/Specials Page (more common for plumbing)
    coupons: {
      enabled: true,
    },

    // Online Scheduling
    // PREREQUISITE: Must have scheduling software
    scheduling: {
      enabled: false,
      prerequisite: 'Requires scheduling software with embed capability',
    },

    // Live Chat
    // PREREQUISITE: Must have chat service account
    liveChat: {
      enabled: false,
      prerequisite: 'Requires chat service (Intercom, Drift, etc.)',
    },

    // Seasonal Messaging (less relevant for plumbing)
    seasonalMessaging: {
      enabled: false,
    },
  },

  // ============================================================================
  // EMERGENCY CONFIGURATION
  // ============================================================================
  emergency: {
    headline: 'Plumbing Emergency? We\'re Here 24/7!',
    subheadline: 'Burst pipes, major leaks, sewer backups - we handle it all',
    responseTime: 'Fast response, any time',
    phone: '(555) 987-6543',
    variant: 'sticky',
    colorScheme: 'urgent',
    showOnPages: [],
    hideOnPages: ['/specials'],
  },

  // ============================================================================
  // SERVICE AREA CONFIGURATION
  // ============================================================================
  serviceArea: {
    radiusMiles: 25,
    cities: [
      'Springfield',
      'Decatur',
      'Champaign',
      'Bloomington',
      'Jacksonville',
    ],
    zipCodes: [],
    mapEmbedUrl: '',
    generateCityPages: true,
    cityPageTemplate: {
      headlinePattern: 'Plumbing Services in {city}, {state}',
      descriptionPattern: 'Professional plumbing services for {city} homes and businesses. 24/7 emergency service, upfront pricing, licensed plumbers.',
    },
  },

  // ============================================================================
  // MAINTENANCE PLANS CONFIGURATION
  // (Disabled by default for plumbing, but available if needed)
  // ============================================================================
  maintenancePlans: {
    programName: 'Plumbing Protection Plan',
    headline: 'Protect Your Pipes',
    description: 'Annual plumbing inspection and maintenance to prevent costly emergencies.',
    plans: [
      {
        id: 'basic',
        name: 'Basic',
        price: '$12.99/mo',
        priceAnnual: '$129/yr',
        features: [
          'Annual plumbing inspection',
          '10% off repairs',
          'Priority scheduling',
        ],
        featured: true,
      },
    ],
    ctaText: 'Learn More',
    ctaLink: '/maintenance',
    showOnHomepage: false,
  },

  // ============================================================================
  // FINANCING CONFIGURATION
  // ============================================================================
  financing: {
    headline: 'Affordable Payment Options',
    description: 'Major plumbing repairs shouldn\'t break the bank. We offer flexible financing.',
    partners: [
      {
        name: 'GreenSky',
        logo: '/images/partners/greensky.png',
        applyUrl: 'https://example.com/apply',
      },
    ],
    offers: [
      {
        headline: '0% APR for 12 Months',
        description: 'On repairs over $1,000',
        terms: 'With approved credit.',
      },
      {
        headline: 'Low Monthly Payments',
        description: 'On water heater and sewer line replacement',
        terms: 'See store for details.',
      },
    ],
    dedicatedPage: true,
    pageSlug: '/financing',
  },

  // ============================================================================
  // GALLERY CONFIGURATION
  // ============================================================================
  gallery: {
    headline: 'Our Plumbing Work',
    description: 'Quality plumbing repairs and installations.',
    type: 'before-after',
    projects: [
      {
        id: 'project-1',
        title: 'Sewer Line Replacement',
        location: 'Springfield, IL',
        beforeImage: '/images/gallery/sewer-before.jpg',
        afterImage: '/images/gallery/sewer-after.jpg',
        description: 'Trenchless sewer line replacement - minimal yard disruption.',
        category: 'sewer',
      },
    ],
    showOnHomepage: true,
    homepageLimit: 3,
  },

  // ============================================================================
  // REVIEWS CONFIGURATION
  // ============================================================================
  reviews: {
    headline: 'What Our Customers Say',
    sources: {
      google: {
        enabled: true,
        placeId: '',
      },
      yelp: {
        enabled: false,
        businessId: '',
      },
      facebook: {
        enabled: false,
        pageId: '',
      },
    },
    manualReviews: [
      {
        rating: 5,
        text: 'Called at midnight with a burst pipe. They were here in 30 minutes and had it fixed fast. True professionals!',
        author: 'Tom H.',
        location: 'Springfield, IL',
        source: 'Google',
        date: '2024-08-10',
      },
      {
        rating: 5,
        text: 'Fair prices, honest work. They could have sold me a new water heater but fixed mine instead. Rare to find that.',
        author: 'Linda S.',
        location: 'Decatur, IL',
        source: 'Google',
        date: '2024-07-22',
      },
      {
        rating: 5,
        text: 'Best plumber in town. They\'ve done our drains, water heater, and bathroom remodel. Always great work.',
        author: 'Robert K.',
        location: 'Champaign, IL',
        source: 'Google',
        date: '2024-06-15',
      },
    ],
    showOnHomepage: true,
    homepageLimit: 3,
    minimumRating: 4,
  },

  // ============================================================================
  // BLOG CONFIGURATION
  // ============================================================================
  blog: {
    enabled: true,
    headline: 'Plumbing Tips & Advice',
    description: 'Expert plumbing tips to protect your home.',
    showOnHomepage: true,
    homepageLimit: 3,
    categories: [
      'Drain Care',
      'Water Heaters',
      'DIY Tips',
      'Emergency Prep',
      'Water Quality',
    ],
  },

  // ============================================================================
  // COUPONS CONFIGURATION
  // ============================================================================
  coupons: {
    headline: 'Current Specials',
    description: 'Save on your next plumbing service.',
    offers: [
      {
        id: 'drain-special',
        title: '$25 Off Drain Cleaning',
        description: 'Any drain cleaning service',
        code: 'DRAIN25',
        expires: '2024-12-31',
        terms: 'New customers only. One per household.',
      },
      {
        id: 'water-heater',
        title: 'Free Estimate',
        description: 'On water heater installation',
        code: undefined,
        expires: undefined,
        terms: 'No obligation quote.',
      },
      {
        id: 'repair-discount',
        title: '$50 Off',
        description: 'Any repair over $300',
        code: 'SAVE50',
        expires: '2024-12-31',
        terms: 'Cannot be combined with other offers.',
      },
      {
        id: 'senior',
        title: '10% Senior Discount',
        description: 'For customers 65+',
        code: 'SENIOR10',
        expires: undefined,
        terms: 'Must show ID. Not valid on already discounted services.',
      },
    ],
    dedicatedPage: true,
    pageSlug: '/specials',
  },

  // ============================================================================
  // SCHEDULING CONFIGURATION
  // ============================================================================
  scheduling: {
    provider: 'none',
    embedCode: '',
    fallbackPhone: true,
    fallbackForm: true,
  },

  // ============================================================================
  // LIVE CHAT CONFIGURATION
  // ============================================================================
  liveChat: {
    provider: 'none',
    embedCode: '',
  },

  // ============================================================================
  // SEASONAL MESSAGING (disabled for plumbing, but structure provided)
  // ============================================================================
  seasonalMessaging: {
    seasons: {
      summer: {
        startMonth: 5,
        endMonth: 8,
        headline: 'Summer Plumbing Specials',
        emphasis: 'maintenance',
        services: ['Sewer Inspection', 'Outdoor Faucet Repair'],
        ctaText: 'Schedule Service',
      },
      winter: {
        startMonth: 11,
        endMonth: 2,
        headline: 'Prevent Frozen Pipes',
        emphasis: 'maintenance',
        services: ['Pipe Insulation', 'Water Heater Check'],
        ctaText: 'Winterize Now',
      },
      spring: {
        startMonth: 3,
        endMonth: 4,
        headline: 'Spring Plumbing Checkup',
        emphasis: 'maintenance',
        services: ['Sump Pump Check', 'Drain Cleaning'],
        ctaText: 'Schedule Checkup',
      },
      fall: {
        startMonth: 9,
        endMonth: 10,
        headline: 'Prepare for Winter',
        emphasis: 'maintenance',
        services: ['Water Heater Flush', 'Pipe Inspection'],
        ctaText: 'Get Ready',
      },
    },
  },

  // ============================================================================
  // SERVICES CONFIGURATION
  // ============================================================================
  services: {
    categories: ['Drain & Sewer', 'Water Heaters', 'Fixtures & Faucets', 'Emergency'],
    featured: [
      'drain-cleaning',
      'water-heater-repair',
      'sewer-line-repair',
      'leak-detection',
      'toilet-repair',
      'faucet-repair',
    ],
    emergency: [
      'burst-pipe-repair',
      'sewer-backup',
      'gas-line-leak',
      'water-heater-failure',
      'major-leak',
    ],
    financingAvailable: [
      'water-heater-installation',
      'sewer-line-replacement',
      'whole-house-repipe',
      'bathroom-remodel',
    ],
  },

  // ============================================================================
  // BRANDS SERVICED
  // ============================================================================
  brands: {
    headline: 'We Service All Major Brands',
    list: [
      { name: 'Rheem', logo: '/images/brands/rheem.png', featured: true },
      { name: 'AO Smith', logo: '/images/brands/ao-smith.png', featured: true },
      { name: 'Bradford White', logo: '/images/brands/bradford-white.png', featured: true },
      { name: 'Kohler', logo: '/images/brands/kohler.png', featured: false },
      { name: 'Moen', logo: '/images/brands/moen.png', featured: false },
      { name: 'Delta', logo: '/images/brands/delta.png', featured: false },
      { name: 'American Standard', logo: '/images/brands/american-standard.png', featured: false },
      { name: 'Rinnai', logo: '/images/brands/rinnai.png', featured: false },
      { name: 'Navien', logo: '/images/brands/navien.png', featured: false },
      { name: 'InSinkErator', logo: '/images/brands/insinkerator.png', featured: false },
    ],
    showOnHomepage: true,
    homepageLimit: 6,
  },

  // ============================================================================
  // TRUST BADGES
  // ============================================================================
  trustBadges: {
    showOnHomepage: true,
    badges: [
      {
        id: 'licensed',
        label: 'Licensed Plumbers',
        sublabel: 'IL License #67890',
        icon: 'shield-check',
        enabled: true,
      },
      {
        id: 'insured',
        label: 'Fully Insured',
        sublabel: '$2M Liability',
        icon: 'shield',
        enabled: true,
      },
      {
        id: 'google',
        label: '5★ Google Rating',
        sublabel: '150+ Reviews',
        icon: 'star',
        enabled: true,
        prerequisite: 'Requires Google Business Profile',
      },
      {
        id: 'satisfaction',
        label: '100% Satisfaction',
        sublabel: 'Guaranteed',
        icon: 'check-circle',
        enabled: true,
      },
      {
        id: 'upfront',
        label: 'Upfront Pricing',
        sublabel: 'No Hidden Fees',
        icon: 'dollar-sign',
        enabled: true,
      },
      {
        id: 'clean',
        label: 'Clean & Courteous',
        sublabel: 'We Respect Your Home',
        icon: 'home',
        enabled: true,
      },
    ],
  },

  // ============================================================================
  // SEO CONFIGURATION
  // ============================================================================
  seo: {
    titleTemplate: '{page} | {business} | {city} Plumber',
    defaultDescription: 'Professional plumbing services in {city}. 24/7 emergency plumber, drain cleaning, water heater repair & installation. Licensed, insured, upfront pricing.',
    schemaType: 'Plumber',
    localBusiness: {
      enabled: true,
      priceRange: '$$',
      areaServed: [],
    },
  },

  // ============================================================================
  // PAGES CONFIGURATION
  // ============================================================================
  pages: {
    home: { enabled: true, slug: '/' },
    about: { enabled: true, slug: '/about' },
    services: { enabled: true, slug: '/services' },
    contact: { enabled: true, slug: '/contact' },
    emergency: { enabled: true, slug: '/emergency' },
    serviceAreas: { enabled: true, slug: '/service-areas' },
    maintenancePlans: { enabled: false, slug: '/maintenance' },
    financing: { enabled: true, slug: '/financing' },
    gallery: { enabled: true, slug: '/gallery' },
    blog: { enabled: true, slug: '/blog' },
    coupons: { enabled: true, slug: '/specials' },
  },
};

export default plumbingConfig;
