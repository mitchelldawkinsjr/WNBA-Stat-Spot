# WNBA Analytics & Prediction Service

A comprehensive Laravel-based analytics and prediction system for WNBA player props and game analysis. This system provides mathematically rigorous predictions using Bayesian inference, Monte Carlo simulations, and advanced statistical modeling.

## 🏀 Features

- **Player Analytics**: Comprehensive player performance analysis with advanced metrics
- **Team Analytics**: Team-level statistics, pace analysis, and efficiency metrics
- **Game Analytics**: Pre-game analysis, matchup predictions, and live game insights
- **Prop Predictions**: Statistical predictions for player props (points, rebounds, assists, etc.)
- **Betting Recommendations**: Expected value calculations and betting insights
- **Model Validation**: Backtesting, accuracy metrics, and performance analysis
- **Intelligent Caching**: Multi-level caching with automatic invalidation
- **WNBA-Specific**: Tailored for 40-minute games, pace variations, and league dynamics

## 📁 File Structure

```
app/Services/WNBA/
├── Analytics/
│   ├── PlayerAnalyticsService.php    # Player performance analytics
│   ├── TeamAnalyticsService.php      # Team-level analytics
│   └── GameAnalyticsService.php      # Game analysis and predictions
├── Predictions/
│   ├── PropsPredictionService.php    # Main prediction orchestrator
│   ├── StatisticalEngineService.php  # Core statistical calculations
│   ├── MonteCarloSimulator.php       # Monte Carlo simulation engine
│   ├── DistributionAnalyzer.php      # Statistical distribution analysis
│   └── ModelValidationService.php    # Model validation and backtesting
├── Math/
│   ├── BayesianCalculator.php        # Bayesian inference calculations
│   ├── PoissonCalculator.php         # Poisson distribution analysis
│   └── RegressionAnalyzer.php        # Regression analysis tools
└── Data/
    ├── DataAggregatorService.php     # Data collection and processing
    └── CacheManagerService.php       # Intelligent caching system
```

## 🚀 Installation

### 1. Register the Service Provider

Add the WNBA service provider to your `config/app.php`:

```php
'providers' => [
    // ... other providers
    App\Providers\WnbaServiceProvider::class,
],
```

### 2. Publish Configuration

```bash
php artisan vendor:publish --tag=wnba-config
```

### 3. Configure Environment Variables

Add these variables to your `.env` file:

```env
# Cache Settings
WNBA_CACHE_ENABLED=true
WNBA_CACHE_TTL=3600
WNBA_PREDICTIONS_TTL=900

# Prediction Settings
WNBA_MIN_GAMES_REQUIRED=5
WNBA_CONFIDENCE_THRESHOLD=0.7
WNBA_SIMULATION_RUNS=10000

# Weights for prediction factors
WNBA_WEIGHT_RECENT_FORM=0.4
WNBA_WEIGHT_SEASON_AVERAGE=0.3
WNBA_WEIGHT_OPPONENT_ADJ=0.2
WNBA_WEIGHT_SITUATIONAL=0.1

# Betting Settings
WNBA_MIN_EDGE_THRESHOLD=0.03
WNBA_KELLY_FRACTION=0.25

# Current Season
WNBA_CURRENT_SEASON=2024
```

### 4. Add Routes

Add these routes to your `routes/api.php`:

```php
Route::prefix('wnba')->group(function () {
    // Predictions
    Route::post('/predictions/props', [WnbaPredictionsController::class, 'getPlayerPropPredictions']);
    Route::post('/predictions/betting', [WnbaPredictionsController::class, 'getBettingRecommendations']);
    
    // Analytics
    Route::get('/analytics/player/{playerId}', [WnbaPredictionsController::class, 'getPlayerAnalytics']);
    Route::get('/analytics/team/{teamId}', [WnbaPredictionsController::class, 'getTeamAnalytics']);
    Route::get('/analytics/game/{gameId}', [WnbaPredictionsController::class, 'getGameAnalytics']);
    
    // Validation
    Route::get('/validation', [WnbaPredictionsController::class, 'getModelValidation']);
    
    // Cache Management
    Route::get('/cache/stats', [WnbaPredictionsController::class, 'getCacheStats']);
    Route::post('/cache/clear', [WnbaPredictionsController::class, 'clearCache']);
    Route::post('/cache/warm', [WnbaPredictionsController::class, 'warmCache']);
});
```

## 📊 Usage Examples

### Basic Player Prop Prediction

```php
use App\Services\WNBA\Predictions\PropsPredictionService;

// Inject the service
$predictionService = app(PropsPredictionService::class);

// Predict points for a player
$prediction = $predictionService->predict(
    playerId: 12345,
    gameId: 67890,
    statType: 'points',
    lineValue: 15.5
);

// Result structure:
[
    'expected_value' => 18.5,
    'confidence_interval' => [15.2, 21.8],
    'distribution' => [...],
    'over_under_probabilities' => [
        '15.5' => ['over' => 0.75, 'under' => 0.25],
        '18.5' => ['over' => 0.52, 'under' => 0.48],
    ],
    'confidence_score' => 0.82,
    'factors' => [...]
]
```

### Player Analytics

```php
use App\Services\WNBA\Analytics\PlayerAnalyticsService;

$playerAnalytics = app(PlayerAnalyticsService::class);

// Get comprehensive player analytics
$analytics = $playerAnalytics->getSeasonAverages(12345, 2024);
$recentForm = $playerAnalytics->getRecentForm(12345, 10);
$advanced = $playerAnalytics->getAdvancedMetrics(12345, 2024);
```

### Betting Recommendations

```php
// Get betting recommendation for a specific prop
$recommendation = $predictionService->getBettingRecommendation(
    playerId: 12345,
    statType: 'points',
    line: 18.5,
    oddsOver: -110,
    oddsUnder: -110,
    gameId: 67890
);

// Result:
[
    'recommendation' => 'OVER',
    'confidence' => 'HIGH',
    'expected_value' => 0.045,
    'kelly_bet_size' => 0.023,
    'reasoning' => [...]
]
```

### Team Analytics

```php
use App\Services\WNBA\Analytics\TeamAnalyticsService;

$teamAnalytics = app(TeamAnalyticsService::class);

$paceAnalysis = $teamAnalytics->getPaceAnalysis(1, 2024);
$offensiveMetrics = $teamAnalytics->getOffensiveMetrics(1, 2024);
$defensiveMetrics = $teamAnalytics->getDefensiveMetrics(1, 2024);
```

## 🔧 API Endpoints

### Player Prop Predictions

**POST** `/api/wnba/predictions/props`

```json
{
    "player_id": 12345,
    "stat_type": "points",
    "game_id": 67890,
    "season": 2024,
    "simulation_runs": 10000
}
```

**Response:**
```json
{
    "success": true,
    "data": {
        "player_id": 12345,
        "stat_type": "points",
        "prediction": {
            "expected_value": 18.5,
            "confidence_interval": [15.2, 21.8],
            "over_under_probabilities": {...},
            "confidence_score": 0.82
        },
        "generated_at": "2024-01-15T10:30:00Z"
    }
}
```

### Betting Recommendations

**POST** `/api/wnba/predictions/betting`

```json
{
    "player_id": 12345,
    "stat_type": "points",
    "line": 18.5,
    "odds_over": -110,
    "odds_under": -110,
    "game_id": 67890
}
```

### Player Analytics

**GET** `/api/wnba/analytics/player/{playerId}?season=2024&last_n_games=10`

### Team Analytics

**GET** `/api/wnba/analytics/team/{teamId}?season=2024`

### Game Analytics

**GET** `/api/wnba/analytics/game/{gameId}`

## 🔄 Recent Optimizations

### Code Structure
- Centralized prediction logic in dedicated services
- Improved type safety with TypeScript interfaces
- Enhanced code organization and documentation

### Performance
- Implemented intelligent caching for predictions
- Optimized Monte Carlo simulations
- Added database indexes for common queries

### Testing
- Comprehensive unit tests for prediction services
- Integration tests for API endpoints
- Frontend component tests with Vitest

### Frontend
- Modern UI with Tailwind CSS
- Responsive chart components
- Improved state management with Svelte stores

## 🧮 Mathematical Models

### Bayesian Inference
- **Beta-Binomial**: For shooting percentages and binary outcomes
- **Gamma-Poisson**: For counting statistics (points, rebounds, assists)
- **Normal-Normal**: For continuous metrics with known variance

### Monte Carlo Simulation
- **10,000+ simulations** for robust probability estimates
- **Multiple distributions**: Normal, Poisson, Gamma, Beta, Log-normal
- **Correlation modeling**: Between related statistics

### Statistical Distributions
- **Poisson**: For rare events (steals, blocks)
- **Normal**: For high-volume statistics (points, minutes)
- **Binomial**: For shooting attempts and makes
- **Gamma**: For positive continuous variables

## 🎯 WNBA-Specific Features

### Game Structure
- **40-minute regulation** games (vs 48 in NBA)
- **5-minute overtime** periods
- **Pace adjustments** for shorter games

### League Dynamics
- **12 teams** with unique scheduling patterns
- **40 regular season games** per team
- **Playoff format** considerations

### Player Factors
- **Position-specific** usage rates and minute distributions
- **Foul trouble** impact (6-foul limit)
- **Rest and travel** effects

## 📈 Performance Optimization

### Caching Strategy
- **Multi-level caching** with intelligent TTL
- **Automatic invalidation** on data updates
- **Cache warming** for upcoming games
- **Redis support** with tagging

### Query Optimization
- **Eager loading** of relationships
- **Batch processing** for large datasets
- **Index optimization** suggestions
- **Memory management** for large simulations

## 🔍 Model Validation

### Accuracy Metrics
- **Mean Absolute Error (MAE)**
- **Root Mean Square Error (RMSE)**
- **Directional accuracy**
- **Hit rate analysis**

### Calibration Testing
- **Reliability diagrams**
- **Brier score decomposition**
- **Confidence interval coverage**

### Backtesting
- **Historical performance** analysis
- **Cross-validation** with time series splits
- **Out-of-sample** testing

## 🛠️ Configuration Options

### Prediction Weights
```php
'weights' => [
    'recent_form' => 0.4,        // Recent 10 games
    'season_average' => 0.3,     // Season-long performance
    'opponent_adjustment' => 0.2, // Matchup factors
    'situational_factors' => 0.1, // Home/away, rest, etc.
]
```

### Cache TTL Settings
```php
'cache' => [
    'player_stats_ttl' => 1800,    // 30 minutes
    'team_stats_ttl' => 1800,      // 30 minutes
    'predictions_ttl' => 900,      // 15 minutes
    'league_data_ttl' => 7200,     // 2 hours
]
```

### Betting Parameters
```php
'betting' => [
    'min_edge_threshold' => 0.03,   // 3% minimum edge
    'kelly_fraction' => 0.25,       // Quarter Kelly sizing
    'max_bet_percentage' => 0.05,   // 5% max of bankroll
]
```

## 🚨 Error Handling

The system includes comprehensive error handling:

- **Graceful degradation** when cache fails
- **Fallback calculations** for missing data
- **Input validation** with detailed error messages
- **Logging** of all errors and performance metrics

## 📝 Logging

Configurable logging for:
- **Prediction requests** and results
- **Cache operations** and performance
- **Model validation** results
- **Error tracking** and debugging

## 🔒 Security Considerations

- **Input validation** on all endpoints
- **Rate limiting** for API requests
- **Authentication** middleware support
- **Data sanitization** for user inputs

## 🧪 Testing

### Unit Tests
```bash
php artisan test --filter=WnbaTest
```

### Integration Tests
```bash
php artisan test --filter=WnbaIntegrationTest
```

### Performance Tests
```bash
php artisan wnba:benchmark
```

## 📚 Advanced Usage

### Custom Prediction Models
```php
// Extend the base prediction service
class CustomPropsPredictionService extends PropsPredictionService
{
    protected function calculateCustomFactors($playerId, $gameId): array
    {
        // Your custom logic here
    }
}
```

### Custom Analytics
```php
// Add custom analytics methods
$playerAnalytics->addCustomMetric('clutch_performance', function($games) {
    // Calculate clutch time performance
});
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## 📄 License

This WNBA Analytics system is proprietary software. All rights reserved.

## 🆘 Support

For support and questions:
- Create an issue in the repository
- Check the documentation
- Review the configuration options

---

**Built with ❤️ for WNBA analytics and sports betting enthusiasts** 
