# C-Can Sam Project Instructions

## Deployment

### SSH Access
- **Production:** `ssh root@ccansam.com`
- **Staging:** `ssh root@ccan.crkid.com`
- SSH key must be configured in `~/.ssh/config` or added to ssh-agent

### Deploy Commands
```bash
# Deploy to both (preferred — uses scripts/deploy.js)
npm run deploy:prod
npm run deploy:staging

# Ship (commit + push + deploy in one command)
npm run ship "commit message"
```

### Server Paths
- Install directory: `/var/www/ccan`
- Submissions: `/var/www/ccan/data/submissions.json`
- Config: `/var/www/ccan/config.yaml`
- Nginx logs: `/var/log/nginx/`

## Admin Panel
- URL: `https://ccansam.com/api/admin.php?key=<ADMIN_SECRET_PATH from .env>`
- Staging: `https://ccan.crkid.com/api/admin.php?key=<ADMIN_SECRET_PATH from .env>`
- See `.env` or `.env.example` for the admin key — do not hardcode secrets in this file

## Branch
- Main branch: `storage-containers`
