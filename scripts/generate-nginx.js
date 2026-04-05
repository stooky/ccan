#!/usr/bin/env node
/**
 * Generate nginx config from template + site config
 *
 * Usage:
 *   node scripts/generate-nginx.js ccansam.com
 *   node scripts/generate-nginx.js ccansam.com --output deploy/nginx-prod.conf
 *
 * Reads:
 *   - deploy/nginx.conf.template
 *   - sites/<domain>/config.yaml (for redirects)
 *
 * Outputs nginx config to stdout or --output file
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import YAML from 'yaml';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.join(__dirname, '..');

const domain = process.argv[2];
const outputFlag = process.argv.indexOf('--output');
const outputPath = outputFlag !== -1 ? process.argv[outputFlag + 1] : null;
const siteRoot = process.argv.indexOf('--root') !== -1
  ? process.argv[process.argv.indexOf('--root') + 1]
  : '/var/www/ccan';

if (!domain) {
  console.error('Usage: node scripts/generate-nginx.js <domain> [--output <file>] [--root <path>]');
  process.exit(1);
}

// Read template
const templatePath = path.join(projectRoot, 'deploy', 'nginx.conf.template');
let template = fs.readFileSync(templatePath, 'utf-8');

// Read site config for redirects
const configPath = path.join(projectRoot, 'sites', domain, 'config.yaml');
let redirects = '';

try {
  const config = YAML.parse(fs.readFileSync(configPath, 'utf-8'));
  if (config.redirects) {
    const lines = config.redirects.map(r => {
      const from = r.from.endsWith('/') ? r.from : r.from + '/';
      return `    location = ${from} { return ${r.status || 301} ${r.to}; }`;
    });
    redirects = '    # ============================================\n' +
                '    # URL Redirects (auto-generated from config.yaml)\n' +
                '    # ============================================\n' +
                lines.join('\n');
  }
} catch (e) {
  console.error(`Warning: Could not read redirects from ${configPath}`);
}

// Apply substitutions
let output = template
  .replace(/\{\{DOMAIN\}\}/g, domain)
  .replace(/\{\{SITE_ROOT\}\}/g, siteRoot)
  .replace(/\{\{REDIRECTS\}\}/g, redirects);

if (outputPath) {
  fs.writeFileSync(path.join(projectRoot, outputPath), output);
  console.log(`Generated: ${outputPath}`);
} else {
  process.stdout.write(output);
}
