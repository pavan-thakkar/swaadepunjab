#!/bin/bash

# ============================================================
#  🚀 SWAAD E PUNJAB — ONE-CLICK DEPLOY SCRIPT
#  Run from your Mac: bash deploy.sh
# ============================================================

set -e

SSH_USER="u360309011"
SSH_HOST="145.79.209.51"
SSH_PORT="65002"
SSH_PASS="Maithili@0809"
REMOTE_FRONTEND="/home/u360309011/domains/swaadepunjab.com/public_html"
LOCAL_PROJECT="$(cd "$(dirname "$0")" && pwd)"
FRONTEND_DIR="$LOCAL_PROJECT/frontend"

RED='\033[0;31m'; GREEN='\033[0;32m'; BLUE='\033[0;34m'; CYAN='\033[0;36m'; NC='\033[0m'; BOLD='\033[1m'

echo ""
echo -e "${CYAN}${BOLD}╔══════════════════════════════════════════╗${NC}"
echo -e "${CYAN}${BOLD}║   🍕 SWAAD E PUNJAB — DEPLOY SCRIPT    ║${NC}"
echo -e "${CYAN}${BOLD}╚══════════════════════════════════════════╝${NC}"
echo ""

command -v sshpass &>/dev/null || brew install sshpass

# ── STEP 1: Push to GitHub ──────────────────────────────────
echo -e "${BLUE}${BOLD}[1/4] 📤 Pushing to GitHub...${NC}"
cd "$LOCAL_PROJECT"
git add -A
git commit -m "deploy: $(date '+%Y-%m-%d %H:%M:%S')" 2>/dev/null || echo "  (nothing new to commit)"
git push origin main
echo -e "${GREEN}  ✅ GitHub updated${NC}"

# ── STEP 2: Build frontend with PRODUCTION env ─────────────
echo ""
echo -e "${BLUE}${BOLD}[2/4] 🔨 Building frontend (production)...${NC}"
cd "$FRONTEND_DIR"
[ -f .env.local ] && mv .env.local .env.local.bak
rm -rf out/
OPENSSL_CONF=/dev/null npm run build
[ -f .env.local.bak ] && mv .env.local.bak .env.local
echo -e "${GREEN}  ✅ Build complete${NC}"

# ── STEP 3: Deploy backend DIRECTLY from local ──────────────
echo ""
echo -e "${BLUE}${BOLD}[3/4] 🔧 Uploading backend directly from local...${NC}"

# Sync files directly from local backend to remote backend (exclude node_modules, storage logs/sessions, env and database)
sshpass -p "$SSH_PASS" rsync -az --delete \
  --exclude='.DS_Store' \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='storage/app/public/*' \
  --exclude='public/storage' \
  --exclude='storage/framework/cache/data/*' \
  --exclude='storage/framework/views/*' \
  --exclude='storage/framework/sessions/*' \
  --exclude='storage/logs/*' \
  --exclude='database/database.sqlite' \
  -e "ssh -o StrictHostKeyChecking=no -p $SSH_PORT" \
  "$LOCAL_PROJECT/backend/" \
  "$SSH_USER@$SSH_HOST:/home/u360309011/domains/swaadepunjab.com/public_html/backend/"

# Clear cache and dump autoload on live server
sshpass -p "$SSH_PASS" ssh -tt -o StrictHostKeyChecking=no -p $SSH_PORT $SSH_USER@$SSH_HOST \
  "cd /home/u360309011/domains/swaadepunjab.com/public_html/backend && \
   sed -i 's/^APP_ENV=.*/APP_ENV=production/; s/^APP_DEBUG=.*/APP_DEBUG=false/' .env && \
   rm -f public/info.php && \
   find public -path public/storage -prune -o -type d -exec chmod 755 {} + && \
   find public -path public/storage -prune -o -type f -exec chmod 644 {} + && \
   (grep -q '^GOOGLE_CLIENT_ID=' .env || printf '\\nGOOGLE_CLIENT_ID=82292707740-slkcblrneb4jeckjbdjq85onu6pr3qgf.apps.googleusercontent.com\\n' >> .env) && \
   rm -f public/storage && \
   ln -s /home/u360309011/domains/swaadepunjab.com/public_html/backend/storage/app/public public/storage 2>/dev/null || true && \
   php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan cache:clear && \
   php artisan migrate --force && \
   composer dump-autoload --optimize && \
   echo 'Backend OK'"
echo -e "${GREEN}  ✅ Backend deployed${NC}"

# ── STEP 4: Upload frontend ─────────────────────────────────
echo ""
echo -e "${BLUE}${BOLD}[4/4] 🌐 Uploading frontend...${NC}"
sshpass -p "$SSH_PASS" rsync -az --delete \
  --exclude='.DS_Store' --exclude='*.apk' --exclude='backend/' \
  --exclude='*.zip' --exclude='*.sql' --exclude='haveli*' \
  -e "ssh -o StrictHostKeyChecking=no -p $SSH_PORT" \
  "$FRONTEND_DIR/out/" \
  "$SSH_USER@$SSH_HOST:$REMOTE_FRONTEND/"

# Remove any old database dumps from the live site (rsync skips *.sql, so delete them here)
sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no -p $SSH_PORT $SSH_USER@$SSH_HOST \
  "rm -f $REMOTE_FRONTEND/*.sql $REMOTE_FRONTEND/backend/public/info.php && \
   find $REMOTE_FRONTEND -path $REMOTE_FRONTEND/backend -prune -o -type d -exec chmod 755 {} + && \
   find $REMOTE_FRONTEND -path $REMOTE_FRONTEND/backend -prune -o -type f -exec chmod 644 {} +"

# Write .htaccess after rsync (rsync may overwrite it)
sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no -p $SSH_PORT $SSH_USER@$SSH_HOST 'cat > /home/u360309011/domains/swaadepunjab.com/public_html/.htaccess << '"'"'HTA'"'"'
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
  # Never serve the Laravel backend folder (.env, database, logs) through the main domain
  RewriteRule ^backend(/.*)?$ - [F,L]
  # Never serve hidden files (.env, .git) or database/backup files
  RewriteRule (^|/)\.(?!well-known) - [F,L]
  RewriteRule \.(sql|sqlite|log|bak|env)$ - [F,L,NC]
  RewriteCond %{REQUEST_FILENAME} -f [OR]
  RewriteCond %{REQUEST_FILENAME} -d
  RewriteRule ^ - [L]
  RewriteCond %{DOCUMENT_ROOT}%{REQUEST_URI}index.html -f
  RewriteRule ^(.+)/$ /index.html [L]
  RewriteRule ^item/.+$ /item/1/index.html [L]
  RewriteRule ^track/.+$ /track/demo/index.html [L]
  RewriteRule ^ /index.html [L]
</IfModule>
HTA'
echo -e "${GREEN}  ✅ Frontend uploaded${NC}"

# ── Verify ──────────────────────────────────────────────────
echo ""
echo -e "${BLUE}${BOLD}🔍 Verifying...${NC}"
F=$(curl -s -o /dev/null -w "%{http_code}" --max-time 8 https://swaadepunjab.com)
A=$(curl -s -o /dev/null -w "%{http_code}" --max-time 8 https://backend.swaadepunjab.com/api/menu)
echo -e "  Frontend: HTTP $F  |  API: HTTP $A"

echo ""
echo -e "${GREEN}${BOLD}╔══════════════════════════════════════════╗${NC}"
echo -e "${GREEN}${BOLD}║        ✅ DEPLOYMENT COMPLETE!          ║${NC}"
echo -e "${GREEN}${BOLD}╚══════════════════════════════════════════╝${NC}"
echo -e "  🌍 ${CYAN}https://swaadepunjab.com${NC}"
echo -e "  🔧 ${CYAN}https://backend.swaadepunjab.com/admin${NC}"
echo ""
