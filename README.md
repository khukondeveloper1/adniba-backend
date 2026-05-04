# AdNiba Backend

**SaaS-grade Ad Network Management & Mediation Platform**

A production-ready Laravel 11 API backend that serves real-time ad configurations to mobile SDKs, supports multi-network mediation with fallback control, handles high-volume event tracking asynchronously, and provides rich analytics.


## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        Mobile SDK                               │
│  1. GET /ads/networks  (init — which SDKs to load)              │
│  2. GET /ads/config    (per placement — fallback/force logic)   │
│  3. POST /ads/event    (async telemetry — fire-and-forget)      │
└──────────────┬──────────────────────────────────────────────────┘
               │  x-api-key header
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                     Nginx (TLS termination)                     │
│         rate-limit zones: sdk=60r/s  admin=5r/s                 │
└──────────────┬──────────────────────────────────────────────────┘
               ▼
┌─────────────────────────────────────────────────────────────────┐
│                   Laravel 11 (PHP-FPM)                          │
│                                                                 │
│  ┌──────────────────────┐   ┌──────────────────────────────┐   │
│  │   SDK API  /v1/ads/  │   │  Admin API  /v1/admin/       │   │
│  │  NetworksController  │   │  AppController               │   │
│  │  AdConfigController  │   │  AdNetworkController         │   │
│  │  EventController     │   │  AdUnitController            │   │
│  └──────────┬───────────┘   │  AdSettingController         │   │
│             │               │  AnalyticsController         │   │
│  ┌──────────▼───────────┐   └──────────────────────────────┘   │
│  │    Service Layer      │                                      │
│  │  AdConfigService ◄────┼── Redis cache (TTL 60-300s)         │
│  │  AdEventService  ─────┼──► Queue (events channel)           │
│  │  AdNetworkService     │                                      │
│  │  AdUnitService        │                                      │
│  │  AdSettingService     │                                      │
│  └──────────┬───────────┘                                      │
│             │                                                   │
│  ┌──────────▼───────────┐                                       │
│  │  Repository Layer    │                                       │
│  │  AppRepository       │                                       │
│  │  AdNetworkRepository │                                       │
│  │  AdUnitRepository    │                                       │
│  │  AdSettingRepository │                                       │
│  │  AdEventRepository   │                                       │
│  └──────────┬───────────┘                                       │
└─────────────┼───────────────────────────────────────────────────┘
              │
    ┌─────────┴──────────┐
    │                    │
    ▼                    ▼
┌────────┐          ┌─────────────────────────────────────┐
│ MySQL  │          │  Redis                              │
│ InnoDB │          │  DB 0: Queue (events + default)     │
│ (data) │          │  DB 1: Config cache (adcfg:*keys)   │
└────────┘          └─────────────────────────────────────┘
                              │
                    ┌─────────▼──────────┐
                    │  Queue Workers      │
                    │  4× events channel  │
                    │  2× default channel │
                    │  (TrackAdEvent job) │
                    └────────────────────┘
```

---

## Tech Stack

| Layer          | Technology                              |
|----------------|-----------------------------------------|
| Framework      | Laravel 11 (PHP 8.3)                    |
| Database       | MySQL 8 — InnoDB, ROW_FORMAT=COMPRESSED |
| Cache          | Redis 7 (separate DBs for cache/queue)  |
| Queue          | Laravel Queue — Redis driver            |
| Auth (Admin)   | JWT via `tymon/jwt-auth`                |
| Auth (SDK)     | API Key (`x-api-key` header)            |
| Architecture   | Service + Repository pattern            |
| API Style      | RESTful, versioned `/api/v1/`           |

---

## Quick Start (Docker)

```bash
# 1. Clone and configure
git clone https://github.com/your-org/adniba-backend.git
cd adniba-backend
cp .env.example .env

# 2. Start all services
docker compose up -d

# 3. Install dependencies + generate keys
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret

# 4. Run migrations (creates tables + seeds default admin)
docker compose exec app php artisan migrate

# 5. Verify
curl http://localhost:8080/up
# → {"status":"up"}
```

**Default admin credentials:** `admin` / `changeme123` *(change immediately!)*

---

## Local Dev Without Docker

```bash
# Requirements: PHP 8.3, Composer, MySQL 8, Redis 7

composer install
cp .env.example .env
# Edit .env — set DB_*, REDIS_*

php artisan key:generate
php artisan jwt:secret
php artisan migrate

# Start the dev server
php artisan serve

# Start queue workers (in separate terminals)
php artisan queue:work redis --queue=events --sleep=1
php artisan queue:work redis --queue=default
```

---

## Database Schema

```sql
admin_users   — Admin panel users (JWT auth)
apps          — Mobile applications (API key, status, global ad toggle)
ad_networks   — Ad network registrations per app (admob / meta / unity)
ad_units      — Ad unit placements (type + placement + priority + unit_id)
ad_settings   — Fallback/force mode control per app+type+placement
ad_events     — High-volume SDK telemetry (queued writes, compressed storage)
```

**Key indexes for hot paths:**

| Table       | Index                              | Used by                |
|-------------|-------------------------------------|------------------------|
| `ad_units`  | `(app_id, ad_type, placement, priority)` | Config API          |
| `ad_events` | `(app_id, created_at, event_type)` | Analytics queries      |
| `ad_events` | `(app_id, network, created_at)`    | Network breakdown      |
| `apps`      | `api_key` (UNIQUE)                 | SDK auth middleware     |

---

## API Reference

### SDK Endpoints (require `x-api-key` header)

#### `GET /api/v1/ads/networks`
Returns which ad SDKs to initialise, ordered by priority.

```json
{
  "status": "ok",
  "networks": [
    { "name": "admob", "priority": 1 },
    { "name": "meta",  "priority": 2 }
  ]
}
```

#### `GET /api/v1/ads/config?ad_type=banner&placement=home`
Returns the full ad configuration for a placement. Redis-cached.

```json
{
  "status": "ok",
  "fallback": true,
  "ad_type": "banner",
  "placement": "home",
  "ads": [
    { "network": "admob", "unit_id": "ca-app-pub-xxx", "priority": 1 },
    { "network": "meta",  "unit_id": "meta-xxx",        "priority": 2 }
  ],
  "refresh_interval": 143
}
```

**Config logic:**

```
fallback_enabled = true  → return ALL enabled units sorted by priority (mediation)
fallback_enabled = false → return ONLY the forced network's unit (single network)
global_ad_enabled = false → return empty ads immediately (kill-switch)
```

#### `POST /api/v1/ads/event`
Fire-and-forget SDK telemetry. Returns `202 Accepted` immediately.

```json
// Request body
{
  "network":    "admob",
  "ad_type":    "banner",
  "placement":  "home",
  "event_type": "impression"
}

// Response
{ "status": "queued" }
```

Valid `event_type` values: `request`, `load`, `impression`, `click`, `fail`

---

### Admin Endpoints (require `Authorization: Bearer <jwt>` header)

#### Auth
```
POST /api/v1/admin/auth/login     { username, password }
POST /api/v1/admin/auth/refresh
POST /api/v1/admin/auth/logout
```

#### App Management
```
GET    /api/v1/admin/apps
POST   /api/v1/admin/apps                    { name, package_name }
GET    /api/v1/admin/apps/{id}
PUT    /api/v1/admin/apps/{id}
DELETE /api/v1/admin/apps/{id}
POST   /api/v1/admin/apps/{id}/rotate-key
PATCH  /api/v1/admin/apps/{id}/status        { active: bool }
PATCH  /api/v1/admin/apps/{id}/ads-enabled   { enabled: bool }
```

#### Network Management
```
GET    /api/v1/admin/apps/{appId}/networks
POST   /api/v1/admin/apps/{appId}/networks           { name: admob|meta|unity }
PATCH  /api/v1/admin/apps/{appId}/networks/{id}/toggle  { enabled: bool }
DELETE /api/v1/admin/apps/{appId}/networks/{id}
```

#### Ad Units
```
GET    /api/v1/admin/apps/{appId}/units
POST   /api/v1/admin/apps/{appId}/units      { network_id, ad_type, placement, unit_id, priority }
GET    /api/v1/admin/apps/{appId}/units/{id}
PUT    /api/v1/admin/apps/{appId}/units/{id}
PATCH  /api/v1/admin/apps/{appId}/units/{id}/toggle  { enabled: bool }
DELETE /api/v1/admin/apps/{appId}/units/{id}
```

#### Ad Settings (Fallback/Force Control)
```
GET    /api/v1/admin/apps/{appId}/settings
POST   /api/v1/admin/apps/{appId}/settings           { ad_type, placement, fallback_enabled, network_id? }
GET    /api/v1/admin/apps/{appId}/settings/{adType}/{placement}
DELETE /api/v1/admin/apps/{appId}/settings/{id}
```

#### Analytics
```
GET /api/v1/admin/analytics?app_id=1&from=2024-01-01&to=2024-01-31
GET /api/v1/admin/analytics/daily?app_id=1&from=2024-01-01&to=2024-01-31
```

**Analytics response:**
```json
{
  "status": "ok",
  "data": {
    "total_requests":    10000,
    "total_impressions": 8500,
    "total_clicks":      340,
    "total_fails":       120,
    "ctr":               4.0,
    "fill_rate":         85.0,
    "match_rate":        95.2,
    "by_network": [...],
    "by_placement": [...]
  }
}
```

---

## SDK Behaviour Guide

```
App launch:
  1. Call GET /ads/networks
  2. Init top-priority network immediately
  3. Init remaining networks lazily in background

Ad loading:
  1. Call GET /ads/config?ad_type=X&placement=Y
  2. If fallback=false → use ads[0] only (forced network)
  3. If fallback=true  → try ads[0], on fail try ads[1], etc.

Caching:
  SDK must cache the config response locally
  Refresh after refresh_interval seconds

Events:
  POST /ads/event for: request → load → impression
                  and: click, fail (when they occur)
```

---

## Running Tests

```bash
# All tests
php artisan test

# With coverage
php artisan test --coverage --min=80

# Specific suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Single file
php artisan test tests/Feature/SDK/AdConfigEndpointTest.php
```

---

## Production Deployment

```bash
# Zero-downtime deploy
./deploy/deploy.sh

# Skip migrations (hotfix deploy)
./deploy/deploy.sh --skip-migrate
```

**Post-deploy checklist:**
- [ ] Verify `/up` health endpoint returns 200
- [ ] Check Supervisor: `supervisorctl status adniba:`
- [ ] Monitor queue: `php artisan queue:monitor redis:events,default`
- [ ] Check Redis config cache: `redis-cli keys "adniba_adcfg:*"`

---

## Scaling Strategy

| Concern              | Solution                                          |
|----------------------|---------------------------------------------------|
| Config API latency   | Redis cache per placement (TTL 60-300s, jittered) |
| Event throughput     | 4× queue workers on dedicated `events` channel   |
| Cache invalidation   | Targeted bust on admin changes (key-level)        |
| DB write load        | Compressed `ad_events` + InnoDB tuning            |
| Horizontal scale     | Stateless app servers behind load balancer        |
| Future: microservice | Event tracking can be extracted independently     |
| Future: analytics    | Batch aggregation / ClickHouse migration ready    |

---

## Security

- SDK routes: API key validated on every request via `AuthenticateSdkKey` middleware
- Admin routes: Short-lived JWT (60 min) with refresh token support
- Rate limiting: 300 req/min per app for config, 1000/min for events, 60/min for admin
- Input validation: All endpoints use `FormRequest` classes with strict rules
- No HTML error responses: all errors return consistent JSON envelopes
- Nginx blocks `.env`, `artisan`, and `composer.*` paths at the web layer
