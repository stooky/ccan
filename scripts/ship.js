#!/usr/bin/env node
/**
 * Ship Script - Commit, push, and deploy in one command
 *
 * Usage:
 *   npm run ship "commit message"     - Commit, push, deploy to both envs
 *   npm run ship:staging "message"    - Commit, push to main, deploy to staging only
 *   npm run ship:prod "message"       - Commit, push to main, deploy to prod only
 *
 * This is a convenience wrapper around deploy.js.
 * All deploy logic lives in deploy.js — ship.js handles git + calls deploy.
 */

import { execSync } from 'child_process';
import readline from 'readline';

// Parse arguments
const args = process.argv.slice(2);
let target = 'all'; // all, staging, or production
let message = null;

if (args[0] === '--staging' || args[0] === '-s') {
  target = 'staging';
  message = args.slice(1).join(' ');
} else if (args[0] === '--prod' || args[0] === '-p') {
  target = 'production';
  message = args.slice(1).join(' ');
} else {
  message = args.join(' ');
}

// Colors
const green = (s) => `\x1b[32m${s}\x1b[0m`;
const yellow = (s) => `\x1b[33m${s}\x1b[0m`;
const red = (s) => `\x1b[31m${s}\x1b[0m`;
const cyan = (s) => `\x1b[36m${s}\x1b[0m`;
const bold = (s) => `\x1b[1m${s}\x1b[0m`;

function run(cmd, opts = {}) {
  console.log(cyan(`  $ ${cmd}`));
  try {
    execSync(cmd, { stdio: 'inherit', ...opts });
    return true;
  } catch (e) {
    return false;
  }
}

function runQuiet(cmd) {
  try {
    return execSync(cmd, { encoding: 'utf8' }).trim();
  } catch (e) {
    return '';
  }
}

async function prompt(question) {
  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout,
  });
  return new Promise((resolve) => {
    rl.question(question, (answer) => {
      rl.close();
      resolve(answer);
    });
  });
}

async function main() {
  console.log(bold('\n🚢 Ship - Commit, Push & Deploy\n'));

  // Check for changes
  const status = runQuiet('git status --porcelain');
  if (!status) {
    console.log(yellow('No changes to commit. Deploying existing commits...\n'));
  } else {
    // Show what will be committed
    console.log('Changes to commit:');
    console.log(status.split('\n').map(l => `  ${l}`).join('\n'));
    console.log('');

    // Get commit message
    if (!message) {
      message = await prompt('Commit message: ');
      if (!message) {
        message = 'Update site';
      }
    }

    // Add signature
    const fullMessage = `${message}

🤖 Generated with [Claude Code](https://claude.com/claude-code)

Co-Authored-By: Claude <noreply@anthropic.com>`;

    // Commit
    console.log(bold('\n📝 Committing changes...\n'));
    run('git add -A');

    // Use a temp file for commit message to handle special chars
    const fs = await import('fs');
    fs.writeFileSync('.commit-msg-tmp', fullMessage);
    run('git commit -F .commit-msg-tmp');
    fs.unlinkSync('.commit-msg-tmp');
  }

  // Push
  console.log(bold('\n📤 Pushing to remote...\n'));
  run('git push origin main');

  // Deploy using deploy.js
  const results = [];

  if (target === 'all' || target === 'staging') {
    console.log(bold(`\n🚀 Deploying to Staging...\n`));
    const success = run('node scripts/deploy.js staging');
    results.push({ env: 'staging', success });
  }

  if (target === 'all' || target === 'production') {
    console.log(bold(`\n🚀 Deploying to Production...\n`));
    const success = run('node scripts/deploy.js production');
    results.push({ env: 'production', success });
  }

  // Summary
  console.log(bold('\n📋 Summary\n'));

  const envUrls = { staging: 'https://ccan.crkid.com', production: 'https://ccansam.com' };
  for (const r of results) {
    if (r.success) {
      console.log(green(`  ✓ ${r.env}: ${envUrls[r.env]}`));
    } else {
      console.log(red(`  ✗ ${r.env}: Failed`));
    }
  }

  console.log('');

  const allSuccess = results.every(r => r.success);
  process.exit(allSuccess ? 0 : 1);
}

main().catch(console.error);
