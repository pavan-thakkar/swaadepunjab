#!/bin/bash

# ============================================================
#  🚀 SWAAD E PUNJAB — ONE-CLICK DEPLOY SCRIPT
#  Run this from your Mac to deploy to server
#  Usage: bash deploy.sh
# ============================================================

set -e  # Stop on any error

# ── CONFIG ──────────────────────────────────────────────────
SSH_USER="u360309011"
SSH_HOST="145.79.209.51"
SSH_PORT="65002"
SSH_PASS="Thakkar@1999"
REMOTE_FRONTEND="/home/u360309011/domains/swaadepunjab.com/public_html"
REMOTE_BACKEND="/home/u360309011/domains/swaadepunjab.com/public_html/backend"
LOCAL_PROJECT="$(cd "$(dirname "$0")" && pwd)"
FRONTEND_DIR="$LOCAL_PROJECT/frontend"
# ────────────────────────────────────────────────────────────

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color
BOLD='\033[1m'

echo ""
echo -e "${CYAN}${BOLD}╔══════════════════════════════════════════╗${NC}"
echo -e "${CYAN}${BOLD}║   🍕 SWAAD E PUNJAB — DEPLOY SCRIPT    ║${NC}"
echo -e "${CYAN}${BOLD}╚══════════════════════════════════════════╝${NC}"
echo ""

# Check sshpass is installed
if ! command -v sshpass &> /dev/null; then
    echo -e "${YELLOW}⚠️  sshpass not found. Installing...${NC}"
    brew install sshpass
fi

SSH_CMD="sshpass -p '$SSH_PASS' ssh -o StrictHostKeyChecking=no -p $SSH_PORT $SSH_USER@$SSH_HOST"
RSYNC_CMD="sshpass -p '$SSH_PASS' rsync -avz --progress -e 'ssh -o StrictHostKeyChecking=no -p $SSH_PORT'"

# ── STEP 1: PUSH LOCAL CODE TO GITHUB ──────────────────────
echo -e "${BLUE}${BOLD}[1/4] 📤 Pushing local code to GitHub...${NC}"
cd "$LOCAL_PROJECT"
git add -A
COMMIT_MSG="deploy: $(date '+%Y-%m-%d %H:%M:%S')"
git commit -m "$COMMIT_MSG" 2>/dev/null || echo "  (nothing new to commit)"
git push origin main
echo -e "${GREEN}  ✅ GitHub updated${NC}"
echo ""

# ── STEP 2: BUILD FRONTEND ──────────────────────────────────
echo -e "${BLUE}${BOLD}[2/4] 🔨 Building Next.js frontend...${NC}"
cd "$FRONTEND_DIR"
npm run build
if [ ! -d "out" ]; then
    echo -e "${RED}  ❌ Build failed — 'out' folder not found!${NC}"
    exit 1
fi
echo -e "${GREEN}  ✅ Frontend built successfully${NC}"
echo ""

# ── STEP 3: BACKUP + DEPLOY BACKEND (from GitHub) ──────────
echo -e "${BLUE}${BOLD}[3/4] 🔧 Deploying backend from GitHub to server...${NC}"

eval "$SSH_CMD" << 'ENDSSH'
set -e
BACKEND_DIR="/home/u360309011/domains/swaadepunjab.com/public_html/backend"
cd "$BACKEND_DIR"

# Backup .env and database (NEVER overwrite these)
echo "  💾 Backing up .env and database..."
cp .env /tmp/env_backup_$(date +%Y%m%d_%H%M%S)
cp database/database.sqlite /tmp/db_backup_$(date +%Y%m%d_%H%M%S).sqlite 2>/dev/null || true

# Fetch latest from GitHub
echo "  📥 Fetching from GitHub..."
git fetch https://github.com/pavan-thakkar/swaadepunjab.git main

# Checkout only backend/ folder from GitHub (preserves .env, vendor, database)
echo "  🔄 Updating backend PHP files..."
git checkout FETCH_HEAD -- backend/

# Restore .env (git checkout may have touched it - force original back)
ENV_BACKUP=$(ls -t /tmp/env_backup_* 2>/dev/null | head -1)
if [ -n "$ENV_BACKUP" ]; then
    cp "$ENV_BACKUP" .env
fi

# Laravel maintenance tasks
echo "  🧹 Clearing Laravel caches..."
php artisan config:clear 2>&1
php artisan route:clear 2>&1
php artisan view:clear 2>&1
php artisan cache:clear 2>&1

echo "  📋 Running migrations..."
php artisan migrate --force 2>&1

echo "  ✅ Backend deployed!"
ENDSSH

echo -e "${GREEN}  ✅ Backend deployed successfully${NC}"
echo ""

# ── STEP 4: UPLOAD FRONTEND STATIC BUILD ───────────────────
echo -e "${BLUE}${BOLD}[4/4] 🌐 Uploading frontend to server...${NC}"

# Backup htaccess before sync
eval "$SSH_CMD" "cp $REMOTE_FRONTEND/.htaccess /tmp/htaccess_backup 2>/dev/null || true"

# Rsync frontend build (exclude heavy/irrelevant files)
eval "$RSYNC_CMD \
  --exclude='.DS_Store' \
  --exclude='*.apk' \
  --exclude='backend/' \
  --exclude='*.zip' \
  --exclude='*.sql' \
  '$FRONTEND_DIR/out/' \
  '$SSH_USER@$SSH_HOST:$REMOTE_FRONTEND/'"

# Restore htaccess (custom SPA routing rules must be preserved)
eval "$SSH_CMD" "cp /tmp/htaccess_backup $REMOTE_FRONTEND/.htaccess 2>/dev/null || true"

echo -e "${GREEN}  ✅ Frontend uploaded successfully${NC}"
echo ""

# ── VERIFY DEPLOYMENT ──────────────────────────────────────
echo -e "${BLUE}${BOLD}🔍 Verifying deployment...${NC}"
LIVE_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 https://swaadepunjab.com 2>/dev/null || echo "000")
API_STATUS=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 https://backend.swaadepunjab.com/api/menu 2>/dev/null || echo "000")

echo -e "  🌐 Frontend (swaadepunjab.com):         HTTP $LIVE_STATUS"
echo -e "  🔌 Backend API (backend.../api/menu):   HTTP $API_STATUS"

echo ""
echo -e "${GREEN}${BOLD}╔══════════════════════════════════════════╗${NC}"
echo -e "${GREEN}${BOLD}║   ✅ DEPLOYMENT COMPLETE!               ║${NC}"
echo -e "${GREEN}${BOLD}╚══════════════════════════════════════════╝${NC}"
echo ""
echo -e "  🌍 Live site:   ${CYAN}https://swaadepunjab.com${NC}"
echo -e "  🔧 Admin panel: ${CYAN}https://backend.swaadepunjab.com/admin${NC}"
echo -e "  📦 API:         ${CYAN}https://backend.swaadepunjab.com/api${NC}"
echo ""
