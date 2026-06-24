# WNBA Stat Spot — Container Setup

## Quick start

```bash
docker-compose up -d
./init-wnba-data.sh
```

- **App**: http://localhost
- **Reports**: http://localhost/reports
- **Predictions**: http://localhost/reports/predictions
- **Model validation**: http://localhost/advanced/model-validation

## Manual import

```bash
docker exec wnba-stat-spot-laravel.test-1 php artisan migrate
docker exec wnba-stat-spot-laravel.test-1 php artisan app:import-wnba-data
docker exec wnba-stat-spot-laravel.test-1 php artisan app:sync-entity-identities
```

Force reimport:

```bash
docker exec wnba-stat-spot-laravel.test-1 php artisan app:import-wnba-data --force
```

## Import order

1. Migrations
2. ESPN schedule + team box scores (batched)
3. Entity identity sync (ESPN ↔ Tank01 IDs)
4. Player box scores (batched)

Options: `--batch-size=10`, `--season=2025`

## Frontend dev

```bash
cd resources/js
npm install
npm run dev
```

## Useful API endpoints

- `GET /api/health` — basic health
- `GET /api/players` — player list
- `GET /api/players/{id}/gamelog` — player game log
- `GET /api/wnba/predictions/todays-best` — today's props
- `GET /api/odds/wnba/props` — live player props (Tank01)

## Troubleshooting

```bash
docker-compose down && docker-compose up -d
docker-compose logs -f laravel.test
```

Reset database:

```bash
docker exec wnba-stat-spot-laravel.test-1 php artisan migrate:fresh
docker exec wnba-stat-spot-laravel.test-1 php artisan app:import-wnba-data
```
