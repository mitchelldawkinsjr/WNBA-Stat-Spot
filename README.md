# 🏀 WNBA Stat Spot

**Your ultimate destination for WNBA statistics, advanced analytics, and predictive insights**

A comprehensive web application built with Laravel and SvelteKit that provides detailed statistics, advanced analytics, prediction engines, and betting insights for WNBA teams, players, and games.

## ✨ Features

### 📊 **Core Statistics & Data**
- **Teams Dashboard** - Complete team profiles with logos, locations, and performance data
- **Player Analytics** - Individual player statistics with season averages and game logs
- **Game Center** - Detailed game information with venues, schedules, and results
- **Performance Metrics** - Top performances and sortable statistics tables
- **Real-time Data** - Live statistics from WNBA games and seasons

### 🎯 **Advanced Analytics Engine**
- **Prediction Engine** - AI-powered player performance predictions
- **Prop Scanner** - Automated scanning for betting opportunities across all players
- **Monte Carlo Simulations** - Statistical modeling for performance forecasting
- **Historical Testing** - Backtesting prediction accuracy with real game data
- **Betting Analytics** - Expected value calculations and betting recommendations
- **Data Quality Monitoring** - Real-time data integrity and completeness tracking

### 🧠 **Predictive Intelligence**
- **Player Prop Predictions** - Points, rebounds, assists, steals, blocks predictions
- **Confidence Scoring** - Statistical confidence levels for all predictions
- **Expected Value Analysis** - Betting edge calculations and Kelly Criterion sizing
- **Situational Factors** - Home/away, back-to-back games, opponent strength adjustments
- **Recent Form Analysis** - Weighted recent performance vs season averages
- **Injury Risk Assessment** - Player availability and performance impact analysis

### 📈 **Analytics Dashboard**
- **Model Validation** - Real-time accuracy tracking and performance metrics
- **Leaderboards** - Top performing players and prediction accuracy rankings
- **Trend Analysis** - Performance patterns and statistical insights
- **Methodology Documentation** - Transparent explanation of all analytical methods

## 🛠️ Technology Stack

### Backend
- **Laravel 12.x** with PHP 8.4+
- **MySQL/PostgreSQL** with Eloquent ORM
- **Redis** for caching and session management
- **Queue Workers** for background job processing
- **Advanced Services**: Bayesian inference, Monte Carlo simulations, regression analysis

### Frontend
- **SvelteKit** with TypeScript
- **Tailwind CSS** for modern, responsive design
- **Chart.js** for data visualization
- **Real-time API** integration with caching

### Analytics & ML
- **Statistical Engine** - Custom-built prediction algorithms
- **Bayesian Calculator** - Probabilistic inference for predictions
- **Monte Carlo Simulator** - Performance distribution modeling
- **Regression Analyzer** - Trend analysis and pattern recognition
- **Data Aggregator** - Multi-source data integration and processing

### Infrastructure
- **Docker** containerization for development and production
- **Nginx** web server with PHP-FPM
- **Supervisor** for process management
- **Production-ready** deployment configurations

## 🚀 Quick Start

### Prerequisites
- Docker and Docker Compose
- Git

### Development Setup

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd wnba-stat-spot
   ```

2. **Start the application**
   ```bash
   docker-compose up -d
   ```

3. **Import WNBA data**
   ```bash
   docker-compose exec laravel.test php artisan app:import-wnba-data
   ```

4. **Start queue workers for analytics**
   ```bash
   docker-compose exec laravel.test php artisan queue:work
   ```

5. **Access the application**
   - Frontend: http://localhost:5173
   - Backend API: http://localhost:80/api

### Production Deployment

The application is ready for deployment on multiple platforms:

- **Render.com** (Recommended) - Free tier with 750 hours/month
- **Railway** - $5/month credits, excellent Docker support
- **Fly.io** - Generous free tier with global edge deployment
- **Vercel + Backend** - Split architecture for maximum free resources

See `DEPLOYMENT_STEPS.md` for detailed deployment instructions.

## 📱 Application Architecture

### API Endpoints

#### Core Data
- `GET /api/teams` - All WNBA teams with statistics
- `GET /api/players` - All players with advanced filtering
- `GET /api/players/{id}` - Individual player details and analytics
- `GET /api/games` - Game schedules and results
- `GET /api/stats` - Performance statistics and leaderboards

#### Analytics & Predictions
- `POST /api/wnba/predictions/generate` - Generate player predictions
- `GET /api/wnba/prop-scanner/scan-all` - Scan all players for betting opportunities
- `POST /api/wnba/monte-carlo/run` - Run Monte Carlo simulations
- `GET /api/wnba/analytics/player/{id}` - Advanced player analytics
- `POST /api/wnba/testing/historical/start` - Start historical testing

#### Data Quality & Validation
- `GET /api/wnba/validation` - Model validation metrics
- `GET /api/wnba/data-quality/metrics` - Data quality monitoring
- `GET /api/wnba/betting/analytics` - Betting performance analytics

### Frontend Routes

#### Core Pages
- `/` - Dashboard with key metrics and navigation
- `/teams` - Teams overview with analytics
- `/players` - Players listing with advanced search
- `/players/{id}` - Individual player profile with prediction engine
- `/games` - Games schedule and results
- `/stats` - Statistics leaderboards

#### Analytics & Tools
- `/advanced/prop-scanner` - Automated prop betting scanner
- `/advanced/monte-carlo` - Monte Carlo simulation interface
- `/advanced/prediction-testing` - Historical testing dashboard
- `/advanced/betting-analytics` - Betting performance tracking
- `/advanced/data-quality` - Data quality monitoring

#### Reports & Documentation
- `/reports/players` - Comprehensive player reports
- `/reports/teams` - Team analytics reports
- `/reports/analytics` - Model validation reports
- `/methodology` - Analytical methodology documentation

## 🎯 Key Features Deep Dive

### Prediction Engine
The prediction engine uses advanced statistical methods to forecast player performance:

- **Bayesian Inference** - Updates predictions based on new data
- **Weighted Averages** - Recent form vs season performance
- **Opponent Adjustments** - Defensive strength impact
- **Situational Factors** - Home/away, rest days, back-to-back games
- **Confidence Intervals** - Statistical uncertainty quantification

### Prop Scanner
Automated scanning system that:

- **Analyzes All Players** - Scans entire WNBA roster for opportunities
- **Calculates Expected Value** - Identifies positive EV betting opportunities
- **Risk Assessment** - Evaluates betting confidence and Kelly sizing
- **Real-time Updates** - Continuous monitoring and alerts

### Historical Testing
Comprehensive backtesting system:

- **Accuracy Tracking** - Measures prediction performance over time
- **Player Rankings** - Identifies most predictable players
- **Statistical Analysis** - Confidence intervals and volatility metrics
- **Methodology Validation** - Ensures robust analytical approaches

### Data Quality Monitoring
Real-time data integrity system:

- **Completeness Tracking** - Monitors missing data points
- **Outlier Detection** - Identifies statistical anomalies
- **Source Validation** - Ensures data accuracy and consistency
- **Quality Scoring** - Provides confidence metrics for all data

## 🔧 Development

### Backend Development
```bash
# Run Laravel commands
docker-compose exec laravel.test php artisan <command>

# Run analytics tests
docker-compose exec laravel.test php artisan test --filter=Analytics

# Start queue workers
docker-compose exec laravel.test php artisan queue:work

# Clear analytics cache
docker-compose exec laravel.test php artisan wnba:cache:clear
```

### Frontend Development
```bash
# Frontend development server
cd resources/js && npm run dev

# Build for production
cd resources/js && npm run build

# Type checking
cd resources/js && npm run check
```

### Analytics Development
```bash
# Test prediction accuracy
docker-compose exec laravel.test php artisan wnba:test:player-accuracy {player_id}

# Run Monte Carlo simulation
docker-compose exec laravel.test php artisan wnba:monte-carlo {player_id} {stat_type}

# Validate models
docker-compose exec laravel.test php artisan wnba:validate:models
```

## 📊 Analytics Capabilities

### Statistical Methods
- **Bayesian Inference** - Probabilistic prediction updates
- **Monte Carlo Simulation** - Performance distribution modeling
- **Regression Analysis** - Trend identification and forecasting
- **Poisson Distribution** - Event probability calculations
- **Ensemble Modeling** - Multiple model combination for accuracy

### Data Processing
- **Real-time Aggregation** - Live data processing and caching
- **Multi-source Integration** - Combines multiple data sources
- **Quality Assurance** - Automated data validation and cleaning
- **Performance Optimization** - Efficient algorithms for large datasets

### Prediction Accuracy
- **Historical Validation** - Backtested against real game outcomes
- **Confidence Scoring** - Statistical uncertainty quantification
- **Continuous Learning** - Models improve with new data

## 🔄 Recent Optimizations

### Phase 1: Cleanup
- Removed unused files and artifacts
- Consolidated Docker configurations
- Streamlined deployment files

### Phase 2: Code Refactoring
- Centralized prediction logic
- Improved code organization
- Enhanced type safety

### Phase 3: Performance
- Optimized CSV imports
- Implemented caching
- Added database indexes

### Phase 4: Structure
- Standardized naming conventions
- Improved code organization
- Enhanced documentation

### Phase 5: Frontend
- Modern UI with Tailwind CSS
- Responsive chart components
- Improved state management

### Phase 6: Testing
- Comprehensive unit tests
- Integration test coverage
- Frontend component tests

## 🎨 UI/UX Features

- **Modern Analytics Dashboard** - Professional interface with real-time data
- **Interactive Charts** - Dynamic visualizations with Chart.js
- **Responsive Design** - Optimized for desktop, tablet, and mobile
- **Real-time Updates** - Live data refresh and notifications
- **Advanced Filtering** - Powerful search and filter capabilities
- **Loading States** - Smooth loading indicators and error handling
- **Professional Styling** - Consistent design with WNBA branding

## 🚀 Production Deployment

### Supported Platforms
- **Render.com** - Recommended for Docker deployments
- **Railway** - Excellent for containerized applications
- **Fly.io** - Global edge deployment
- **AWS/GCP/Azure** - Enterprise-grade hosting
- **Vercel + Backend** - Split architecture deployment

### Environment Configuration
```env
# Core Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_DATABASE=wnba_stat_spot

# Analytics Configuration
WNBA_CACHE_ENABLED=true
WNBA_PREDICTIONS_TTL=900
WNBA_SIMULATION_RUNS=10000
WNBA_CONFIDENCE_THRESHOLD=0.7
```

## 📈 Performance & Scaling

### Caching Strategy
- **Redis** for session and application caching
- **Database** query result caching
- **API** response caching with TTL
- **Prediction** result caching for performance

### Background Processing
- **Queue Workers** for heavy analytics computations
- **Batch Processing** for historical data analysis
- **Scheduled Tasks** for data updates and maintenance
- **Monitoring** for job success and failure tracking

## 📄 Documentation

- `DEPLOYMENT_STEPS.md` - Step-by-step deployment guide
- `README_WNBA_ANALYTICS.md` - Detailed analytics documentation
- `INTEGRATION_CHECKLIST.md` - Development integration guide
- `/methodology` - In-app methodology documentation

## 📄 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

### Development Guidelines
- Follow PSR-12 coding standards for PHP
- Use TypeScript for all frontend code
- Write tests for new analytics features
- Document new prediction methods
- Maintain API backward compatibility

---

**Built with ❤️ for WNBA fans, basketball analytics enthusiasts, and data scientists**

*Empowering smarter basketball analysis through advanced statistics and predictive modeling*
