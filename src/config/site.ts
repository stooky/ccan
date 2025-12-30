/**
 * Site Configuration Loader
 * Reads from config.yaml - the single source of truth for all settings
 */

import fs from 'node:fs';
import path from 'node:path';
import YAML from 'yaml';

// Type definitions
interface NavItem {
  name: string;
  href: string;
  children?: NavItem[];
}

interface FooterLinks {
  services: NavItem[];
  products: NavItem[];
  company: NavItem[];
  legal: NavItem[];
}

interface Address {
  street: string;
  city: string;
  state: string;
  zip: string;
  country: string;
}

interface Contact {
  email: string;
  phone: string;
  phoneLocal: string;
  address: Address;
}

interface Social {
  facebook: string;
  instagram: string;
  twitter: string;
  linkedin: string;
  youtube: string;
  whatsapp: string;
}

interface Hours {
  monday: string;
  tuesday: string;
  wednesday: string;
  thursday: string;
  friday: string;
  saturday: string;
  sunday: string;
}

interface Analytics {
  googleAnalytics: {
    enabled: boolean;
    measurementId: string;
  };
  googleTagManager: {
    enabled: boolean;
    containerId: string;
  };
}

export interface SiteConfig {
  name: string;
  tagline: string;
  description: string;
  url: string;
  contact: Contact;
  social: Social;
  hours: Hours;
  navigation: NavItem[];
  footerLinks: FooterLinks;
  defaultOgImage: string;
  analytics: Analytics;
  copyright: string;
}

// Load and parse config.yaml
function loadConfig(): SiteConfig {
  const configPath = path.join(process.cwd(), 'config.yaml');
  const configFile = fs.readFileSync(configPath, 'utf-8');
  const config = YAML.parse(configFile);

  // Transform YAML structure to match expected interface
  return {
    // Site identity
    name: config.site.name,
    tagline: config.site.tagline,
    description: config.site.description,
    url: config.site.url,
    defaultOgImage: config.site.default_og_image,

    // Contact (transform snake_case to camelCase)
    contact: {
      email: config.contact.email,
      phone: config.contact.phone,
      phoneLocal: config.contact.phone_local,
      address: {
        street: config.contact.address.street,
        city: config.contact.address.city,
        state: config.contact.address.state,
        zip: config.contact.address.zip,
        country: config.contact.address.country,
      },
    },

    // Social media
    social: {
      facebook: config.social.facebook || '',
      instagram: config.social.instagram || '',
      twitter: config.social.twitter || '',
      linkedin: config.social.linkedin || '',
      youtube: config.social.youtube || '',
      whatsapp: config.social.whatsapp || '',
    },

    // Business hours
    hours: config.hours,

    // Navigation
    navigation: config.navigation.main,

    // Footer links
    footerLinks: {
      services: config.navigation.footer.services,
      products: config.navigation.footer.products,
      company: config.navigation.footer.company,
      legal: config.navigation.footer.legal,
    },

    // Analytics
    analytics: {
      googleAnalytics: {
        enabled: config.analytics?.google_analytics?.enabled || false,
        measurementId: config.analytics?.google_analytics?.measurement_id || '',
      },
      googleTagManager: {
        enabled: config.analytics?.google_tag_manager?.enabled || false,
        containerId: config.analytics?.google_tag_manager?.container_id || '',
      },
    },

    // Generated
    copyright: `© ${new Date().getFullYear()} ${config.site.name}. All rights reserved.`,
  };
}

// Export the loaded config
export const siteConfig = loadConfig();
