#!/usr/bin/env node
/**
 * Deploy script for C-Can Sam
 *
 * Usage:
 *   npm run deploy:staging    - Deploy to ccan.crkid.com
 *   npm run deploy:prod       - Deploy to ccansam.com
 *   npm run deploy            - Deploy to staging (default)
 *
 * Features:
 *   - Backs up data files before git pull
 *   - Runs data migrations if version changed
 *   - Keeps last 10 backups, cleans older ones
 */

import { execSync } from 'child_process';

const ENVIRONMENTS = {
  staging: {
    name: 'Staging',
    host: 'ccan.crkid.com',
    user: 'root',
    path: '/var/www/ccan',
    branch: 'develop',
    url: 'https://ccan.crkid.com',
  },
  production: {
    name: 'Production',
    host: 'ccansam.com',
    user: 'root',
    path: '/var/www/ccan',
    branch: 'main',
    url: 'https://ccansam.com',
  },
};

// Parse command line argument
const arg = process.argv[2] || 'staging';
const env = ENVIRONMENTS[arg];

if (!env) {
  console.error(`Unknown environment: ${arg}`);
  console.error('Usage: node scripts/deploy.js [staging|production]');
  process.exit(1);
}

console.log(`\n🚀 Deploying to ${env.name} (${env.host})...\n`);

const sshKey = '~/.ssh/id_rsa';
const sshCmd = `ssh -i ${sshKey} ${env.user}@${env.host}`;

// Data files to backup (relative to project root)
const dataFiles = [
  'submissions.json',
  'reviews.json',
  'inventory.json',
  'spam-log.json',
  'quote-requests.json',
  'rate-limits.json'
];

// Build the remote command sequence
const remoteCommands = `
cd ${env.path}

# Create backup directory with timestamp
BACKUP_DIR="data/backups/$(date +%Y-%m-%d-%H%M%S)"
mkdir -p "$BACKUP_DIR"

# Backup existing data files
echo "📦 Backing up data files to $BACKUP_DIR..."
for file in ${dataFiles.join(' ')}; do
  if [ -f "data/$file" ]; then
    cp "data/$file" "$BACKUP_DIR/"
    echo "   ✓ $file"
  fi
done

# Store current data version before pull
OLD_VERSION=$(grep -oP 'version:\\s*\\K\\d+' config.yaml 2>/dev/null || echo "0")

# Git operations
echo ""
echo "📥 Pulling latest code..."
git fetch origin
git checkout ${env.branch}
git pull origin ${env.branch}

# Get new data version after pull
NEW_VERSION=$(grep -oP 'version:\\s*\\K\\d+' config.yaml 2>/dev/null || echo "0")

# Run migrations if version changed
if [ "$OLD_VERSION" != "$NEW_VERSION" ]; then
  echo ""
  echo "🔄 Data version changed ($OLD_VERSION → $NEW_VERSION), running migrations..."
  if [ -f "scripts/migrate.js" ]; then
    node scripts/migrate.js "$OLD_VERSION" "$NEW_VERSION"
  else
    echo "   ⚠️  No migrate.js found, skipping migrations"
  fi
else
  echo ""
  echo "✓ Data version unchanged (v$NEW_VERSION)"
fi

# Clean old backups (keep last 10)
echo ""
echo "🧹 Cleaning old backups (keeping last 10)..."
cd data/backups 2>/dev/null && ls -dt */ 2>/dev/null | tail -n +11 | xargs rm -rf 2>/dev/null || true
cd ${env.path}

# Build
echo ""
echo "🔨 Building..."
npm run build
`.trim();

try {
  // Run the deploy command
  execSync(`${sshCmd} '${remoteCommands}'`, {
    stdio: 'inherit',
    shell: true
  });

  console.log(`\n✅ Deployed successfully!`);
  console.log(`   ${env.url}\n`);
} catch (error) {
  console.error(`\n❌ Deployment failed`);
  process.exit(1);
}
