# WNBA Stat Spot

Laravel + SvelteKit dashboard for WNBA stats, prop predictions, and live odds.

## Stack

- **Backend**: Laravel 12, PHP 8.2+, MySQL/PostgreSQL, Redis
- **Frontend**: SvelteKit, TypeScript, Bootstrap 5, Chart.js
- **Data**: ESPN (bulk import), Tank01 (live sync + odds), SportsDataverse/SportsBlaze fallbacks

## Quick start (Docker)

```bash
docker-compose up -d
./init-wnba-data.sh   # migrations + data import
```

- App: http://localhost
- Frontend dev server: `cd resources/js && npm run dev` → http://localhost:5173
- API: http://localhost/api

## Key commands

```bash
# Import WNBA data (batched, memory-safe)
php artisan app:import-wnba-data

# Sync ESPN ↔ Tank01 player/team/game IDs
php artisan app:sync-entity-identities

# Live box score sync (Tank01)
php artisan app:sync-wnba-live

# Tests
php artisan test
```

## API overview

| Area | Endpoints |
|------|-----------|
| Core data | `GET /api/teams`, `/players`, `/games`, `/stats` |
| Player gamelog | `GET /api/players/{id}/gamelog` |
| Predictions | `POST /api/wnba/predictions/generate`, `GET .../prop-bets`, `.../todays-best` |
| Prop scanner | `GET /api/wnba/prop-scanner/scan-all` |
| Odds (Tank01) | `GET /api/odds/wnba/props`, `/odds/live`, `/odds/usage` |
| Data admin | `POST /api/wnba/data/import`, `.../sync-identities` |
| Health | `GET /api/health`, `/health/detailed`, `/health/database`, `/health/cache` |

## Frontend routes

- `/` — dashboard
- `/teams`, `/players`, `/games`, `/stats` — core data
- `/reports/predictions`, `/reports/todays-props` — predictions
- `/advanced/prop-scanner`, `/advanced/live-odds` — betting tools
- `/advanced/model-validation`, `/advanced/prediction-testing` — model tools
- `/methodology` — docs

## Environment

Copy `.env.example` to `.env`. Important variables:

- `WNBA_DATA_PROVIDER` — `espn` (default bulk import) or `tank01`
- `ODDS_PROVIDER` — `tank01` (default)
- `RAPIDAPI_KEY` — Tank01 on RapidAPI
- `WNBA_IMPORT_MEMORY_LIMIT` — default `512M`
- `WNBA_IMPORT_GAME_BATCH_SIZE` — default `10`

See `README_CONTAINER_SETUP.md` for Docker details and `config/wnba.php` for provider settings.

## Production

SSH to the VPS: `ssh vps`

After deploy, run migrations and import:

```bash
php artisan migrate --force
php artisan app:import-wnba-data
php artisan app:sync-entity-identities
```
