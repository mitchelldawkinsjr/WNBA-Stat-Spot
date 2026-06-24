<?php

return [
    /*
    |--------------------------------------------------------------------------
    | The Odds API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for The Odds API integration
    | Get your API key from: https://the-odds-api.com/
    |
    */

    'provider' => env('ODDS_PROVIDER', 'tank01'),

    'api_key' => env('ODDS_API_KEY', null),
    'base_url' => env('ODDS_API_BASE_URL', 'https://api.the-odds-api.com/v4'),

    /*
    |--------------------------------------------------------------------------
    | API Settings
    |--------------------------------------------------------------------------
    */

    'timeout' => env('ODDS_API_TIMEOUT', 30),
    'retry_attempts' => env('ODDS_API_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('ODDS_API_RETRY_DELAY', 1000), // milliseconds

    /*
    |--------------------------------------------------------------------------
    | Cache Settings (Aggressive for Free Tier - 500 requests/month)
    |--------------------------------------------------------------------------
    */

    'cache_ttl' => [
        // Very aggressive caching to stay under 500 requests/month (~16/day)
        'odds' => env('ODDS_CACHE_TTL', 3600), // 1 hour (was 5 minutes)
        'props' => env('PROPS_CACHE_TTL', 7200), // 2 hours (was 3 minutes)
        'events' => env('EVENTS_CACHE_TTL', 14400), // 4 hours (was 10 minutes)
        'sports' => env('SPORTS_CACHE_TTL', 86400), // 24 hours (unchanged)
        'live_odds' => env('LIVE_ODDS_CACHE_TTL', 1800), // 30 minutes for "live" data
        'usage_stats' => env('USAGE_STATS_CACHE_TTL', 3600), // 1 hour
    ],

    'cache' => [
        // Legacy key aliases used by OddsController cache helpers
        'odds_ttl' => env('ODDS_CACHE_TTL', 3600), // 1 hour
        'props_ttl' => env('PROPS_CACHE_TTL', 7200), // 2 hours
        'events_ttl' => env('EVENTS_CACHE_TTL', 14400), // 4 hours
        'sports_ttl' => env('SPORTS_CACHE_TTL', 86400), // 24 hours
        'live_odds_ttl' => env('LIVE_ODDS_CACHE_TTL', 1800), // 30 minutes
        'usage_stats_ttl' => env('USAGE_STATS_CACHE_TTL', 3600), // 1 hour

        // Cache keys for easy management
        'prefix' => 'odds_api_',
        'tags' => [
            'odds' => 'odds_data',
            'props' => 'player_props',
            'events' => 'events_data',
            'sports' => 'sports_data'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting & Usage Tracking
    |--------------------------------------------------------------------------
    */

    'rate_limit' => [
        'requests_per_month' => env('ODDS_API_MONTHLY_LIMIT', 500), // Free tier limit
        'daily_target' => env('ODDS_API_DAILY_TARGET', 12), // Conservative daily target
        'burst_limit' => env('ODDS_API_BURST_LIMIT', 3), // Max requests in quick succession
        'cooldown_period' => env('ODDS_API_COOLDOWN', 300), // 5 minutes between bursts

        // Usage tracking
        'track_usage' => true,
        'warn_threshold' => 0.8, // Warn at 80% of monthly limit
        'block_threshold' => 0.95, // Block at 95% of monthly limit
    ],

    /*
    |--------------------------------------------------------------------------
    | Sport Configuration
    |--------------------------------------------------------------------------
    */

    'sports' => [
        'wnba' => [
            'key' => 'basketball_wnba',
            'title' => 'WNBA',
            'group' => 'Basketball',
            'active' => true,
        ],
        'nba' => [
            'key' => 'basketball_nba',
            'name' => 'NBA',
            'active' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Bookmaker Configuration
    |--------------------------------------------------------------------------
    */

    'bookmakers' => [
        'preferred' => [
            'draftkings',
            'fanduel',
            'betmgm',
            'caesars',
            'pointsbet_us',
            'unibet_us',
        ],
        'all_us' => [
            'draftkings',
            'fanduel',
            'betmgm',
            'caesars',
            'pointsbet_us',
            'unibet_us',
            'bovada',
            'mybookieag',
            'betonlineag',
            'lowvig',
        ],
        'names' => [
            'draftkings' => 'DraftKings',
            'fanduel' => 'FanDuel',
            'betmgm' => 'BetMGM',
            'caesars' => 'Caesars',
            'pointsbet_us' => 'PointsBet',
            'unibet_us' => 'Unibet',
            'betrivers' => 'BetRivers',
            'wynnbet' => 'WynnBET',
            'superbook' => 'SuperBook',
            'barstool' => 'Barstool Sportsbook',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Market Configuration
    |--------------------------------------------------------------------------
    */

    'markets' => [
        'h2h' => [
            'key' => 'h2h',
            'name' => 'Head to Head (Moneyline)',
            'active' => true,
        ],
        'spreads' => [
            'key' => 'spreads',
            'name' => 'Point Spreads',
            'active' => true,
        ],
        'totals' => [
            'key' => 'totals',
            'name' => 'Totals (Over/Under)',
            'active' => true,
        ],
        'player_props' => [
            'key' => 'player_points',
            'name' => 'Player Points',
            'active' => true,
            'stat_mapping' => 'points',
        ],
        'player_rebounds' => [
            'key' => 'player_rebounds',
            'name' => 'Player Rebounds',
            'active' => true,
            'stat_mapping' => 'rebounds',
        ],
        'player_assists' => [
            'key' => 'player_assists',
            'name' => 'Player Assists',
            'active' => true,
            'stat_mapping' => 'assists',
        ],
        'player_threes' => [
            'key' => 'player_threes',
            'name' => 'Player 3-Pointers Made',
            'active' => true,
            'stat_mapping' => 'three_point_field_goals_made',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Regional Settings
    |--------------------------------------------------------------------------
    */

    'regions' => [
        'default' => 'us',
        'available' => ['us', 'uk', 'eu', 'au'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Odds Format
    |--------------------------------------------------------------------------
    */

    'odds_format' => env('ODDS_API_FORMAT', 'american'), // american, decimal, fractional

    /*
    |--------------------------------------------------------------------------
    | Player Name Mapping
    |--------------------------------------------------------------------------
    | Map WNBA player names to The Odds API format
    */

    'player_name_mappings' => [
        // Common name variations that might appear in odds
        "A'ja Wilson" => ["A'ja Wilson", "Aja Wilson", "A. Wilson"],
        "Breanna Stewart" => ["Breanna Stewart", "B. Stewart", "Stewie"],
        "Sabrina Ionescu" => ["Sabrina Ionescu", "S. Ionescu"],
        "Kelsey Plum" => ["Kelsey Plum", "K. Plum"],
        "Napheesa Collier" => ["Napheesa Collier", "N. Collier"],
        // Add more mappings as needed
    ],

    /*
    |--------------------------------------------------------------------------
    | Error Handling
    |--------------------------------------------------------------------------
    */

    'fallback_enabled' => env('ODDS_API_FALLBACK_ENABLED', true),
    'mock_data_enabled' => env('ODDS_API_MOCK_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('ODDS_API_LOGGING_ENABLED', true),
        'level' => env('ODDS_API_LOGGING_LEVEL', 'info'),
        'channel' => env('ODDS_API_LOGGING_CHANNEL', 'single'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WNBA Player Props Markets (from The Odds API documentation)
    |--------------------------------------------------------------------------
    */

    'wnba_player_props' => [
        // Core Player Props
        'player_points' => [
            'key' => 'player_points',
            'name' => 'Player Points',
            'description' => 'Points (Over/Under)',
            'type' => 'over_under'
        ],
        'player_rebounds' => [
            'key' => 'player_rebounds',
            'name' => 'Player Rebounds',
            'description' => 'Rebounds (Over/Under)',
            'type' => 'over_under'
        ],
        'player_assists' => [
            'key' => 'player_assists',
            'name' => 'Player Assists',
            'description' => 'Assists (Over/Under)',
            'type' => 'over_under'
        ],
        'player_threes' => [
            'key' => 'player_threes',
            'name' => 'Player 3-Pointers',
            'description' => 'Threes Made (Over/Under)',
            'type' => 'over_under'
        ],
        'player_steals' => [
            'key' => 'player_steals',
            'name' => 'Player Steals',
            'description' => 'Steals (Over/Under)',
            'type' => 'over_under'
        ],
        'player_blocks' => [
            'key' => 'player_blocks',
            'name' => 'Player Blocks',
            'description' => 'Blocks (Over/Under)',
            'type' => 'over_under'
        ],
        'player_turnovers' => [
            'key' => 'player_turnovers',
            'name' => 'Player Turnovers',
            'description' => 'Turnovers (Over/Under)',
            'type' => 'over_under'
        ],

        // Combination Props
        'player_points_rebounds' => [
            'key' => 'player_points_rebounds',
            'name' => 'Points + Rebounds',
            'description' => 'Points + Rebounds (Over/Under)',
            'type' => 'over_under'
        ],
        'player_points_assists' => [
            'key' => 'player_points_assists',
            'name' => 'Points + Assists',
            'description' => 'Points + Assists (Over/Under)',
            'type' => 'over_under'
        ],
        'player_rebounds_assists' => [
            'key' => 'player_rebounds_assists',
            'name' => 'Rebounds + Assists',
            'description' => 'Rebounds + Assists (Over/Under)',
            'type' => 'over_under'
        ],
        'player_points_rebounds_assists' => [
            'key' => 'player_points_rebounds_assists',
            'name' => 'Points + Rebounds + Assists',
            'description' => 'Points + Rebounds + Assists (Over/Under)',
            'type' => 'over_under'
        ],

        // Alternate Lines
        'player_points_alternate' => [
            'key' => 'player_points_alternate',
            'name' => 'Alternate Player Points',
            'description' => 'Alternate Points (Over/Under)',
            'type' => 'alternate'
        ],
        'player_rebounds_alternate' => [
            'key' => 'player_rebounds_alternate',
            'name' => 'Alternate Player Rebounds',
            'description' => 'Alternate Rebounds (Over/Under)',
            'type' => 'alternate'
        ],
        'player_assists_alternate' => [
            'key' => 'player_assists_alternate',
            'name' => 'Alternate Player Assists',
            'description' => 'Alternate Assists (Over/Under)',
            'type' => 'alternate'
        ],
        'player_threes_alternate' => [
            'key' => 'player_threes_alternate',
            'name' => 'Alternate Player 3-Pointers',
            'description' => 'Alternate Threes (Over/Under)',
            'type' => 'alternate'
        ],
        'player_points_assists_alternate' => [
            'key' => 'player_points_assists_alternate',
            'name' => 'Alternate Points + Assists',
            'description' => 'Alternate Points + Assists (Over/Under)',
            'type' => 'alternate'
        ],
        'player_points_rebounds_alternate' => [
            'key' => 'player_points_rebounds_alternate',
            'name' => 'Alternate Points + Rebounds',
            'description' => 'Alternate Points + Rebounds (Over/Under)',
            'type' => 'alternate'
        ],
        'player_rebounds_assists_alternate' => [
            'key' => 'player_rebounds_assists_alternate',
            'name' => 'Alternate Rebounds + Assists',
            'description' => 'Alternate Rebounds + Assists (Over/Under)',
            'type' => 'alternate'
        ],
        'player_points_rebounds_assists_alternate' => [
            'key' => 'player_points_rebounds_assists_alternate',
            'name' => 'Alternate Points + Rebounds + Assists',
            'description' => 'Alternate Points + Rebounds + Assists (Over/Under)',
            'type' => 'alternate'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Standard Betting Markets
    |--------------------------------------------------------------------------
    */

    'markets' => [
        'h2h' => [
            'key' => 'h2h',
            'name' => 'Moneyline',
            'description' => 'Head to head, Moneyline - Bet on the winning team'
        ],
        'spreads' => [
            'key' => 'spreads',
            'name' => 'Point Spreads',
            'description' => 'Points spread, Handicap - Bet on the winning team after a points handicap'
        ],
        'totals' => [
            'key' => 'totals',
            'name' => 'Totals (O/U)',
            'description' => 'Total points/goals, Over/Under - Bet on the total score being above or below a threshold'
        ],
        'alternate_spreads' => [
            'key' => 'alternate_spreads',
            'name' => 'Alternate Spreads',
            'description' => 'All available point spread outcomes for each team'
        ],
        'alternate_totals' => [
            'key' => 'alternate_totals',
            'name' => 'Alternate Totals',
            'description' => 'All available over/under outcomes'
        ],
        'team_totals' => [
            'key' => 'team_totals',
            'name' => 'Team Totals',
            'description' => 'Featured team totals (Over/Under)'
        ],
    ],
];
