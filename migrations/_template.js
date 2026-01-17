/**
 * Migration Template
 *
 * Copy this file and rename to: v{FROM}_to_v{TO}.js
 * Example: v1_to_v2.js
 *
 * This file will be automatically run by deploy.js when the data version
 * in config.yaml is bumped from {FROM} to {TO}.
 */

// Which data files this migration affects
export const files = [
  'submissions.json',
  // 'reviews.json',
  // 'spam-log.json',
  // etc.
];

// Human-readable description of what this migration does
export const description = 'Example: Add a new field to all entries';

/**
 * Migration function
 *
 * @param {any} data - The parsed JSON data from the file
 * @param {string} filename - The name of the file being migrated
 * @returns {any} - The transformed data to write back
 */
export function migrate(data, filename) {
  // Example: If data is an array, add a field to each entry
  if (Array.isArray(data)) {
    return data.map(entry => ({
      ...entry,
      // Add your new fields here:
      // newField: entry.newField || 'default value',
    }));
  }

  // Example: If data is an object with an array property
  if (data && typeof data === 'object') {
    // return {
    //   ...data,
    //   items: data.items?.map(item => ({ ...item, newField: 'value' }))
    // };
  }

  // Return data unchanged if no transformation needed
  return data;
}
