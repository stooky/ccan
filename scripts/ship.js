#!/usr/bin/env node
/**
 * Ship Script - Commit, push, and deploy in one command
 *
 * Usage:
 *   npm run ship "commit message"     - Commit, push to both branches, deploy to both envs
 *   npm run ship:staging "message"    - Commit, push to develop, deploy to staging only
 *   npm run ship:prod "message"       - Commit, push to main, deploy to prod only
 *
 * If no message provided, will prompt or use a default
 */

import { execSync, spawnSync } from 'child_process';
import readline from 'readline';

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

// Parse arguments
const args = process.argv.slice(2);
let target = 'all'; // all, staging, or production
let message = null;

// Check for target flag
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

function deploy(envName) {
  const env = ENVIRONMENTS[envName];
  console.log(`\n${bold(green(`🚀 Deploying to ${env.name}...`))}`);

  const sshKey = '~/.ssh/id_rsa';
  const sshCmd = `ssh -i ${sshKey} ${env.user}@${env.host}`;
  const remoteCmd = `cd ${env.path} && git fetch origin && git checkout ${env.branch} && git pull origin ${env.branch} && npm run build`;

  if (run(`${sshCmd} "${remoteCmd}"`)) {
    console.log(green(`  ✓ ${env.name} deployed: ${env.url}`));
    return true;
  } else {
    console.log(red(`  ✗ ${env.name} deployment failed`));
    return false;
  }
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

  // Push to appropriate branches
  console.log(bold('\n📤 Pushing to remote...\n'));

  if (target === 'all' || target === 'production') {
    run('git push origin main');
  }

  if (target === 'all' || target === 'staging') {
    run('git push origin main:develop');
  }

  // Deploy
  const results = [];

  if (target === 'all' || target === 'staging') {
    results.push({ env: 'staging', success: deploy('staging') });
  }

  if (target === 'all' || target === 'production') {
    results.push({ env: 'production', success: deploy('production') });
  }

  // Summary
  console.log(bold('\n📋 Summary\n'));

  for (const r of results) {
    const env = ENVIRONMENTS[r.env];
    if (r.success) {
      console.log(green(`  ✓ ${env.name}: ${env.url}`));
    } else {
      console.log(red(`  ✗ ${env.name}: Failed`));
    }
  }

  console.log('');

  const allSuccess = results.every(r => r.success);
  process.exit(allSuccess ? 0 : 1);
}

main().catch(console.error);
