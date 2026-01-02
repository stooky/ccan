#!/bin/bash
#
# Reload Nginx Configuration
# Usage: sudo bash reload-nginx.sh
#
# Regenerates nginx config from config.yaml and reloads nginx.
# Use this after modifying redirects in config.yaml.
#

set -e

INSTALL_DIR="/var/www/ccan"
NGINX_CONF="/etc/nginx/sites-available/ccan"

# Colors
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m'

# Check if running as root
if [[ $EUID -ne 0 ]]; then
    echo -e "${RED}[ERROR]${NC} This script must be run as root (use sudo)"
    exit 1
fi

# Get domain from existing config
DOMAIN=$(grep -oP 'server_name \K[^;]+' "$NGINX_CONF" | head -1)
if [[ -z "$DOMAIN" ]]; then
    echo -e "${RED}[ERROR]${NC} Could not detect domain from existing nginx config"
    exit 1
fi

echo -e "${GREEN}[INFO]${NC} Regenerating nginx config for $DOMAIN..."

# Generate redirects from config.yaml
echo -e "${GREEN}[INFO]${NC} Reading redirects from config.yaml..."
REDIRECTS=""
in_redirects=false
while IFS= read -r line; do
    if [[ "$line" =~ ^redirects: ]]; then
        in_redirects=true
        continue
    fi
    if [[ "$in_redirects" == true ]] && [[ "$line" =~ ^[a-z] ]] && [[ ! "$line" =~ ^[[:space:]] ]]; then
        in_redirects=false
        continue
    fi
    if [[ "$in_redirects" == true ]] && [[ "$line" =~ ^[[:space:]]+(/[^:]*):\ (.+)$ ]]; then
        old_path="${BASH_REMATCH[1]}"
        new_path="${BASH_REMATCH[2]}"
        REDIRECTS+="    location = $old_path { return 301 $new_path; }"$'\n'
    fi
done < "$INSTALL_DIR/config.yaml"

redirect_count=$(echo "$REDIRECTS" | grep -c 'location' || echo "0")
echo -e "${GREEN}[INFO]${NC} Loaded $redirect_count redirects from config.yaml"

# Backup existing config
cp "$NGINX_CONF" "$NGINX_CONF.bak"

# Create new nginx configuration
cat > "$NGINX_CONF" << EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;

    root $INSTALL_DIR/dist;
    index index.html;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml application/javascript application/json image/svg+xml;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|webp|woff|woff2|ttf|svg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # PHP API endpoints
    location ~ ^/api/.*\.php$ {
        root $INSTALL_DIR;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    # Block direct access to data directory
    location /data {
        deny all;
        return 404;
    }

    # Block access to config file
    location = /config.yaml {
        deny all;
        return 404;
    }

    # ============================================
    # URL Redirects (from config.yaml)
    # ============================================
$REDIRECTS
    # Handle Astro routes (clean URLs)
    location / {
        try_files \$uri \$uri/ \$uri.html /index.html;
    }

    # Error pages
    error_page 404 /404.html;
    location = /404.html {
        internal;
    }
}
EOF

# Test nginx configuration
echo -e "${GREEN}[INFO]${NC} Testing nginx configuration..."
if nginx -t 2>&1; then
    echo -e "${GREEN}[INFO]${NC} Nginx config test passed"

    # Reload nginx
    echo -e "${GREEN}[INFO]${NC} Reloading nginx..."
    systemctl reload nginx

    echo ""
    echo -e "${GREEN}[INFO]${NC} Nginx reloaded successfully!"
    echo "  Redirects: $redirect_count"
    echo "  Config: $NGINX_CONF"
else
    echo -e "${RED}[ERROR]${NC} Nginx config test FAILED - restoring backup"
    cp "$NGINX_CONF.bak" "$NGINX_CONF"
    exit 1
fi
