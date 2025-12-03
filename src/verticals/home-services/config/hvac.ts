/**
 * HVAC Vertical Master Configuration
 *
 * This is the main configuration file for HVAC contractor websites.
 * Toggle features on/off and customize content for each client.
 *
 * PREREQUISITES are noted for features that depend on other features or external setup.
 */

import type { HomeServicesConfig } from './types';

export const hvacConfig: HomeServicesConfig = {
  // ============================================================================
  // VERTICAL IDENTITY
  // ============================================================================
  vertical: 'hvac',
  verticalLabel: 'HVAC',
  verticalTagline: 'Heating, Cooling & Indoor Air Quality',

  // ============================================================================
  // BUSINESS INFORMATION
  // Required: These must be filled out for the site to function
  // ============================================================================
  business: {
    name: 'ABC Heating & Cooling',
    phone: '(555) 123-4567',
    email: 'info@abcheating.com',
    address: {
      street: '123 Main Street',
      city: 'Springfield',
      state: 'IL',
      zip: '62701',
    },
    license: 'IL HVAC License #12345',
    yearEstablished: 2010,
  },

  // ============================================================================
  // FEATURE TOGGLES
  // Enable/disable major site features
  // ============================================================================
  features: {
    // Emergency Services Banner
    // Shows sticky banner with phone number for 24/7 service
    emergency: {
      enabled: true,
      // No prerequisites
    },

    // Service Area Map & Pages
    // Shows coverage map and generates city-specific pages
    serviceArea: {
      enabled: true,
      // No prerequisites
    },

    // Maintenance Plans
    // Displays maintenance plan cards and dedicated page
    maintenancePlans: {
      enabled: true,
      // No prerequisites
    },

    // Financing Options
    // Shows financing banners and dedicated financing page
    // PREREQUISITE: Must have financing partner account (Synchrony, GreenSky, etc.)
    financing: {
      enabled: true,
      prerequisite: 'Requires active financing partner account',
    },

    // Before/After Gallery
    // Photo gallery with slider comparisons
    // PREREQUISITE: Requires actual project photos from client
    gallery: {
      enabled: true,
      prerequisite: 'Requires minimum 3 project photos with before/after shots',
    },

    // Reviews Integration
    // Displays reviews from Google, Yelp, etc.
    // PREREQUISITE: Must have Google Business Profile with reviews
    reviews: {
      enabled: true,
      prerequisite: 'Requires Google Business Profile with minimum 5 reviews',
    },

    // Blog
    // Enables blog section with posts
    blog: {
      enabled: true,
      // No prerequisites
    },

    // Coupons/Specials Page
    // Dedicated page for current offers
    coupons: {
      enabled: false, // Less common for HVAC (maintenance plans preferred)
      // No prerequisites
    },

    // Online Scheduling
    // Embed scheduling widget
    // PREREQUISITE: Must have scheduling software (ServiceTitan, Housecall Pro, etc.)
    scheduling: {
      enabled: false,
      prerequisite: 'Requires scheduling software with embed capability',
    },

    // Live Chat
    // Chat widget integration
    // PREREQUISITE: Must have chat service account
    liveChat: {
      enabled: false,
      prerequisite: 'Requires chat service (Intercom, Drift, etc.)',
    },

    // Seasonal Messaging
    // Automatically adjusts headlines based on season
    seasonalMessaging: {
      enabled: true,
      // No prerequisites
    },
  },

  // ============================================================================
  // EMERGENCY CONFIGURATION
  // Only used if features.emergency.enabled = true
  // ============================================================================
  emergency: {
    headline: "No Heat? No AC? We're On Our Way!",
    subheadline: '24/7 Emergency HVAC Service',
    responseTime: '60 minutes or less',
    phone: '(555) 123-4567', // Can differ from main number

    // Banner styling
    variant: 'sticky', // 'sticky' | 'inline'
    colorScheme: 'urgent', // 'urgent' (red) | 'brand' (primary color)

    // Show on these pages (empty = all pages)
    showOnPages: [],

    // Hide on these pages
    hideOnPages: ['/maintenance-plans'], // Don't show emergency on sales pages
  },

  // ============================================================================
  // SERVICE AREA CONFIGURATION
  // Only used if features.serviceArea.enabled = true
  // ============================================================================
  serviceArea: {
    // Primary service radius
    radiusMiles: 30,

    // Cities served (used for local SEO and service area page)
    cities: [
      'Springfield',
      'Decatur',
      'Champaign',
      'Bloomington',
      'Peoria',
    ],

    // ZIP codes (optional, for more precise targeting)
    zipCodes: [
      '62701', '62702', '62703', '62704',
    ],

    // Google Maps embed URL (optional)
    // PREREQUISITE: Google Maps API key for custom map
    mapEmbedUrl: '',

    // Generate individual city pages for SEO
    generateCityPages: true,

    // City page template
    cityPageTemplate: {
      headlinePattern: 'HVAC Services in {city}, {state}',
      descriptionPattern: 'Trusted heating and cooling services for {city} residents. 24/7 emergency service, upfront pricing, satisfaction guaranteed.',
    },
  },

  // ============================================================================
  // MAINTENANCE PLANS CONFIGURATION
  // Only used if features.maintenancePlans.enabled = true
  // ============================================================================
  maintenancePlans: {
    // Program branding
    programName: 'Comfort Club',
    headline: 'Join the Comfort Club',
    description: 'Keep your HVAC system running smoothly year-round with our maintenance plans.',

    // Plan tiers
    plans: [
      {
        id: 'basic',
        name: 'Basic',
        price: '$14.99/mo',
        priceAnnual: '$149/yr',
        features: [
          '1 tune-up per year',
          '10% off repairs',
          'Priority scheduling',
          'Email reminders',
        ],
        featured: false,
      },
      {
        id: 'premium',
        name: 'Premium',
        price: '$24.99/mo',
        priceAnnual: '$249/yr',
        features: [
          '2 tune-ups per year (heating + cooling)',
          '15% off repairs',
          'Priority scheduling',
          'No overtime charges',
          'Filter replacement included',
          '24/7 emergency priority',
        ],
        featured: true, // Highlighted as best value
      },
      {
        id: 'ultimate',
        name: 'Ultimate',
        price: '$39.99/mo',
        priceAnnual: '$399/yr',
        features: [
          '2 tune-ups per year',
          '20% off repairs',
          'Priority scheduling',
          'No overtime charges',
          'Filter replacement included',
          '24/7 emergency priority',
          'Indoor air quality check',
          'Duct inspection',
        ],
        featured: false,
      },
    ],

    // CTA
    ctaText: 'Join Now',
    ctaLink: '/maintenance-plans',

    // Show on homepage
    showOnHomepage: true,
  },

  // ============================================================================
  // FINANCING CONFIGURATION
  // Only used if features.financing.enabled = true
  // PREREQUISITE: Active financing partner account
  // ============================================================================
  financing: {
    headline: 'Flexible Financing Available',
    description: "Don't let budget stop you from comfort. Get the system you need today.",

    // Financing partners
    partners: [
      {
        name: 'Synchrony',
        logo: '/images/partners/synchrony.png', // Add to public/images/partners/
        applyUrl: 'https://example.com/apply', // Partner-provided URL
      },
    ],

    // Promotional offers
    offers: [
      {
        headline: '0% APR for 18 Months',
        description: 'On qualifying systems $2,000+',
        terms: 'With approved credit. See store for details.',
      },
      {
        headline: 'Payments as low as $89/mo',
        description: 'On new system installation',
        terms: 'Based on $8,000 system with 120-month term.',
      },
    ],

    // Dedicated page
    dedicatedPage: true,
    pageSlug: '/financing',
  },

  // ============================================================================
  // GALLERY CONFIGURATION
  // Only used if features.gallery.enabled = true
  // PREREQUISITE: Client must provide project photos
  // ============================================================================
  gallery: {
    headline: 'Our Work',
    description: 'See the quality of our installations and repairs.',

    // Gallery type
    type: 'before-after', // 'before-after' | 'grid' | 'masonry'

    // Projects (to be populated with actual client photos)
    projects: [
      {
        id: 'project-1',
        title: 'New Trane XR15 Installation',
        location: 'Springfield, IL',
        beforeImage: '/images/gallery/project-1-before.jpg',
        afterImage: '/images/gallery/project-1-after.jpg',
        description: 'Replaced 20-year-old system with high-efficiency Trane unit.',
        category: 'installation',
      },
      // Add more projects...
    ],

    // Show on homepage
    showOnHomepage: true,
    homepageLimit: 3, // Number of projects to show on homepage
  },

  // ============================================================================
  // REVIEWS CONFIGURATION
  // Only used if features.reviews.enabled = true
  // PREREQUISITE: Google Business Profile with reviews
  // ============================================================================
  reviews: {
    headline: 'What Our Customers Say',

    // Review sources
    sources: {
      google: {
        enabled: true,
        placeId: '', // Google Place ID for live integration
        // PREREQUISITE: Google Places API key for live reviews
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

    // Manual reviews (fallback or supplement to live reviews)
    manualReviews: [
      {
        rating: 5,
        text: "They came out same day and fixed our AC. Fair price, great work! Highly recommend.",
        author: 'John D.',
        location: 'Springfield, IL',
        source: 'Google',
        date: '2024-07-15',
      },
      {
        rating: 5,
        text: "Best HVAC company in town. They installed our new furnace and did an excellent job. Very professional.",
        author: 'Sarah M.',
        location: 'Decatur, IL',
        source: 'Google',
        date: '2024-06-22',
      },
      {
        rating: 5,
        text: "Called at 10pm with no heat. They were here within an hour. Lifesavers!",
        author: 'Mike R.',
        location: 'Champaign, IL',
        source: 'Google',
        date: '2024-01-10',
      },
    ],

    // Display settings
    showOnHomepage: true,
    homepageLimit: 3,
    minimumRating: 4, // Only show reviews with this rating or higher
  },

  // ============================================================================
  // BLOG CONFIGURATION
  // Only used if features.blog.enabled = true
  // ============================================================================
  blog: {
    enabled: true,
    headline: 'HVAC Tips & News',
    description: 'Expert advice to keep your home comfortable year-round.',

    // Show on homepage
    showOnHomepage: true,
    homepageLimit: 3,

    // Categories
    categories: [
      'Heating',
      'Cooling',
      'Maintenance Tips',
      'Energy Savings',
      'Indoor Air Quality',
    ],
  },

  // ============================================================================
  // COUPONS CONFIGURATION
  // Only used if features.coupons.enabled = true
  // ============================================================================
  coupons: {
    headline: 'Current Specials',
    description: 'Save on your next HVAC service.',

    offers: [
      {
        id: 'tuneup-special',
        title: '$79 AC Tune-Up',
        description: 'Regular $129. Limited time offer.',
        code: 'COOL79',
        expires: '2024-08-31',
        terms: 'New customers only. Cannot be combined with other offers.',
      },
    ],

    // Dedicated page
    dedicatedPage: true,
    pageSlug: '/specials',
  },

  // ============================================================================
  // SCHEDULING CONFIGURATION
  // Only used if features.scheduling.enabled = true
  // PREREQUISITE: Scheduling software account
  // ============================================================================
  scheduling: {
    provider: 'none', // 'servicetitan' | 'housecallpro' | 'jobber' | 'calendly' | 'none'
    embedCode: '', // Provided by scheduling software

    // Fallback if no embed
    fallbackPhone: true,
    fallbackForm: true,
  },

  // ============================================================================
  // LIVE CHAT CONFIGURATION
  // Only used if features.liveChat.enabled = true
  // PREREQUISITE: Chat service account
  // ============================================================================
  liveChat: {
    provider: 'none', // 'intercom' | 'drift' | 'livechat' | 'tawk' | 'none'
    embedCode: '', // Provided by chat service
  },

  // ============================================================================
  // SEASONAL MESSAGING
  // Only used if features.seasonalMessaging.enabled = true
  // ============================================================================
  seasonalMessaging: {
    // Define date ranges and messaging for each season
    seasons: {
      summer: {
        startMonth: 5, // June (0-indexed would be 5, but using 1-indexed for clarity)
        endMonth: 8,   // August
        headline: 'Beat the Heat This Summer',
        emphasis: 'cooling',
        services: ['AC Repair', 'AC Installation', 'AC Maintenance'],
        ctaText: 'Schedule AC Service',
      },
      winter: {
        startMonth: 11, // November
        endMonth: 2,    // February
        headline: 'Stay Warm All Winter',
        emphasis: 'heating',
        services: ['Furnace Repair', 'Furnace Installation', 'Heating Maintenance'],
        ctaText: 'Schedule Heating Service',
      },
      spring: {
        startMonth: 3, // March
        endMonth: 4,   // April
        headline: 'Spring Tune-Up Time',
        emphasis: 'maintenance',
        services: ['AC Tune-Up', 'System Inspection'],
        ctaText: 'Schedule Tune-Up',
      },
      fall: {
        startMonth: 9,  // September
        endMonth: 10,   // October
        headline: 'Prepare for Winter',
        emphasis: 'maintenance',
        services: ['Furnace Tune-Up', 'System Inspection'],
        ctaText: 'Schedule Tune-Up',
      },
    },
  },

  // ============================================================================
  // SERVICES CONFIGURATION
  // Define which services are offered and their details
  // ============================================================================
  services: {
    // Service categories to display
    categories: ['Heating', 'Cooling', 'Indoor Air Quality', 'Maintenance'],

    // Featured services (shown prominently on homepage)
    featured: [
      'ac-repair',
      'furnace-repair',
      'ac-installation',
      'furnace-installation',
      'maintenance-plans',
      'duct-cleaning',
    ],

    // Emergency services (shown with emergency badge)
    emergency: [
      'ac-repair',
      'furnace-repair',
      'heat-pump-repair',
      'no-heat',
      'no-cooling',
    ],

    // Services with financing available
    financingAvailable: [
      'ac-installation',
      'furnace-installation',
      'heat-pump-installation',
      'complete-system',
    ],
  },

  // ============================================================================
  // BRANDS SERVICED
  // Display brand logos and mention in copy
  // ============================================================================
  brands: {
    headline: 'We Service All Major Brands',

    // Brands list
    list: [
      { name: 'Trane', logo: '/images/brands/trane.png', featured: true },
      { name: 'Carrier', logo: '/images/brands/carrier.png', featured: true },
      { name: 'Lennox', logo: '/images/brands/lennox.png', featured: true },
      { name: 'Rheem', logo: '/images/brands/rheem.png', featured: false },
      { name: 'Goodman', logo: '/images/brands/goodman.png', featured: false },
      { name: 'York', logo: '/images/brands/york.png', featured: false },
      { name: 'Bryant', logo: '/images/brands/bryant.png', featured: false },
      { name: 'American Standard', logo: '/images/brands/american-standard.png', featured: false },
      { name: 'Daikin', logo: '/images/brands/daikin.png', featured: false },
      { name: 'Mitsubishi', logo: '/images/brands/mitsubishi.png', featured: false },
    ],

    // Show on homepage
    showOnHomepage: true,
    homepageLimit: 6, // Show only featured + fill to this number
  },

  // ============================================================================
  // TRUST BADGES
  // Credibility indicators shown prominently
  // ============================================================================
  trustBadges: {
    showOnHomepage: true,

    badges: [
      {
        id: 'licensed',
        label: 'Licensed & Insured',
        sublabel: 'IL License #12345',
        icon: 'shield-check',
        enabled: true,
      },
      {
        id: 'bbb',
        label: 'BBB A+ Rating',
        sublabel: 'Accredited Business',
        icon: 'award',
        enabled: true,
        // PREREQUISITE: BBB accreditation
        prerequisite: 'Requires BBB accreditation',
      },
      {
        id: 'google',
        label: '5★ Google Rating',
        sublabel: '200+ Reviews',
        icon: 'star',
        enabled: true,
        // PREREQUISITE: Google Business Profile
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
        id: 'background',
        label: 'Background Checked',
        sublabel: 'Technicians',
        icon: 'user-check',
        enabled: true,
      },
      {
        id: 'upfront',
        label: 'Upfront Pricing',
        sublabel: 'No Surprises',
        icon: 'dollar-sign',
        enabled: true,
      },
    ],
  },

  // ============================================================================
  // SEO CONFIGURATION
  // ============================================================================
  seo: {
    // Title template
    titleTemplate: '{page} | {business} | {city} HVAC',

    // Default meta description
    defaultDescription: 'Professional HVAC services in {city}. 24/7 emergency service, heating & cooling repair, installation, and maintenance. Licensed, insured, satisfaction guaranteed.',

    // Schema.org type
    schemaType: 'HVACBusiness',

    // Local business schema
    localBusiness: {
      enabled: true,
      priceRange: '$$',
      areaServed: [], // Populated from serviceArea.cities
    },
  },

  // ============================================================================
  // PAGES CONFIGURATION
  // Control which pages are generated
  // ============================================================================
  pages: {
    home: { enabled: true, slug: '/' },
    about: { enabled: true, slug: '/about' },
    services: { enabled: true, slug: '/services' },
    contact: { enabled: true, slug: '/contact' },

    // Feature-dependent pages
    emergency: {
      enabled: true, // features.emergency.enabled
      slug: '/emergency',
    },
    serviceAreas: {
      enabled: true, // features.serviceArea.enabled
      slug: '/service-areas',
    },
    maintenancePlans: {
      enabled: true, // features.maintenancePlans.enabled
      slug: '/maintenance-plans',
    },
    financing: {
      enabled: true, // features.financing.enabled
      slug: '/financing',
    },
    gallery: {
      enabled: true, // features.gallery.enabled
      slug: '/gallery',
    },
    blog: {
      enabled: true, // features.blog.enabled
      slug: '/blog',
    },
    coupons: {
      enabled: false, // features.coupons.enabled
      slug: '/specials',
    },
  },
};

export default hvacConfig;
