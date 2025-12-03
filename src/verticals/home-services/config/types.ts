/**
 * Type definitions for Home Services vertical configuration
 */

// ============================================================================
// FEATURE TOGGLE TYPES
// ============================================================================

export interface FeatureToggle {
  enabled: boolean;
  prerequisite?: string; // Description of what's needed to use this feature
}

export interface FeaturesConfig {
  emergency: FeatureToggle;
  serviceArea: FeatureToggle;
  maintenancePlans: FeatureToggle;
  financing: FeatureToggle;
  gallery: FeatureToggle;
  reviews: FeatureToggle;
  blog: FeatureToggle;
  coupons: FeatureToggle;
  scheduling: FeatureToggle;
  liveChat: FeatureToggle;
  seasonalMessaging: FeatureToggle;
}

// ============================================================================
// BUSINESS INFO TYPES
// ============================================================================

export interface BusinessAddress {
  street: string;
  city: string;
  state: string;
  zip: string;
}

export interface BusinessConfig {
  name: string;
  phone: string;
  email: string;
  address: BusinessAddress;
  license?: string;
  yearEstablished?: number;
}

// ============================================================================
// EMERGENCY CONFIG TYPES
// ============================================================================

export interface EmergencyConfig {
  headline: string;
  subheadline: string;
  responseTime?: string;
  phone: string;
  variant: 'sticky' | 'inline';
  colorScheme: 'urgent' | 'brand';
  showOnPages: string[];
  hideOnPages: string[];
}

// ============================================================================
// SERVICE AREA TYPES
// ============================================================================

export interface CityPageTemplate {
  headlinePattern: string;
  descriptionPattern: string;
}

export interface ServiceAreaConfig {
  radiusMiles: number;
  cities: string[];
  zipCodes?: string[];
  mapEmbedUrl?: string;
  generateCityPages: boolean;
  cityPageTemplate: CityPageTemplate;
}

// ============================================================================
// MAINTENANCE PLANS TYPES
// ============================================================================

export interface MaintenancePlan {
  id: string;
  name: string;
  price: string;
  priceAnnual?: string;
  features: string[];
  featured: boolean;
}

export interface MaintenancePlansConfig {
  programName: string;
  headline: string;
  description: string;
  plans: MaintenancePlan[];
  ctaText: string;
  ctaLink: string;
  showOnHomepage: boolean;
}

// ============================================================================
// FINANCING TYPES
// ============================================================================

export interface FinancingPartner {
  name: string;
  logo: string;
  applyUrl: string;
}

export interface FinancingOffer {
  headline: string;
  description: string;
  terms?: string;
}

export interface FinancingConfig {
  headline: string;
  description: string;
  partners: FinancingPartner[];
  offers: FinancingOffer[];
  dedicatedPage: boolean;
  pageSlug: string;
}

// ============================================================================
// GALLERY TYPES
// ============================================================================

export interface GalleryProject {
  id: string;
  title: string;
  location?: string;
  beforeImage: string;
  afterImage: string;
  description?: string;
  category?: string;
}

export interface GalleryConfig {
  headline: string;
  description: string;
  type: 'before-after' | 'grid' | 'masonry';
  projects: GalleryProject[];
  showOnHomepage: boolean;
  homepageLimit: number;
}

// ============================================================================
// REVIEWS TYPES
// ============================================================================

export interface ReviewSource {
  enabled: boolean;
  placeId?: string;  // Google
  businessId?: string; // Yelp
  pageId?: string; // Facebook
}

export interface ManualReview {
  rating: number;
  text: string;
  author: string;
  location?: string;
  source: string;
  date: string;
}

export interface ReviewsConfig {
  headline: string;
  sources: {
    google: ReviewSource;
    yelp: ReviewSource;
    facebook: ReviewSource;
  };
  manualReviews: ManualReview[];
  showOnHomepage: boolean;
  homepageLimit: number;
  minimumRating: number;
}

// ============================================================================
// BLOG TYPES
// ============================================================================

export interface BlogConfig {
  enabled: boolean;
  headline: string;
  description: string;
  showOnHomepage: boolean;
  homepageLimit: number;
  categories: string[];
}

// ============================================================================
// COUPONS TYPES
// ============================================================================

export interface Coupon {
  id: string;
  title: string;
  description: string;
  code?: string;
  expires?: string;
  terms?: string;
}

export interface CouponsConfig {
  headline: string;
  description: string;
  offers: Coupon[];
  dedicatedPage: boolean;
  pageSlug: string;
}

// ============================================================================
// SCHEDULING TYPES
// ============================================================================

export interface SchedulingConfig {
  provider: 'servicetitan' | 'housecallpro' | 'jobber' | 'calendly' | 'none';
  embedCode: string;
  fallbackPhone: boolean;
  fallbackForm: boolean;
}

// ============================================================================
// LIVE CHAT TYPES
// ============================================================================

export interface LiveChatConfig {
  provider: 'intercom' | 'drift' | 'livechat' | 'tawk' | 'none';
  embedCode: string;
}

// ============================================================================
// SEASONAL MESSAGING TYPES
// ============================================================================

export interface Season {
  startMonth: number;
  endMonth: number;
  headline: string;
  emphasis: 'heating' | 'cooling' | 'maintenance';
  services: string[];
  ctaText: string;
}

export interface SeasonalMessagingConfig {
  seasons: {
    summer: Season;
    winter: Season;
    spring: Season;
    fall: Season;
  };
}

// ============================================================================
// SERVICES TYPES
// ============================================================================

export interface ServicesConfig {
  categories: string[];
  featured: string[];
  emergency: string[];
  financingAvailable: string[];
}

// ============================================================================
// BRANDS TYPES
// ============================================================================

export interface Brand {
  name: string;
  logo: string;
  featured: boolean;
}

export interface BrandsConfig {
  headline: string;
  list: Brand[];
  showOnHomepage: boolean;
  homepageLimit: number;
}

// ============================================================================
// TRUST BADGES TYPES
// ============================================================================

export interface TrustBadge {
  id: string;
  label: string;
  sublabel?: string;
  icon: string;
  enabled: boolean;
  prerequisite?: string;
}

export interface TrustBadgesConfig {
  showOnHomepage: boolean;
  badges: TrustBadge[];
}

// ============================================================================
// SEO TYPES
// ============================================================================

export interface LocalBusinessSchema {
  enabled: boolean;
  priceRange: string;
  areaServed: string[];
}

export interface SEOConfig {
  titleTemplate: string;
  defaultDescription: string;
  schemaType: string;
  localBusiness: LocalBusinessSchema;
}

// ============================================================================
// PAGES TYPES
// ============================================================================

export interface PageConfig {
  enabled: boolean;
  slug: string;
}

export interface PagesConfig {
  home: PageConfig;
  about: PageConfig;
  services: PageConfig;
  contact: PageConfig;
  emergency: PageConfig;
  serviceAreas: PageConfig;
  maintenancePlans: PageConfig;
  financing: PageConfig;
  gallery: PageConfig;
  blog: PageConfig;
  coupons: PageConfig;
}

// ============================================================================
// MASTER CONFIG TYPE
// ============================================================================

export interface HomeServicesConfig {
  // Identity
  vertical: 'hvac' | 'plumbing' | 'electrical';
  verticalLabel: string;
  verticalTagline: string;

  // Business
  business: BusinessConfig;

  // Feature toggles
  features: FeaturesConfig;

  // Feature configurations
  emergency: EmergencyConfig;
  serviceArea: ServiceAreaConfig;
  maintenancePlans: MaintenancePlansConfig;
  financing: FinancingConfig;
  gallery: GalleryConfig;
  reviews: ReviewsConfig;
  blog: BlogConfig;
  coupons: CouponsConfig;
  scheduling: SchedulingConfig;
  liveChat: LiveChatConfig;
  seasonalMessaging: SeasonalMessagingConfig;

  // Content
  services: ServicesConfig;
  brands: BrandsConfig;
  trustBadges: TrustBadgesConfig;

  // Technical
  seo: SEOConfig;
  pages: PagesConfig;
}

// ============================================================================
// HELPER TYPE: Get enabled features
// ============================================================================

export type EnabledFeatures<T extends FeaturesConfig> = {
  [K in keyof T]: T[K]['enabled'] extends true ? K : never;
}[keyof T];

// ============================================================================
// PREREQUISITE SUMMARY TYPE
// ============================================================================

export interface PrerequisiteSummary {
  feature: string;
  requirement: string;
  status: 'met' | 'unmet' | 'unknown';
}

/**
 * Get all prerequisites for enabled features
 */
export function getPrerequisites(config: HomeServicesConfig): PrerequisiteSummary[] {
  const prerequisites: PrerequisiteSummary[] = [];

  // Check feature toggles
  Object.entries(config.features).forEach(([feature, toggle]) => {
    if (toggle.enabled && toggle.prerequisite) {
      prerequisites.push({
        feature,
        requirement: toggle.prerequisite,
        status: 'unknown',
      });
    }
  });

  // Check trust badges
  config.trustBadges.badges.forEach((badge) => {
    if (badge.enabled && badge.prerequisite) {
      prerequisites.push({
        feature: `Trust Badge: ${badge.label}`,
        requirement: badge.prerequisite,
        status: 'unknown',
      });
    }
  });

  return prerequisites;
}
