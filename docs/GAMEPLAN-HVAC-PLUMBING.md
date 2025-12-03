# Game Plan: HVAC & Plumbing Vertical

A complete implementation plan for the first vertical targeting HVAC and Plumbing contractors.

---

## Why HVAC & Plumbing First?

| Factor | HVAC | Plumbing |
|--------|------|----------|
| Avg. Job Value | $150 - $10,000+ | $150 - $5,000+ |
| Emergency Demand | High (no heat/AC) | High (leaks, clogs) |
| Seasonality | Strong (summer/winter) | Year-round |
| Recurring Revenue | Maintenance plans | Less common |
| Template Overlap | ~90% shared with Plumbing | ~90% shared with HVAC |

**Key Insight:** These two verticals share nearly identical website structures, making them perfect to build together as a "Home Services" base.

---

## Directory Structure

```
src/
├── verticals/
│   └── home-services/
│       ├── config/
│       │   ├── hvac.ts              # HVAC-specific config
│       │   ├── plumbing.ts          # Plumbing-specific config
│       │   └── shared.ts            # Shared home services config
│       ├── components/
│       │   ├── EmergencyBanner.astro
│       │   ├── ServiceAreaMap.astro
│       │   ├── MaintenancePlanCard.astro
│       │   ├── FinancingBanner.astro
│       │   ├── BeforeAfterGallery.astro
│       │   ├── ServiceCallCTA.astro
│       │   ├── TrustBadges.astro
│       │   └── ReviewsCarousel.astro
│       ├── content/
│       │   ├── hvac/
│       │   │   ├── services.ts      # Pre-built service descriptions
│       │   │   ├── faqs.ts          # Common HVAC FAQs
│       │   │   └── blog/            # Sample blog posts
│       │   └── plumbing/
│       │       ├── services.ts
│       │       ├── faqs.ts
│       │       └── blog/
│       └── pages/                   # Page template overrides
│           ├── index.astro          # Home (emergency-focused)
│           ├── services.astro       # Services with categories
│           ├── service-areas.astro  # Service area page
│           ├── financing.astro      # Financing options
│           ├── maintenance.astro    # Maintenance plans (HVAC)
│           ├── emergency.astro      # 24/7 emergency page
│           └── coupons.astro        # Specials/coupons page
```

---

## Vertical-Specific Components

### 1. EmergencyBanner.astro
**Purpose:** Sticky banner for 24/7 emergency service - the #1 conversion driver

```
┌─────────────────────────────────────────────────────────────────┐
│ 🚨 24/7 EMERGENCY SERVICE  •  Call Now: (555) 123-4567  [CALL] │
└─────────────────────────────────────────────────────────────────┘
```

**Props:**
- `phone: string` - Emergency phone number
- `message?: string` - Custom message (default: "24/7 Emergency Service")
- `variant?: 'sticky' | 'inline'`

---

### 2. ServiceAreaMap.astro
**Purpose:** Show coverage areas with city/zip list

```
┌──────────────────────────────────────┐
│  [Interactive Map]                   │
│                                      │
│  Cities We Serve:                    │
│  ✓ Springfield  ✓ Shelbyville       │
│  ✓ Capital City ✓ Ogdenville        │
│  [See All Service Areas →]           │
└──────────────────────────────────────┘
```

**Props:**
- `cities: string[]`
- `zipCodes?: string[]`
- `mapEmbed?: string` - Google Maps embed URL

---

### 3. MaintenancePlanCard.astro
**Purpose:** Sell recurring maintenance plans (HVAC-focused)

```
┌──────────────────────────────────────┐
│  🛡️ COMFORT CLUB                     │
│  Annual Maintenance Plan             │
│                                      │
│  ✓ 2 tune-ups per year              │
│  ✓ Priority scheduling              │
│  ✓ 15% off repairs                  │
│  ✓ No overtime charges              │
│                                      │
│  Starting at $19.99/month            │
│  [Join Now]                          │
└──────────────────────────────────────┘
```

**Props:**
- `name: string`
- `price: string`
- `benefits: string[]`
- `ctaText?: string`
- `ctaHref?: string`

---

### 4. FinancingBanner.astro
**Purpose:** Promote financing for big-ticket items (new AC, water heater)

```
┌─────────────────────────────────────────────────────────────────┐
│  💳 0% Financing Available                                      │
│  New system as low as $89/month • Easy approval • Apply today  │
│  [Check Your Rate →]                                            │
└─────────────────────────────────────────────────────────────────┘
```

**Props:**
- `headline: string`
- `description: string`
- `ctaText: string`
- `ctaHref: string`
- `partners?: string[]` - Financing partner logos

---

### 5. BeforeAfterGallery.astro
**Purpose:** Showcase work quality with slider comparisons

```
┌──────────────────────────────────────┐
│  ◄ [Before | After Slider] ►        │
│                                      │
│  "New Trane XR15 Installation"       │
│  Springfield, IL                     │
│                                      │
│  [◉ ○ ○ ○ ○]  [View All Projects]   │
└──────────────────────────────────────┘
```

**Props:**
- `projects: { before: string, after: string, title: string, location?: string }[]`

---

### 6. ServiceCallCTA.astro
**Purpose:** Primary conversion block - schedule service

```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│        Need [HVAC/Plumbing] Service?                           │
│                                                                 │
│   [━━━━━━━━━━━━━━━━━━]  [━━━━━━━━━━━━━━━━━━]                   │
│   Your Name                Your Phone                          │
│                                                                 │
│   [━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━]                   │
│   Describe your issue...                                       │
│                                                                 │
│   [🗓️ Schedule Service]        or call (555) 123-4567          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Props:**
- `headline: string`
- `phone: string`
- `formAction: string`

---

### 7. TrustBadges.astro
**Purpose:** Build credibility with licensing, certifications, guarantees

```
┌─────────────────────────────────────────────────────────────────┐
│  [Licensed]  [Insured]  [BBB A+]  [5★ Google]  [Satisfaction]  │
│   & Bonded    $2M        Rating    4.9 (200+)    Guaranteed    │
└─────────────────────────────────────────────────────────────────┘
```

**Props:**
- `badges: { icon: string, label: string, sublabel?: string }[]`

---

### 8. ReviewsCarousel.astro
**Purpose:** Social proof with customer reviews

```
┌──────────────────────────────────────┐
│  ★★★★★                              │
│  "They came out same day and fixed  │
│   our AC. Fair price, great work!"  │
│                                      │
│   — John D., Springfield            │
│   via Google Reviews                 │
│                                      │
│  [◀ ● ○ ○ ▶]   [See All Reviews]    │
└──────────────────────────────────────┘
```

**Props:**
- `reviews: { rating: number, text: string, author: string, source: string }[]`
- `googlePlaceId?: string` - For live review integration

---

## Page Templates

### Home Page Structure (HVAC/Plumbing)

```
1. EmergencyBanner (sticky)
2. Hero
   - Headline: "Your Trusted [HVAC/Plumbing] Experts in [City]"
   - Subhead: "24/7 Emergency Service • Licensed & Insured • Satisfaction Guaranteed"
   - CTA: "Schedule Service" + Phone number
3. TrustBadges
4. Services Grid (top 6 services)
5. ServiceCallCTA
6. Why Choose Us (FeatureGrid)
7. MaintenancePlanCard (HVAC) or Coupons (Plumbing)
8. BeforeAfterGallery
9. ReviewsCarousel
10. ServiceAreaMap
11. FinancingBanner
12. CTA (final conversion)
13. Footer
```

### Services Page Structure

```
1. EmergencyBanner
2. Hero (Services)
3. Service Categories
   - HVAC: Heating | Cooling | Indoor Air Quality | Maintenance
   - Plumbing: Drain & Sewer | Water Heaters | Fixtures | Emergency
4. Individual Service Cards (expandable)
5. ServiceCallCTA
6. FinancingBanner
7. FAQ Accordion
```

### Emergency Page Structure

```
1. EmergencyBanner (prominent)
2. Hero
   - "24/7 Emergency [HVAC/Plumbing] Service"
   - "We're Available NOW - Call (555) 123-4567"
   - [CALL NOW] button (large, red)
3. What to Do While You Wait (tips)
4. Emergency Services List
5. ServiceCallCTA (simplified - just phone + brief form)
6. Response Time Guarantee
7. TrustBadges
```

---

## Service Definitions

### HVAC Services

```typescript
// src/verticals/home-services/content/hvac/services.ts

export const hvacServices = {
  heating: [
    {
      name: "Furnace Repair",
      slug: "furnace-repair",
      description: "Fast, reliable furnace repair to restore your heat.",
      icon: "flame",
      emergency: true,
    },
    {
      name: "Furnace Installation",
      slug: "furnace-installation",
      description: "Expert installation of high-efficiency furnaces.",
      icon: "flame",
      financing: true,
    },
    {
      name: "Heat Pump Service",
      slug: "heat-pump-service",
      description: "Repair and maintenance for all heat pump brands.",
      icon: "arrows-exchange",
    },
    {
      name: "Boiler Service",
      slug: "boiler-service",
      description: "Boiler repair, maintenance, and replacement.",
      icon: "droplet",
    },
  ],
  cooling: [
    {
      name: "AC Repair",
      slug: "ac-repair",
      description: "Same-day AC repair to beat the heat.",
      icon: "snowflake",
      emergency: true,
    },
    {
      name: "AC Installation",
      slug: "ac-installation",
      description: "New air conditioning system installation.",
      icon: "snowflake",
      financing: true,
    },
    {
      name: "AC Maintenance",
      slug: "ac-maintenance",
      description: "Annual tune-ups to keep your AC running efficiently.",
      icon: "tool",
    },
    {
      name: "Ductless Mini-Split",
      slug: "ductless-mini-split",
      description: "Installation and repair of ductless systems.",
      icon: "layout",
    },
  ],
  airQuality: [
    {
      name: "Air Duct Cleaning",
      slug: "air-duct-cleaning",
      description: "Remove dust, allergens, and contaminants from your ducts.",
      icon: "wind",
    },
    {
      name: "Air Purification",
      slug: "air-purification",
      description: "Whole-home air purification systems.",
      icon: "shield-check",
    },
    {
      name: "Humidifier Installation",
      slug: "humidifier-installation",
      description: "Combat dry air with whole-home humidifiers.",
      icon: "droplet",
    },
  ],
  maintenance: [
    {
      name: "Maintenance Plans",
      slug: "maintenance-plans",
      description: "Keep your system running with our annual plans.",
      icon: "calendar-check",
      featured: true,
    },
  ],
};
```

### Plumbing Services

```typescript
// src/verticals/home-services/content/plumbing/services.ts

export const plumbingServices = {
  drainSewer: [
    {
      name: "Drain Cleaning",
      slug: "drain-cleaning",
      description: "Clear clogs fast with professional drain cleaning.",
      icon: "droplet",
      emergency: true,
    },
    {
      name: "Sewer Line Repair",
      slug: "sewer-line-repair",
      description: "Trenchless sewer repair and replacement.",
      icon: "git-merge",
      financing: true,
    },
    {
      name: "Camera Inspection",
      slug: "camera-inspection",
      description: "Video inspection to diagnose pipe problems.",
      icon: "video",
    },
    {
      name: "Hydro Jetting",
      slug: "hydro-jetting",
      description: "High-pressure cleaning for stubborn clogs.",
      icon: "zap",
    },
  ],
  waterHeaters: [
    {
      name: "Water Heater Repair",
      slug: "water-heater-repair",
      description: "Restore hot water fast with expert repair.",
      icon: "flame",
      emergency: true,
    },
    {
      name: "Water Heater Installation",
      slug: "water-heater-installation",
      description: "Tank and tankless water heater installation.",
      icon: "flame",
      financing: true,
    },
    {
      name: "Tankless Water Heaters",
      slug: "tankless-water-heaters",
      description: "Endless hot water with tankless systems.",
      icon: "zap",
      financing: true,
    },
  ],
  fixtures: [
    {
      name: "Faucet Repair",
      slug: "faucet-repair",
      description: "Fix leaky faucets and install new fixtures.",
      icon: "droplet",
    },
    {
      name: "Toilet Repair",
      slug: "toilet-repair",
      description: "Toilet repair, replacement, and installation.",
      icon: "home",
    },
    {
      name: "Garbage Disposal",
      slug: "garbage-disposal",
      description: "Disposal repair and replacement.",
      icon: "trash",
    },
    {
      name: "Sump Pump",
      slug: "sump-pump",
      description: "Sump pump installation and repair.",
      icon: "upload",
    },
  ],
  emergency: [
    {
      name: "Burst Pipe Repair",
      slug: "burst-pipe-repair",
      description: "24/7 emergency burst pipe repair.",
      icon: "alert-triangle",
      emergency: true,
    },
    {
      name: "Gas Line Service",
      slug: "gas-line-service",
      description: "Licensed gas line repair and installation.",
      icon: "flame",
      emergency: true,
    },
    {
      name: "Water Leak Detection",
      slug: "water-leak-detection",
      description: "Find hidden leaks before they cause damage.",
      icon: "search",
    },
  ],
};
```

---

## Site Configuration

### HVAC Config

```typescript
// src/verticals/home-services/config/hvac.ts

import { sharedHomeServicesConfig } from './shared';

export const hvacConfig = {
  ...sharedHomeServicesConfig,

  vertical: 'hvac',
  verticalLabel: 'HVAC',

  // Industry-specific
  serviceCategories: ['Heating', 'Cooling', 'Indoor Air Quality', 'Maintenance'],

  // Emergency messaging
  emergency: {
    enabled: true,
    headline: "No Heat? No AC? We're On Our Way!",
    subheadline: "24/7 Emergency HVAC Service",
    responseTime: "60 minutes or less",
  },

  // Maintenance plans (HVAC-specific)
  maintenancePlans: {
    enabled: true,
    name: "Comfort Club",
    plans: [
      {
        name: "Basic",
        price: "$14.99/mo",
        features: ["1 tune-up/year", "10% off repairs", "Priority scheduling"],
      },
      {
        name: "Premium",
        price: "$24.99/mo",
        features: ["2 tune-ups/year", "15% off repairs", "Priority scheduling", "No overtime charges"],
        featured: true,
      },
    ],
  },

  // Seasonal messaging
  seasonal: {
    summer: {
      headline: "Beat the Heat",
      emphasis: "cooling",
    },
    winter: {
      headline: "Stay Warm",
      emphasis: "heating",
    },
  },

  // Common brands serviced
  brands: [
    "Trane", "Carrier", "Lennox", "Rheem", "Goodman",
    "York", "Bryant", "American Standard", "Daikin", "Mitsubishi"
  ],
};
```

### Plumbing Config

```typescript
// src/verticals/home-services/config/plumbing.ts

import { sharedHomeServicesConfig } from './shared';

export const plumbingConfig = {
  ...sharedHomeServicesConfig,

  vertical: 'plumbing',
  verticalLabel: 'Plumbing',

  // Industry-specific
  serviceCategories: ['Drain & Sewer', 'Water Heaters', 'Fixtures & Faucets', 'Emergency'],

  // Emergency messaging
  emergency: {
    enabled: true,
    headline: "Plumbing Emergency? We're Here 24/7!",
    subheadline: "Burst pipes, major leaks, no hot water",
    responseTime: "Fast response, any time",
  },

  // Coupons (more common for plumbing)
  coupons: {
    enabled: true,
    offers: [
      {
        title: "$50 Off",
        description: "Any plumbing repair over $300",
        code: "SAVE50",
        expires: "2024-12-31",
      },
      {
        title: "Free Estimate",
        description: "On water heater installation",
        code: null,
      },
      {
        title: "$25 Off",
        description: "Drain cleaning service",
        code: "DRAIN25",
      },
    ],
  },

  // Guarantees
  guarantees: [
    "Upfront pricing - no surprises",
    "Clean, uniformed technicians",
    "We respect your home",
    "Satisfaction guaranteed",
  ],
};
```

### Shared Home Services Config

```typescript
// src/verticals/home-services/config/shared.ts

export const sharedHomeServicesConfig = {
  // Trust signals
  trustBadges: [
    { label: "Licensed & Insured", icon: "shield-check" },
    { label: "Background Checked", icon: "user-check" },
    { label: "Satisfaction Guaranteed", icon: "award" },
    { label: "Upfront Pricing", icon: "dollar-sign" },
  ],

  // Financing
  financing: {
    enabled: true,
    headline: "Flexible Financing Available",
    description: "Don't let budget stop you from comfort. 0% APR options available.",
    partners: ["Synchrony", "GreenSky", "Wells Fargo"],
  },

  // Service area
  serviceArea: {
    enabled: true,
    radius: "30 miles",
    displayMap: true,
  },

  // Reviews
  reviews: {
    enabled: true,
    platforms: ["google", "yelp", "facebook"],
    minimumRating: 4.5,
  },

  // Schema.org structured data
  schema: {
    type: "LocalBusiness",
    additionalTypes: ["HomeAndConstructionBusiness", "ProfessionalService"],
  },
};
```

---

## FAQ Content

### HVAC FAQs

```typescript
export const hvacFaqs = [
  {
    question: "How often should I replace my air filter?",
    answer: "We recommend replacing standard filters every 1-3 months, depending on usage, pets, and allergies. High-efficiency filters may last longer but should be checked monthly.",
  },
  {
    question: "How long does an HVAC system last?",
    answer: "With proper maintenance, most HVAC systems last 15-20 years. Factors like usage, climate, and maintenance frequency all affect lifespan.",
  },
  {
    question: "Why is my AC blowing warm air?",
    answer: "Common causes include low refrigerant, a dirty air filter, thermostat issues, or a failing compressor. We recommend calling for a diagnostic if the problem persists.",
  },
  {
    question: "What size AC do I need for my home?",
    answer: "Proper sizing depends on square footage, insulation, windows, and climate. An undersized or oversized unit both cause problems. We provide free in-home assessments.",
  },
  {
    question: "How much does a new HVAC system cost?",
    answer: "A complete system typically ranges from $5,000 to $15,000+, depending on size, efficiency rating, and features. We offer financing to make it affordable.",
  },
  {
    question: "What's included in an HVAC tune-up?",
    answer: "Our tune-ups include inspection of all components, cleaning coils and blower, checking refrigerant levels, testing safety controls, and calibrating the thermostat.",
  },
];
```

### Plumbing FAQs

```typescript
export const plumbingFaqs = [
  {
    question: "How do I know if I have a water leak?",
    answer: "Signs include unexplained high water bills, wet spots on walls/ceilings, musty odors, or the sound of running water when nothing is on. We offer leak detection services.",
  },
  {
    question: "How long does a water heater last?",
    answer: "Traditional tank water heaters last 8-12 years, while tankless units can last 20+ years with proper maintenance. Annual flushing extends lifespan.",
  },
  {
    question: "Why is my drain slow?",
    answer: "Slow drains are usually caused by buildup of hair, soap, grease, or other debris. If multiple drains are slow, it may indicate a main line issue.",
  },
  {
    question: "Should I repair or replace my water heater?",
    answer: "If your water heater is over 10 years old and needs a major repair, replacement is often more cost-effective. We can assess and advise.",
  },
  {
    question: "What causes low water pressure?",
    answer: "Low pressure can result from pipe corrosion, leaks, municipal issues, or a failing pressure regulator. A plumber can diagnose the exact cause.",
  },
  {
    question: "Is it safe to use chemical drain cleaners?",
    answer: "We don't recommend them. Chemical cleaners can damage pipes over time and are harmful to the environment. Professional drain cleaning is safer and more effective.",
  },
];
```

---

## Sample Blog Posts

### HVAC Blog Topics
1. "5 Signs Your AC Needs Repair Before Summer"
2. "Heat Pump vs. Furnace: Which Is Right for You?"
3. "How to Lower Your Heating Bill This Winter"
4. "The Importance of Annual HVAC Maintenance"
5. "When to Replace vs. Repair Your Air Conditioner"

### Plumbing Blog Topics
1. "7 Things You Should Never Put Down Your Drain"
2. "Tankless vs. Tank Water Heaters: Pros and Cons"
3. "How to Prevent Frozen Pipes This Winter"
4. "Signs You Need to Replace Your Sewer Line"
5. "DIY Plumbing Fixes vs. When to Call a Pro"

---

## Implementation Checklist

### Phase 1: Components
- [ ] EmergencyBanner.astro
- [ ] ServiceAreaMap.astro
- [ ] MaintenancePlanCard.astro
- [ ] FinancingBanner.astro
- [ ] BeforeAfterGallery.astro
- [ ] ServiceCallCTA.astro
- [ ] TrustBadges.astro
- [ ] ReviewsCarousel.astro

### Phase 2: Configuration
- [ ] shared.ts (home services base config)
- [ ] hvac.ts (HVAC-specific config)
- [ ] plumbing.ts (Plumbing-specific config)

### Phase 3: Content
- [ ] HVAC services definitions
- [ ] Plumbing services definitions
- [ ] HVAC FAQs
- [ ] Plumbing FAQs
- [ ] Sample blog posts (2 each)

### Phase 4: Pages
- [ ] Home page template (emergency-focused)
- [ ] Services page (with categories)
- [ ] Emergency page
- [ ] Service Areas page
- [ ] Financing page
- [ ] Maintenance Plans page (HVAC)
- [ ] Coupons page (Plumbing)

### Phase 5: Polish
- [ ] Schema.org structured data
- [ ] Seasonal messaging logic
- [ ] Mobile click-to-call optimization
- [ ] Local SEO meta tags
- [ ] Google Business Profile integration notes

---

## Success Metrics

A successful HVAC/Plumbing site should:

1. **Load fast** - Under 2s on mobile (emergency users are impatient)
2. **Phone prominent** - Visible without scrolling on all devices
3. **Trust signals** - Licensing, reviews, guarantees visible immediately
4. **Clear services** - Easy to find what you need
5. **Emergency path** - One click/tap to call for emergencies
6. **Local SEO** - City names in titles, service area pages

---

## Next Steps

1. Build the 8 vertical-specific components
2. Create config files for HVAC and Plumbing
3. Build service and FAQ content files
4. Create modified page templates
5. Test on sample HVAC and Plumbing sites
6. Document customization process
