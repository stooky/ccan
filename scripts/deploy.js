#!/usr/bin/env node
/**
 * Deploy script for C-Can Sam (multi-site capable)
 *
 * Usage:
 *   npm run deploy:staging                - Deploy ccansam.com to staging
 *   npm run deploy:prod                   - Deploy ccansam.com to production
 *   node scripts/deploy.js production     - Deploy to production
 *   node scripts/deploy.js production --site ccansam.com  - Explicit site
 *
 * Features:
 *   - Multi-site: reads sites.yaml for site registry
 *   - Backs up data files before git pull
 *   - Runs data migrations if version changed
 *   - Updates nginx config and reloads
 *   - Post-deploy health check
 *   - Keeps last 10 backups, cleans older ones
 */

import { execSync } from 'child_process';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
import fs from 'fs';
import YAML from 'yaml';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const projectRoot = join(__dirname, '..');

const SSH_KEY = join(process.env.HOME, '.ssh', 'id_rsa 1');

// Load site registry
function loadSiteRegistry() {
  const registryPath = join(projectRoot, 'sites', 'sites.yaml');
  try {
    return YAML.parse(fs.readFileSync(registryPath, 'utf-8'));
  } catch (e) {
    return null;
  }
}

// Parse CLI args
const args = process.argv.slice(2);
const envArg = args.find(a => !a.startsWith('--')) || 'staging';
const siteFlag = args.indexOf('--site');
const siteDomain = siteFlag !== -1 ? args[siteFlag + 1] : null;

// Resolve site from registry or fall back to hardcoded envs
const registry = loadSiteRegistry();
let env;

if (siteDomain && registry?.sites?.[siteDomain]) {
  const site = registry.sites[siteDomain];
  const isStaging = envArg === 'staging';
  env = {
    name: isStaging ? `${site.name} (Staging)` : site.name,
    host: isStaging ? site.staging_server : site.server,
    user: 'root',
    path: '/var/www/ccan',
    branch: site.branch || 'main',
    url: `https://${isStaging ? site.staging_server : site.server}`,
    nginxConf: isStaging ? site.staging_nginx_conf : site.nginx_conf,
    nginxDest: `/etc/nginx/sites-available/${isStaging ? site.staging_server : site.server}`,
    nginxLink: `/etc/nginx/sites-enabled/${isStaging ? site.staging_server : site.server}`,
  };
} else {
  // Fallback to hardcoded environments
  const ENVIRONMENTS = {
    staging: {
      name: 'Staging',
      host: 'ccan.crkid.com',
      user: 'root',
      path: '/var/www/ccan',
      branch: 'main',
      url: 'https://ccan.crkid.com',
      nginxConf: 'deploy/nginx-staging.conf',
      nginxDest: '/etc/nginx/sites-available/ccan.crkid.com',
      nginxLink: '/etc/nginx/sites-enabled/ccan.crkid.com',
    },
    production: {
      name: 'Production',
      host: 'ccansam.com',
      user: 'root',
      path: '/var/www/ccan',
      branch: 'main',
      url: 'https://ccansam.com',
      nginxConf: 'deploy/nginx-prod.conf',
      nginxDest: '/etc/nginx/sites-available/ccansam.com',
      nginxLink: '/etc/nginx/sites-enabled/ccansam.com',
    },
  };
  env = ENVIRONMENTS[envArg];
}

if (!env) {
  console.error(`Unknown environment: ${envArg}`);
  console.error('Usage: node scripts/deploy.js [staging|production] [--site domain]');
  process.exit(1);
}

console.log(`\n🚀 Deploying to ${env.name} (${env.host})...\n`);

const sshBase = `ssh -i "${SSH_KEY}" ${env.user}@${env.host}`;

function ssh(cmd, options = {}) {
  const fullCmd = `${sshBase} "${cmd.replace(/"/g, '\\"')}"`;
  return execSync(fullCmd, { stdio: 'inherit', shell: true, ...options });
}

function sshOutput(cmd) {
  const fullCmd = `${sshBase} "${cmd.replace(/"/g, '\\"')}"`;
  return execSync(fullCmd, { encoding: 'utf-8', shell: true }).trim();
}

function scp(localPath, remotePath) {
  const fullCmd = `scp -i "${SSH_KEY}" "${localPath}" ${env.user}@${env.host}:${remotePath}`;
  return execSync(fullCmd, { stdio: 'inherit', shell: true });
}

function healthCheck(url) {
  try {
    const result = execSync(`curl -sL -o /dev/null -w "%{http_code}" "${url}" 2>/dev/null`, { encoding: 'utf-8' }).trim();
    return result === '200';
  } catch (e) {
    return false;
  }
}

try {
  const remotePath = env.path;
  const branch = env.branch;

  // Step 1: Create backup
  console.log('📦 Backing up data files...');
  const timestamp = new Date().toISOString().replace(/[:.]/g, '-').slice(0, 19);
  const backupDir = `data/backups/${timestamp}`;
  ssh(`cd ${remotePath} && mkdir -p ${backupDir}`);

  const dataFiles = ['submissions.json', 'reviews.json', 'inventory.json', 'spam-log.json', 'quote-requests.json', 'rate-limits.json'];
  for (const file of dataFiles) {
    try {
      ssh(`cd ${remotePath} && [ -f data/${file} ] && cp data/${file} ${backupDir}/ && echo '   ✓ ${file}'`);
    } catch (e) {
      // File doesn't exist, skip
    }
  }

  // Step 2: Get old version
  let oldVersion = '0';
  try {
    oldVersion = sshOutput(`cd ${remotePath} && grep -oP 'version:\\s*\\K\\d+' config.yaml 2>/dev/null || echo 0`);
  } catch (e) {
    oldVersion = '0';
  }

  // Step 3: Git pull
  console.log('\n📥 Pulling latest code...');
  ssh(`cd ${remotePath} && git fetch origin && git checkout ${branch} && git pull origin ${branch}`);

  // Step 4: Get new version and run migrations if changed
  let newVersion = '0';
  try {
    newVersion = sshOutput(`cd ${remotePath} && grep -oP 'version:\\s*\\K\\d+' config.yaml 2>/dev/null || echo 0`);
  } catch (e) {
    newVersion = '0';
  }

  if (oldVersion !== newVersion) {
    console.log(`\n🔄 Data version changed (${oldVersion} → ${newVersion}), running migrations...`);
    try {
      ssh(`cd ${remotePath} && [ -f scripts/migrate.js ] && node scripts/migrate.js ${oldVersion} ${newVersion}`);
    } catch (e) {
      console.log('   ⚠️  Migration script not found or failed');
    }
  } else {
    console.log(`\n✓ Data version unchanged (v${newVersion})`);
  }

  // Step 5: Clean old backups
  console.log('\n🧹 Cleaning old backups (keeping last 10)...');
  try {
    ssh(`cd ${remotePath}/data/backups 2>/dev/null && ls -dt */ 2>/dev/null | tail -n +11 | xargs rm -rf 2>/dev/null; true`);
  } catch (e) {
    // No backups to clean
  }

  // Step 6: Build
  console.log('\n🔨 Building...');
  ssh(`cd ${remotePath} && npm run build`);

  // Step 7: Update nginx config
  console.log('\n🌐 Updating nginx config...');
  const localNginxPath = join(projectRoot, env.nginxConf);
  scp(localNginxPath, env.nginxDest);
  ssh(`ln -sf ${env.nginxDest} ${env.nginxLink} && nginx -t && systemctl reload nginx`);
  console.log('   ✓ nginx config updated and reloaded');

  // Step 8: Fix permissions for www-data (PHP/web server)
  console.log('\n🔐 Fixing permissions...');
  ssh(`chown -R www-data:www-data ${remotePath}/dist ${remotePath}/data ${remotePath}/public ${remotePath}/config.yaml 2>/dev/null; true`);

  // Step 9: Post-deploy health check
  console.log('\n🏥 Running health check...');
  const checkUrl = env.url;
  if (healthCheck(checkUrl)) {
    console.log(`   ✓ ${checkUrl} is live (HTTP 200)`);
  } else {
    console.log(`   ⚠️  ${checkUrl} health check failed — site may not be responding`);
  }

  console.log(`\n✅ Deployed successfully!`);
  console.log(`   ${env.url}\n`);
} catch (error) {
  console.error(`\n❌ Deployment failed`);
  process.exit(1);
}
