#!/bin/bash

# ============================================================
#  🚀 SWAAD E PUNJAB — ONE-CLICK DEPLOY SCRIPT
#  Run from your Mac: bash deploy.sh
# ============================================================

set -e

SSH_USER="u360309011"
SSH_HOST="145.79.209.51"
SSH_PORT="65002"
SSH_PASS="Thakkar@1999"
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
npm run build
[ -f .env.local.bak ] && mv .env.local.bak .env.local
echo -e "${GREEN}  ✅ Build complete${NC}"

# ── STEP 3: Deploy backend from GitHub ─────────────────────
echo ""
echo -e "${BLUE}${BOLD}[3/4] 🔧 Deploying backend...${NC}"
sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no -p $SSH_PORT $SSH_USER@$SSH_HOST << 'ENDSSH'
  set -e
  BDIR="/home/u360309011/domains/swaadepunjab.com/public_html/backend"
  cd "$BDIR"
  cp .env /tmp/env_bak_$(date +%s)
  cp database/database.sqlite /tmp/db_bak_$(date +%s).sqlite 2>/dev/null || true
  git fetch https://github.com/pavan-thakkar/swaadepunjab.git main
  git checkout FETCH_HEAD -- backend/
  cp $(ls -t /tmp/env_bak_* | head -1) .env
  php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan cache:clear
  php artisan migrate --force
  echo "Backend OK"
ENDSSH
echo -e "${GREEN}  ✅ Backend deployed${NC}"

# ── STEP 4: Upload frontend ─────────────────────────────────
echo ""
echo -e "${BLUE}${BOLD}[4/4] 🌐 Uploading frontend...${NC}"
sshpass -p "$SSH_PASS" rsync -az --delete \
  --exclude='.DS_Store' --exclude='*.apk' --exclude='backend/' \
  --exclude='*.zip' --exclude='*.sql' \
  -e "ssh -o StrictHostKeyChecking=no -p $SSH_PORT" \
  "$FRONTEND_DIR/out/" \
  "$SSH_USER@$SSH_HOST:$REMOTE_FRONTEND/"

# Write .htaccess after rsync (rsync may overwrite it)
sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no -p $SSH_PORT $SSH_USER@$SSH_HOST 'cat > /home/u360309011/domains/swaadepunjab.com/public_html/.htaccess << '"'"'HTA'"'"'
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteBase /
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
