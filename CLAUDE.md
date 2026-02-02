# C-Can Sam Project Instructions

## Deployment

### SSH Access
- **Production:** `ssh -i ~/.ssh/id_rsa root@ccansam.com`
- **Staging:** `ssh -i ~/.ssh/id_rsa root@ccan.crkid.com`

### Deploy Commands
```bash
# Deploy to staging
ssh -i ~/.ssh/id_rsa root@ccan.crkid.com "cd /var/www/ccan && git pull origin main && npm ci --production=false && npm run build && chown -R www-data:www-data . && systemctl reload nginx"

# Deploy to production
ssh -i ~/.ssh/id_rsa root@ccansam.com "cd /var/www/ccan && git pull origin main && npm ci --production=false && npm run build && chown -R www-data:www-data . && systemctl reload nginx"
```

### Server Paths
- Install directory: `/var/www/ccan`
- Submissions: `/var/www/ccan/data/submissions.json`
- Config: `/var/www/ccan/config.yaml`
- Nginx logs: `/var/log/nginx/`

## Admin Panel
- URL: `https://ccansam.com/api/admin.php?key=ccan-admin-2024`
- Staging: `https://ccan.crkid.com/api/admin.php?key=ccan-admin-2024`

## Branch
- Main branch: `main`
