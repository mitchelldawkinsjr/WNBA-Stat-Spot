# WNBA Stat Spot - Container Setup Guide

This guide explains how to set up and run the WNBA analytics system using Docker containers.

## Quick Start

### 1. Start the Containers
```bash
docker-compose up -d
```

### 2. Initialize the Application
```bash
./init-wnba-data.sh
```

This script will:
- ✅ Check that containers are running
- ⏳ Wait for database connection
- 🗄️ Create database tables (migrations)
- 📊 Import all WNBA data (teams, games, players, statistics)
- 🎉 Confirm the application is ready

### 3. Access the Application
- **Main Application**: http://localhost
- **Analytics Dashboard**: http://localhost/reports
- **Advanced Analytics**: http://localhost/reports/analytics
- **Predictions Engine**: http://localhost/reports/predictions

## Manual Commands

### Import Data Manually (includes table creation)
```bash
docker exec wnba-stat-spot-laravel.test-1 php artisan app:import-wnba-data
```

### Run Migrations Only
```bash
docker exec wnba-stat-spot-laravel.test-1 php artisan migrate
```

### Access Container Shell
```bash
docker exec -it wnba-stat-spot-laravel.test-1 bash
```

### View Logs
```bash
docker-compose logs -f laravel.test
```

## Data Import Process

The import command now handles everything in one step:

**Step 0**: 🗄️ Database table creation (migrations)
**Step 1**: 📊 Team data (information, logos, colors)
**Step 2**: 📅 Game schedule (all games with venue information)
**Step 3**: 🏀 Play-by-play data (detailed game events)
**Step 4**: 🏀 Player box score data (player statistics and game performance)

### Import Summary
After import, you'll see statistics like:
- 🏀 Teams imported: 15
- 👥 Players imported: 154
- 🎮 Games imported: 287
- 📊 Player game stats: 491

## Analytics Features

### Available Analytics
- **Player Performance Analysis**
- **Team Efficiency Metrics**
- **Game Predictions**
- **Props Betting Recommendations**
- **Advanced Statistical Models**

### Prediction Models
- Bayesian Inference
- Monte Carlo Simulations
- Poisson Distributions
- Regression Analysis
- WNBA-specific adjustments (40-minute games, pace, etc.)

## Troubleshooting

### Container Issues
```bash
# Restart containers
docker-compose down
docker-compose up -d

# Rebuild containers
docker-compose build --no-cache
docker-compose up -d
```

### Database Issues
```bash
# Reset database and reimport everything
docker exec wnba-stat-spot-laravel.test-1 php artisan migrate:fresh
docker exec wnba-stat-spot-laravel.test-1 php artisan app:import-wnba-data
```

### Import Issues
```bash
# Force reimport (overwrites existing data)
docker exec wnba-stat-spot-laravel.test-1 php artisan app:import-wnba-data --force
```

## Development

### Frontend Development
The Svelte frontend runs separately:
```bash
cd resources/js
npm run dev
```

### API Endpoints
- `GET /api/wnba/players` - List all players
- `GET /api/wnba/teams` - List all teams
- `GET /api/wnba/analytics/player/{id}` - Player analytics
- `GET /api/wnba/predictions/props` - Props predictions

## Environment Variables

Key environment variables in `.env`:
```
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password

CACHE_DRIVER=redis
REDIS_HOST=redis
```

## Services

### Laravel Application
- **Port**: 80
- **Container**: `wnba-stat-spot-laravel.test-1`
- **Features**: API, Analytics, Predictions

### MySQL Database
- **Port**: 3306
- **Container**: `wnba-stat-spot-mysql-1`
- **Data**: Persistent volume

### Redis Cache
- **Port**: 6379
- **Container**: `wnba-stat-spot-redis-1`
- **Purpose**: Caching analytics results

### Frontend (Svelte)
- **Port**: 5173
- **Container**: `wnba-stat-spot-frontend-1`
- **Purpose**: Modern SPA interface

## Recent Optimizations

### Container Structure
- Streamlined Docker configurations
- Optimized container dependencies
- Improved build caching

### Performance
- Added Redis caching layer
- Optimized database queries
- Improved data import process

### Development Experience
- Enhanced development container setup
- Improved hot-reloading
- Better error reporting

## Next Steps

1. **Start Containers**: `docker-compose up -d`
2. **Initialize Everything**: `./init-wnba-data.sh` (creates tables + imports data)
3. **Access Analytics**: Visit http://localhost/reports
4. **Explore Predictions**: Visit http://localhost/reports/predictions
5. **View Player Analytics**: Click analytics buttons on player pages

The system is now ready for comprehensive WNBA analytics and predictions! 
