#!/usr/bin/env node
/**
 * Tag Reviews Script
 *
 * Uses OpenAI to analyze reviews and tag them with relevant pages.
 * Updates config.yaml with the tagged mappings.
 *
 * Usage:
 *   npm run tag-reviews
 *   node scripts/tag-reviews.js
 *
 * Requires:
 *   - OPENAI_API_KEY in .env.local
 */

import fs from 'fs';
import path from 'path';
import https from 'https';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Paths
const ROOT = path.join(__dirname, '..');
const CONFIG_PATH = path.join(ROOT, 'config.yaml');
const REVIEWS_PATH = path.join(ROOT, 'data', 'reviews.json');
const ENV_PATH = path.join(ROOT, '.env.local');

// Pages we want to tag reviews for
const TAGGABLE_PAGES = [
  { path: '/', description: 'Homepage - general positive reviews about the company' },
  { path: '/about', description: 'About page - reviews mentioning trust, family business, local service' },
  { path: '/storage-containers-for-sale', description: 'Sales page - reviews about buying/purchasing containers' },
  { path: '/storage-container-rentals', description: 'Rentals page - reviews about renting containers' },
  { path: '/storage-container-leasing', description: 'Leasing page - reviews about leasing or long-term rental' },
  { path: '/storage-container-sales-and-rentals', description: 'Sales & Rentals overview page - general container reviews for sales or rentals' },
  { path: '/on-site-storage-rentals', description: 'On-site storage - reviews about storing at their facility' },
  { path: '/containers/20ft-standard', description: '20ft container product page - reviews mentioning 20ft containers' },
  { path: '/containers/40ft-standard', description: '40ft container product page - reviews mentioning 40ft containers' },
  { path: '/containers/40ft-high-cube', description: '40ft high cube product page - reviews mentioning high cube or extra height' },
];

// Load environment variables from .env.local
function loadEnv() {
  try {
    const content = fs.readFileSync(ENV_PATH, 'utf-8');
    const lines = content.split('\n');
    for (const line of lines) {
      const match = line.match(/^([A-Z_]+)=(.+)$/);
      if (match) {
        process.env[match[1]] = match[2].trim();
      }
    }
  } catch (e) {
    // .env.local might not exist
  }
}

// Load reviews from JSON
function loadReviews() {
  const content = fs.readFileSync(REVIEWS_PATH, 'utf-8');
  const data = JSON.parse(content);
  return data.reviews || [];
}

// Call OpenAI API
function callOpenAI(prompt) {
  return new Promise((resolve, reject) => {
    const apiKey = process.env.OPENAI_API_KEY;
    if (!apiKey || apiKey === 'your-openai-api-key-here') {
      reject(new Error('OPENAI_API_KEY not set in .env.local'));
      return;
    }

    const payload = JSON.stringify({
      model: 'gpt-4o-mini',
      messages: [
        {
          role: 'system',
          content: `You are a helpful assistant that categorizes customer reviews for a shipping container company (C-Can Sam).
Your task is to determine which website pages each review is most relevant to.
Respond ONLY with a JSON object mapping review IDs to arrays of page paths.
Be selective - only include pages where the review content is clearly relevant.
Most reviews should appear on 1-3 pages maximum.`
        },
        { role: 'user', content: prompt }
      ],
      temperature: 0.3,
      max_tokens: 4000
    });

    const options = {
      hostname: 'api.openai.com',
      port: 443,
      path: '/v1/chat/completions',
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${apiKey}`,
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(payload)
      }
    };

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        if (res.statusCode >= 200 && res.statusCode < 300) {
          try {
            const response = JSON.parse(data);
            const content = response.choices[0].message.content;
            resolve(content);
          } catch (e) {
            reject(new Error(`Failed to parse OpenAI response: ${e.message}`));
          }
        } else {
          reject(new Error(`OpenAI API error: ${res.statusCode} - ${data}`));
        }
      });
    });

    req.on('error', reject);
    req.write(payload);
    req.end();
  });
}

// Update config.yaml with tagged reviews
function updateConfig(tagged) {
  let content = fs.readFileSync(CONFIG_PATH, 'utf-8');

  // Find and replace the tagged section
  const taggedStart = content.indexOf('tagged:');
  if (taggedStart === -1) {
    console.error('Could not find tagged: section in config.yaml');
    return false;
  }

  // Build the new tagged section
  const taggedLines = ['tagged:'];
  for (const [reviewId, pages] of Object.entries(tagged)) {
    if (pages.length > 0) {
      taggedLines.push(`    ${reviewId}: ${JSON.stringify(pages)}`);
    }
  }

  // Find where the tagged section ends (next section or end of file)
  const beforeTagged = content.substring(0, taggedStart);

  // Write the new config
  const newContent = beforeTagged + taggedLines.join('\n') + '\n';
  fs.writeFileSync(CONFIG_PATH, newContent, 'utf-8');

  return true;
}

// Main
async function main() {
  console.log('Tag Reviews with AI\n');

  // Load env
  loadEnv();

  // Load reviews
  console.log('Loading reviews...');
  const reviews = loadReviews();
  console.log(`Found ${reviews.length} reviews\n`);

  // Build prompt
  const pagesDescription = TAGGABLE_PAGES
    .map(p => `  - "${p.path}": ${p.description}`)
    .join('\n');

  const reviewsList = reviews
    .map(r => `  - ${r.id}: "${r.text.substring(0, 200)}${r.text.length > 200 ? '...' : ''}"`)
    .join('\n');

  const prompt = `Here are the pages on the C-Can Sam website:
${pagesDescription}

Here are the customer reviews (id: text):
${reviewsList}

For each review, determine which pages it should appear on based on the content.
Return a JSON object like: {"review_001": ["/", "/about"], "review_002": ["/"]}

Only include a page if the review is genuinely relevant to it. Be selective.`;

  // Call OpenAI
  console.log('Calling OpenAI to analyze reviews...');
  try {
    const response = await callOpenAI(prompt);

    // Parse the JSON from the response
    const jsonMatch = response.match(/\{[\s\S]*\}/);
    if (!jsonMatch) {
      throw new Error('No JSON found in OpenAI response');
    }

    const tagged = JSON.parse(jsonMatch[0]);
    console.log(`\nTagged ${Object.keys(tagged).length} reviews\n`);

    // Show summary
    const pageCounts = {};
    for (const pages of Object.values(tagged)) {
      for (const page of pages) {
        pageCounts[page] = (pageCounts[page] || 0) + 1;
      }
    }
    console.log('Reviews per page:');
    for (const [page, count] of Object.entries(pageCounts).sort((a, b) => b[1] - a[1])) {
      console.log(`  ${page}: ${count}`);
    }

    // Update config
    console.log('\nUpdating config.yaml...');
    if (updateConfig(tagged)) {
      console.log('\n Done! Review mappings saved to config.yaml');
    } else {
      console.log('\n Failed to update config.yaml');
    }

  } catch (error) {
    console.error('\n Error:', error.message);
    process.exit(1);
  }
}

main();
