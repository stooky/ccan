#!/usr/bin/env node
/**
 * Data Migration Runner for C-Can Sam
 *
 * Runs data migrations when the data version changes.
 * Called automatically by deploy.js when version bump is detected.
 *
 * Usage:
 *   node scripts/migrate.js <from_version> <to_version>
 *   node scripts/migrate.js 1 2
 *
 * Migration files should be in migrations/ folder:
 *   migrations/v1_to_v2.js
 *   migrations/v2_to_v3.js
 *   etc.
 *
 * Each migration file should export:
 *   - files: Array of data files this migration affects
 *   - migrate(data): Function that transforms the data
 *   - description: Human-readable description of the migration
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const ROOT = path.join(__dirname, '..');
const DATA_DIR = path.join(ROOT, 'data');
const MIGRATIONS_DIR = path.join(ROOT, 'migrations');

// Parse arguments
const fromVersion = parseInt(process.argv[2], 10);
const toVersion = parseInt(process.argv[3], 10);

if (isNaN(fromVersion) || isNaN(toVersion)) {
  console.error('Usage: node scripts/migrate.js <from_version> <to_version>');
  process.exit(1);
}

console.log(`\n🔄 Running migrations from v${fromVersion} to v${toVersion}\n`);

// Check if migrations directory exists
if (!fs.existsSync(MIGRATIONS_DIR)) {
  console.log('   No migrations/ directory found, creating it...');
  fs.mkdirSync(MIGRATIONS_DIR, { recursive: true });
  console.log('   ✓ Created migrations/ directory');
  console.log('   No migrations to run.\n');
  process.exit(0);
}

// Run migrations sequentially
async function runMigrations() {
  let currentVersion = fromVersion;
  let migrationsRun = 0;

  while (currentVersion < toVersion) {
    const nextVersion = currentVersion + 1;
    const migrationFile = path.join(MIGRATIONS_DIR, `v${currentVersion}_to_v${nextVersion}.js`);

    if (!fs.existsSync(migrationFile)) {
      console.log(`   ⚠️  No migration file for v${currentVersion} → v${nextVersion}`);
      console.log(`      Expected: migrations/v${currentVersion}_to_v${nextVersion}.js`);
      currentVersion = nextVersion;
      continue;
    }

    console.log(`   Running: v${currentVersion} → v${nextVersion}`);

    try {
      // Import the migration module
      const migration = await import(`file://${migrationFile}`);

      if (!migration.files || !migration.migrate) {
        console.error(`      ❌ Invalid migration file: missing 'files' or 'migrate' export`);
        currentVersion = nextVersion;
        continue;
      }

      console.log(`      ${migration.description || 'No description'}`);

      // Process each file in the migration
      for (const filename of migration.files) {
        const filePath = path.join(DATA_DIR, filename);

        if (!fs.existsSync(filePath)) {
          console.log(`      ⚠️  Skipping ${filename} (file not found)`);
          continue;
        }

        // Read current data
        const rawData = fs.readFileSync(filePath, 'utf-8');
        let data;
        try {
          data = JSON.parse(rawData);
        } catch (e) {
          console.error(`      ❌ Failed to parse ${filename}: ${e.message}`);
          continue;
        }

        // Run migration
        const migratedData = migration.migrate(data, filename);

        // Write migrated data
        fs.writeFileSync(filePath, JSON.stringify(migratedData, null, 2));
        console.log(`      ✓ Migrated ${filename}`);
      }

      migrationsRun++;
    } catch (error) {
      console.error(`      ❌ Migration failed: ${error.message}`);
      process.exit(1);
    }

    currentVersion = nextVersion;
  }

  if (migrationsRun === 0) {
    console.log('   No migration files found for this version range.');
  } else {
    console.log(`\n✅ Completed ${migrationsRun} migration(s)\n`);
  }
}

runMigrations().catch(error => {
  console.error(`\n❌ Migration error: ${error.message}`);
  process.exit(1);
});
