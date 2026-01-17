# Data Migrations

This folder contains data migration scripts that run automatically during deployment when the data version changes.

## How It Works

1. **Version is tracked** in `config.yaml` under `data.version`
2. **Deploy script** backs up all data files before `git pull`
3. **If version changed**, `migrate.js` runs all migrations from old → new version
4. **Migrations run sequentially**: v1→v2, then v2→v3, etc.

## Creating a Migration

When you need to change the structure of a data file:

1. **Copy the template**:
   ```bash
   cp migrations/_template.js migrations/v1_to_v2.js
   ```

2. **Edit the migration** to transform your data:
   ```javascript
   export const files = ['spam-log.json'];
   export const description = 'Add source field to spam entries';

   export function migrate(data, filename) {
     return data.map(entry => ({
       ...entry,
       source: entry.source || 'unknown'
     }));
   }
   ```

3. **Bump the version** in `config.yaml`:
   ```yaml
   data:
     version: 2  # was 1
   ```

4. **Deploy** - the migration runs automatically

## Example Migrations

### Adding a field to all entries
```javascript
export const files = ['submissions.json'];
export const description = 'Add priority field to submissions';

export function migrate(data) {
  return data.map(entry => ({
    ...entry,
    priority: entry.priority || 'normal'
  }));
}
```

### Renaming a field
```javascript
export const files = ['reviews.json'];
export const description = 'Rename author to authorName';

export function migrate(data) {
  return {
    ...data,
    reviews: data.reviews.map(review => {
      const { author, ...rest } = review;
      return { ...rest, authorName: author };
    })
  };
}
```

### Restructuring data
```javascript
export const files = ['inventory.json'];
export const description = 'Group items by location';

export function migrate(data) {
  const grouped = {};
  data.forEach(item => {
    const loc = item.location || 'unknown';
    if (!grouped[loc]) grouped[loc] = [];
    grouped[loc].push(item);
  });
  return { version: 2, byLocation: grouped, items: data };
}
```

## Backups

Every deploy creates a backup in `data/backups/YYYY-MM-DD-HHMMSS/`. The last 10 backups are kept automatically.

To restore from a backup:
```bash
# SSH to server
ssh root@ccansam.com

# List backups
ls -la /var/www/ccan/data/backups/

# Restore a specific file
cp /var/www/ccan/data/backups/2026-01-17-050000/submissions.json /var/www/ccan/data/
```

## Testing Migrations Locally

```bash
# Run a migration manually
node scripts/migrate.js 1 2
```
