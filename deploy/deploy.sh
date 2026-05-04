#!/usr/bin/env bash
# =============================================================================
# AdNiba Backend — Production Deploy Script
# Usage: ./deploy/deploy.sh [--skip-migrate]
# =============================================================================
set -euo pipefail

APP_DIR="/var/www/adniba"
PHP="php8.3"
ARTISAN="$PHP $APP_DIR/artisan"

echo "🚀  AdNiba deploy starting at $(date)"
cd "$APP_DIR"

# ── 1. Pull latest code ───────────────────────────────────────────────────────
echo "📦  Pulling latest code..."
git pull origin main

# ── 2. Install / update PHP dependencies (no dev) ────────────────────────────
echo "🔧  Installing Composer dependencies..."
composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --prefer-dist \
    --optimize-autoloader

# ── 3. Run database migrations ────────────────────────────────────────────────
if [[ "${1:-}" != "--skip-migrate" ]]; then
    echo "🗃️   Running migrations..."
    $ARTISAN migrate --force
fi

# ── 4. Clear old caches before re-caching ────────────────────────────────────
echo "🗑️   Clearing caches..."
$ARTISAN config:clear
$ARTISAN route:clear
$ARTISAN view:clear
$ARTISAN cache:clear            # Clear Redis config keys

# ── 5. Rebuild Laravel caches (makes OPcache + route lookups fast) ────────────
echo "⚡  Rebuilding caches..."
$ARTISAN config:cache
$ARTISAN route:cache
$ARTISAN event:cache

# ── 6. Restart queue workers gracefully (finishes in-flight jobs) ─────────────
echo "🔄  Restarting queue workers..."
$ARTISAN queue:restart

# ── 7. Reload PHP-FPM (picks up new OPcache files) ───────────────────────────
echo "🔁  Reloading PHP-FPM..."
sudo systemctl reload php8.3-fpm

# ── 8. Restart supervisor worker group ───────────────────────────────────────
echo "👷  Restarting Supervisor worker group..."
sudo supervisorctl restart adniba:

# ── 9. Smoke test — hit health endpoint ──────────────────────────────────────
echo "🏥  Running health check..."
HEALTH=$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1/up)
if [ "$HEALTH" == "200" ]; then
    echo "✅  Health check passed (HTTP $HEALTH)"
else
    echo "❌  Health check FAILED (HTTP $HEALTH) — check logs immediately"
    exit 1
fi

echo "✅  Deploy complete at $(date)"
