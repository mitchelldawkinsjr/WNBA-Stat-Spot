<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WNBA Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration options for the WNBA analytics and
    | prediction services. Adjust these values based on your requirements.
    |
    */

    'cache' => [
        'enabled' => env('WNBA_CACHE_ENABLED', true),
        'default_ttl' => env('WNBA_CACHE_TTL', 3600), // 1 hour
        'player_stats_ttl' => env('WNBA_PLAYER_STATS_TTL', 1800), // 30 minutes
        'team_stats_ttl' => env('WNBA_TEAM_STATS_TTL', 1800), // 30 minutes
        'game_data_ttl' => env('WNBA_GAME_DATA_TTL', 3600), // 1 hour
        'predictions_ttl' => env('WNBA_PREDICTIONS_TTL', 900), // 15 minutes
        'league_data_ttl' => env('WNBA_LEAGUE_DATA_TTL', 7200), // 2 hours
        'historical_data_ttl' => env('WNBA_HISTORICAL_DATA_TTL', 86400), // 24 hours
    ],

    'predictions' => [
        'min_games_required' => env('WNBA_MIN_GAMES_REQUIRED', 5),
        'confidence_threshold' => env('WNBA_CONFIDENCE_THRESHOLD', 0.7),
        'default_simulation_runs' => env('WNBA_SIMULATION_RUNS', 10000),
        'max_simulation_runs' => env('WNBA_MAX_SIMULATION_RUNS', 100000),

        'weights' => [
            'recent_form' => env('WNBA_WEIGHT_RECENT_FORM', 0.4),
            'season_average' => env('WNBA_WEIGHT_SEASON_AVERAGE', 0.3),
            'opponent_adjustment' => env('WNBA_WEIGHT_OPPONENT_ADJ', 0.2),
            'situational_factors' => env('WNBA_WEIGHT_SITUATIONAL', 0.1),
        ],

        'recent_games_window' => env('WNBA_RECENT_GAMES_WINDOW', 10),
        'pace_adjustment_factor' => env('WNBA_PACE_ADJUSTMENT_FACTOR', 1.0),
        'home_court_advantage' => env('WNBA_HOME_COURT_ADVANTAGE', 0.03),
        'back_to_back_penalty' => env('WNBA_BACK_TO_BACK_PENALTY', 0.05),
    ],

    'analytics' => [
        'league_averages' => [
            'pace' => 83.5, // Possessions per 40 minutes
            'offensive_rating' => 105.0, // Points per 100 possessions
            'defensive_rating' => 105.0, // Points allowed per 100 possessions
            'effective_fg_pct' => 0.485,
            'turnover_rate' => 0.145,
            'offensive_rebound_rate' => 0.285,
            'free_throw_rate' => 0.220,
        ],

        'position_factors' => [
            'G' => ['minutes' => 28.5, 'usage_rate' => 0.20],
            'F' => ['minutes' => 26.8, 'usage_rate' => 0.18],
            'C' => ['minutes' => 24.2, 'usage_rate' => 0.16],
        ],

        'game_factors' => [
            'regulation_minutes' => 40,
            'overtime_minutes' => 5,
            'max_overtime_periods' => 4,
            'foul_limit' => 6,
            'technical_foul_shots' => 1,
        ],
    ],

    'validation' => [
        'backtest_seasons' => env('WNBA_BACKTEST_SEASONS', 2),
        'cross_validation_folds' => env('WNBA_CV_FOLDS', 5),
        'accuracy_threshold' => env('WNBA_ACCURACY_THRESHOLD', 0.55),
        'calibration_bins' => env('WNBA_CALIBRATION_BINS', 10),

        'metrics' => [
            'track_mae' => true,
            'track_rmse' => true,
            'track_directional_accuracy' => true,
            'track_calibration' => true,
            'track_hit_rate' => true,
            'track_roi' => true,
        ],
    ],

    'betting' => [
        'default_odds_format' => env('WNBA_ODDS_FORMAT', 'american'), // american, decimal, fractional
        'juice_assumption' => env('WNBA_JUICE_ASSUMPTION', 0.05), // 5% vig
        'min_edge_threshold' => env('WNBA_MIN_EDGE_THRESHOLD', 0.03), // 3% edge
        'max_bet_percentage' => env('WNBA_MAX_BET_PERCENTAGE', 0.05), // 5% of bankroll
        'kelly_fraction' => env('WNBA_KELLY_FRACTION', 0.25), // Quarter Kelly
    ],

    'data_quality' => [
        'min_sample_size' => env('WNBA_MIN_SAMPLE_SIZE', 10),
        'max_missing_data_pct' => env('WNBA_MAX_MISSING_DATA_PCT', 0.1), // 10%
        'outlier_threshold' => env('WNBA_OUTLIER_THRESHOLD', 3.0), // 3 standard deviations
        'recency_weight' => env('WNBA_RECENCY_WEIGHT', 0.8),

        'required_fields' => [
            'player_games' => ['minutes', 'points', 'rebounds', 'assists'],
            'team_games' => ['field_goals_made', 'field_goals_attempted', 'points'],
            'games' => ['game_date', 'home_team_id', 'away_team_id'],
        ],
    ],

    'performance' => [
        'batch_size' => env('WNBA_BATCH_SIZE', 1000),
        'max_concurrent_requests' => env('WNBA_MAX_CONCURRENT_REQUESTS', 10),
        'request_timeout' => env('WNBA_REQUEST_TIMEOUT', 30), // seconds
        'memory_limit' => env('WNBA_MEMORY_LIMIT', '512M'),

        'optimization' => [
            'enable_query_optimization' => true,
            'enable_result_caching' => true,
            'enable_lazy_loading' => true,
            'enable_compression' => false,
        ],
    ],

    'logging' => [
        'enabled' => env('WNBA_LOGGING_ENABLED', true),
        'level' => env('WNBA_LOG_LEVEL', 'info'),
        'channels' => [
            'predictions' => env('WNBA_LOG_PREDICTIONS', true),
            'analytics' => env('WNBA_LOG_ANALYTICS', true),
            'cache' => env('WNBA_LOG_CACHE', false),
            'performance' => env('WNBA_LOG_PERFORMANCE', true),
        ],

        'retention_days' => env('WNBA_LOG_RETENTION_DAYS', 30),
    ],

    'features' => [
        'enable_live_updates' => env('WNBA_ENABLE_LIVE_UPDATES', false),
        'enable_injury_tracking' => env('WNBA_ENABLE_INJURY_TRACKING', true),
        'enable_weather_factors' => env('WNBA_ENABLE_WEATHER_FACTORS', false),
        'enable_referee_tracking' => env('WNBA_ENABLE_REFEREE_TRACKING', false),
        'enable_travel_factors' => env('WNBA_ENABLE_TRAVEL_FACTORS', true),
        'enable_rest_tracking' => env('WNBA_ENABLE_REST_TRACKING', true),
    ],

    'api' => [
        'rate_limit' => env('WNBA_API_RATE_LIMIT', 100), // requests per minute
        'timeout' => env('WNBA_API_TIMEOUT', 30), // seconds
        'retry_attempts' => env('WNBA_API_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('WNBA_API_RETRY_DELAY', 1), // seconds

        'endpoints' => [
            'predictions' => '/api/wnba/predictions',
            'analytics' => '/api/wnba/analytics',
            'validation' => '/api/wnba/validation',
            'cache' => '/api/wnba/cache',
        ],
    ],

    'seasons' => [
        'current_season' => (int) env('WNBA_CURRENT_SEASON', 2026),
        'season_start_month' => 5, // May
        'season_end_month' => 10, // October
        'playoff_start_month' => 9, // September
        'regular_season_games' => 40,
        'playoff_format' => 'best_of_series',
    ],

    'teams' => [
        'total_teams' => 12,
        'conferences' => ['Eastern', 'Western'],
        'playoff_teams' => 8,
        'roster_size' => 12,
        'active_players' => 11,
    ],

    'advanced_metrics' => [
        'enable_player_tracking' => env('WNBA_ENABLE_PLAYER_TRACKING', false),
        'enable_shot_charts' => env('WNBA_ENABLE_SHOT_CHARTS', false),
        'enable_lineup_analysis' => env('WNBA_ENABLE_LINEUP_ANALYSIS', true),
        'enable_clutch_stats' => env('WNBA_ENABLE_CLUTCH_STATS', true),

        'clutch_time_definition' => [
            'time_remaining' => 300, // 5 minutes in seconds
            'score_margin' => 5, // points
        ],
    ],
];
