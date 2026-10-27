#!/bin/bash
# Swaad E Punjab — install what's missing and run locally (double-click me)
cd "$(dirname "$0")"
ROOT="$(pwd)"
echo "== Swaad E Punjab local setup =="

# 1. Homebrew
if ! command -v brew >/dev/null 2>&1; then
  for p in /opt/homebrew/bin/brew /usr/local/bin/brew; do [ -x "$p" ] && eval "$($p shellenv)"; done
fi
if ! command -v brew >/dev/null 2>&1; then
  echo ">> Installing Homebrew (it may ask for your Mac password)..."
  /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
  for p in /opt/homebrew/bin/brew /usr/local/bin/brew; do [ -x "$p" ] && eval "$($p shellenv)"; done
fi

# 2. PHP 8.3+, Composer, Node
need_php=1
if command -v php >/dev/null 2>&1; then
  php -r 'exit(version_compare(PHP_VERSION,"8.3.0",">=")?0:1);' && need_php=0
fi
[ $need_php = 1 ] && { echo ">> Installing PHP..."; brew install php; }
command -v composer >/dev/null 2>&1 || { echo ">> Installing Composer..."; brew install composer; }
command -v node >/dev/null 2>&1 || { echo ">> Installing Node.js..."; brew install node; }
echo "PHP $(php -r 'echo PHP_VERSION;') | Node $(node -v)"

# 3. Backend deps
cd "$ROOT/backend"
[ -f vendor/autoload.php ] || composer install
[ -f .env ] || { cp .env.example .env; php artisan key:generate; }
[ -f database/database.sqlite ] || { touch database/database.sqlite; php artisan migrate --force; }

# 4. Frontend deps
cd "$ROOT/frontend"
[ -d node_modules/next ] || npm install

# 5. Start servers
cd "$ROOT/backend"
php artisan serve --host=127.0.0.1 --port=8000 > "$ROOT/backend-server.log" 2>&1 &
BACK=$!
trap 'echo; echo "Stopping..."; kill $BACK 2>/dev/null; exit' INT TERM EXIT
echo ">> Backend running at http://127.0.0.1:8000 (log: backend-server.log)"
cd "$ROOT/frontend"
( sleep 8; open http://localhost:3000 ) &
echo ">> Frontend starting at http://localhost:3000 - close this window or press Ctrl+C to stop both."
npm run dev
