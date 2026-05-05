# ARTE Media Catalog — Laravel 11 + Oracle XE 21c POC

Reference implementation for **ARTE GEIE Marché 25213019, Cat. 1** (Full-Stack Development).
Demonstrates the exact stack listed in the brief: Laravel + Oracle/PL-SQL + REST + tests + CI.

> Author: Romain Sultan · github.com/Clerks303 · MIT licensed.
> This is a public artifact; no client data is involved.

---

## What it does

A small **Media Catalog API** modelled around an ARTE-relevant domain:

- **Channels** (e.g. ARTE France, ARTE Deutschland)
- **Programs** (title, synopsis, duration, channel, genres)
- **Genres** (Documentary, Fiction, News, Culture, Cinema)
- **Broadcasts** (program × channel × scheduled time × replay window)
- **Audience stats** computed by a **PL/SQL pipelined function** called via raw SQL.

### Endpoints

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET    | `/api/v1/channels`                 | — | List channels |
| GET    | `/api/v1/channels/{id}`            | — | One channel |
| GET    | `/api/v1/channels/{id}/programs`   | — | Programs of a channel |
| GET    | `/api/v1/programs?search=&genre=`  | — | Search/filter programs |
| GET    | `/api/v1/programs/{id}`            | — | One program (with channel + genres) |
| GET    | `/api/v1/broadcasts?from=&to=`     | — | Schedule window |
| GET    | `/api/v1/broadcasts/{id}`          | — | One broadcast |
| GET    | `/api/v1/audience/per-channel`     | — | PL/SQL aggregates per channel |
| GET    | `/api/v1/audience/top-programs`    | — | Top N programs by airtime (PL/SQL) |
| POST   | `/api/v1/broadcasts`               | Sanctum | Create broadcast |
| PATCH  | `/api/v1/broadcasts/{id}`          | Sanctum | Update broadcast |
| DELETE | `/api/v1/broadcasts/{id}`          | Sanctum | Delete broadcast |

---

## Stack

- **Laravel 11**, PHP 8.3
- **Oracle XE 21c** via [`yajra/laravel-oci8`](https://github.com/yajra/laravel-oci8)
- **Eloquent** with relations, scopes, Form Requests, API Resources
- **Sanctum** token auth on writes
- **Pest 3** (Feature + Unit)
- **GitHub Actions** with two jobs: SQLite (fast) + Oracle XE service (integration)
- **Docker Compose**: Oracle + PHP-FPM + nginx

---

## Run locally

```bash
# 1) Bring the stack up
cp .env.example .env
docker compose up -d --build
docker compose logs -f oracle   # wait for "DATABASE IS READY TO USE"

# 2) Install Laravel deps inside the php container
docker compose exec php composer install
docker compose exec php cp .env.example .env
docker compose exec php php artisan key:generate

# 3) Migrate + seed
docker compose exec php php artisan migrate --seed

# 4) Hit the API
curl http://localhost:8080/api/v1/channels | jq
curl "http://localhost:8080/api/v1/programs?search=berlin" | jq
curl http://localhost:8080/api/v1/audience/per-channel | jq

# 5) Auth on writes
docker compose exec php php artisan tinker
> $u = App\Models\User::first(); echo $u->createToken('demo')->plainTextToken;
# Use the token as: Authorization: Bearer <token>
```

## Run tests

```bash
# Fast loop (SQLite, in-memory)
docker compose exec php vendor/bin/pest

# Integration (real Oracle)
docker compose exec php php artisan migrate:fresh --seed
docker compose exec php vendor/bin/pest --testsuite=Feature
```

CI runs both suites on every push (`.github/workflows/ci.yml`).

---

## Project layout

```
.
├── docker-compose.yml          # Oracle XE 21c + PHP 8.3-fpm + nginx
├── docker/
│   ├── php/Dockerfile          # PHP + Instant Client + oci8 + pdo_oci
│   ├── nginx/default.conf
│   └── oracle/init/01_schema_grants.sql
├── src/                        # Laravel app
│   ├── app/Models/             # Channel, Program, Genre, Broadcast, User
│   ├── app/Http/Controllers/Api/
│   ├── app/Http/Requests/      # Form requests w/ validation rules
│   ├── app/Http/Resources/     # API resources (consistent envelope)
│   ├── config/database.php     # Oracle connection config
│   ├── database/migrations/    # Oracle-aware schema (sequences, CLOB, FK names ≤30c)
│   ├── database/factories/
│   ├── database/seeders/
│   ├── routes/api.php
│   └── tests/{Feature,Unit}/
├── docs/
│   ├── DECISIONS.md            # Architectural choices + Oracle gotchas
│   └── PL_SQL_SAMPLES.sql      # audience_stats_pkg package
└── .github/workflows/ci.yml
```

---

## Why this POC matters for the ARTE mission

The ARTE technical brief lists, among others:

- **Laravel** (back-end framework)
- **Oracle / PL-SQL** (RDBMS, ability to work without an ORM)
- **JS/TS/React** (front-end, covered by other deliverables)
- **REST APIs**, **Git/GitHub Actions**, **tests**

This repo is a focused proof for the back-end half:

1. A **runnable Laravel 11 API** wired to **Oracle XE 21c** through `oci8`.
2. **Oracle-aware migrations** (sequences, `VARCHAR2`, identifier limit, `CLOB` for synopsis, composite indexes).
3. A **PL/SQL package** (`audience_stats_pkg`) called from a controller via raw `DB::select()`, demonstrating native SQL/PL-SQL skills alongside Eloquent.
4. **CI** that exercises both a fast SQLite loop and a real Oracle service.

See [`docs/DECISIONS.md`](docs/DECISIONS.md) for the design rationale.
