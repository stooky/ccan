/**
 * Home Services Vertical Configuration
 *
 * This module exports all configuration for HVAC and Plumbing verticals.
 */

// Type definitions
export * from './types';

// Vertical configs
export { hvacConfig, default as hvac } from './hvac';
export { plumbingConfig, default as plumbing } from './plumbing';

// Config utilities
import type { HomeServicesConfig, PrerequisiteSummary, FeaturesConfig } from './types';

/**
 * Get all prerequisites for a given config
 * Returns list of features that require external setup
 */
export function getPrerequisites(config: HomeServicesConfig): PrerequisiteSummary[] {
  const prerequisites: PrerequisiteSummary[] = [];

  // Check feature toggles
  (Object.entries(config.features) as [keyof FeaturesConfig, { enabled: boolean; prerequisite?: string }][])
    .forEach(([feature, toggle]) => {
      if (toggle.enabled && toggle.prerequisite) {
        prerequisites.push({
          feature: formatFeatureName(feature),
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

  // Check financing partners
  if (config.features.financing.enabled && config.financing.partners.length === 0) {
    prerequisites.push({
      feature: 'Financing',
      requirement: 'Add at least one financing partner with apply URL',
      status: 'unmet',
    });
  }

  // Check gallery projects
  if (config.features.gallery.enabled && config.gallery.projects.length < 3) {
    prerequisites.push({
      feature: 'Gallery',
      requirement: `Need ${3 - config.gallery.projects.length} more project photos`,
      status: 'unmet',
    });
  }

  // Check reviews
  if (config.features.reviews.enabled) {
    const hasGooglePlaceId = config.reviews.sources.google.enabled && config.reviews.sources.google.placeId;
    const hasManualReviews = config.reviews.manualReviews.length >= 3;

    if (!hasGooglePlaceId && !hasManualReviews) {
      prerequisites.push({
        feature: 'Reviews',
        requirement: 'Add Google Place ID for live reviews OR at least 3 manual reviews',
        status: 'unmet',
      });
    }
  }

  return prerequisites;
}

/**
 * Get list of enabled features
 */
export function getEnabledFeatures(config: HomeServicesConfig): string[] {
  return (Object.entries(config.features) as [string, { enabled: boolean }][])
    .filter(([, toggle]) => toggle.enabled)
    .map(([feature]) => formatFeatureName(feature));
}

/**
 * Get list of disabled features
 */
export function getDisabledFeatures(config: HomeServicesConfig): string[] {
  return (Object.entries(config.features) as [string, { enabled: boolean }][])
    .filter(([, toggle]) => !toggle.enabled)
    .map(([feature]) => formatFeatureName(feature));
}

/**
 * Get list of enabled pages
 */
export function getEnabledPages(config: HomeServicesConfig): { name: string; slug: string }[] {
  return (Object.entries(config.pages) as [string, { enabled: boolean; slug: string }][])
    .filter(([, page]) => page.enabled)
    .map(([name, page]) => ({
      name: formatFeatureName(name),
      slug: page.slug,
    }));
}

/**
 * Validate config completeness
 * Returns list of issues that need to be addressed
 */
export function validateConfig(config: HomeServicesConfig): string[] {
  const issues: string[] = [];

  // Required business info
  if (!config.business.name) issues.push('Business name is required');
  if (!config.business.phone) issues.push('Business phone is required');
  if (!config.business.email) issues.push('Business email is required');
  if (!config.business.address.city) issues.push('Business city is required');
  if (!config.business.address.state) issues.push('Business state is required');

  // Emergency phone
  if (config.features.emergency.enabled && !config.emergency.phone) {
    issues.push('Emergency phone number is required when emergency feature is enabled');
  }

  // Service area
  if (config.features.serviceArea.enabled && config.serviceArea.cities.length === 0) {
    issues.push('At least one service area city is required');
  }

  // Maintenance plans
  if (config.features.maintenancePlans.enabled && config.maintenancePlans.plans.length === 0) {
    issues.push('At least one maintenance plan is required when feature is enabled');
  }

  return issues;
}

/**
 * Generate config summary for display
 */
export function getConfigSummary(config: HomeServicesConfig): string {
  const enabled = getEnabledFeatures(config);
  const disabled = getDisabledFeatures(config);
  const prerequisites = getPrerequisites(config);
  const issues = validateConfig(config);

  let summary = `
=== ${config.verticalLabel.toUpperCase()} CONFIGURATION SUMMARY ===

Business: ${config.business.name}
Phone: ${config.business.phone}
Location: ${config.business.address.city}, ${config.business.address.state}

ENABLED FEATURES (${enabled.length}):
${enabled.map(f => `  ✓ ${f}`).join('\n')}

DISABLED FEATURES (${disabled.length}):
${disabled.map(f => `  ✗ ${f}`).join('\n')}
`;

  if (prerequisites.length > 0) {
    summary += `
PREREQUISITES NEEDED (${prerequisites.length}):
${prerequisites.map(p => `  ⚠ ${p.feature}: ${p.requirement}`).join('\n')}
`;
  }

  if (issues.length > 0) {
    summary += `
VALIDATION ISSUES (${issues.length}):
${issues.map(i => `  ❌ ${i}`).join('\n')}
`;
  } else {
    summary += `
✅ Configuration is valid
`;
  }

  return summary;
}

/**
 * Format camelCase feature name to Title Case
 */
function formatFeatureName(name: string): string {
  return name
    .replace(/([A-Z])/g, ' $1')
    .replace(/^./, str => str.toUpperCase())
    .trim();
}

/**
 * Merge partial config with defaults
 */
export function mergeConfig(
  base: HomeServicesConfig,
  overrides: Partial<HomeServicesConfig>
): HomeServicesConfig {
  return {
    ...base,
    ...overrides,
    business: { ...base.business, ...overrides.business },
    features: { ...base.features, ...overrides.features },
    emergency: { ...base.emergency, ...overrides.emergency },
    serviceArea: { ...base.serviceArea, ...overrides.serviceArea },
    maintenancePlans: { ...base.maintenancePlans, ...overrides.maintenancePlans },
    financing: { ...base.financing, ...overrides.financing },
    gallery: { ...base.gallery, ...overrides.gallery },
    reviews: { ...base.reviews, ...overrides.reviews },
    blog: { ...base.blog, ...overrides.blog },
    coupons: { ...base.coupons, ...overrides.coupons },
    scheduling: { ...base.scheduling, ...overrides.scheduling },
    liveChat: { ...base.liveChat, ...overrides.liveChat },
    seasonalMessaging: { ...base.seasonalMessaging, ...overrides.seasonalMessaging },
    services: { ...base.services, ...overrides.services },
    brands: { ...base.brands, ...overrides.brands },
    trustBadges: { ...base.trustBadges, ...overrides.trustBadges },
    seo: { ...base.seo, ...overrides.seo },
    pages: { ...base.pages, ...overrides.pages },
  } as HomeServicesConfig;
}
