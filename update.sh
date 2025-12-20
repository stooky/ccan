#!/bin/bash
#
# C-Can Sam Update Script
# Usage: sudo bash update.sh
#
# Pulls latest changes and rebuilds the site
#

set -e

INSTALL_DIR="/var/www/ccan"
BRANCH="storage-containers"

# Colors
GREEN='\033[0;32m'
NC='\033[0m'

echo -e "${GREEN}[INFO]${NC} Updating C-Can Sam site..."

cd "$INSTALL_DIR"

echo -e "${GREEN}[INFO]${NC} Pulling latest changes..."
git fetch origin
git checkout "$BRANCH"
git pull origin "$BRANCH"

echo -e "${GREEN}[INFO]${NC} Installing dependencies..."
npm ci --production=false

echo -e "${GREEN}[INFO]${NC} Building site..."
npm run build

echo -e "${GREEN}[INFO]${NC} Setting permissions..."
chown -R www-data:www-data "$INSTALL_DIR"

echo -e "${GREEN}[INFO]${NC} Reloading nginx..."
systemctl reload nginx

echo ""
echo -e "${GREEN}[INFO]${NC} Update complete!"
