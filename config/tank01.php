<?php

return [
    'api_key' => env('RAPIDAPI_KEY'),
    'host' => env('TANK01_WNBA_HOST', 'tank01-wnba-live-in-game-real-time-statistics-wnba.p.rapidapi.com'),
    'base_url' => rtrim(env('TANK01_WNBA_BASE_URL', 'https://tank01-wnba-live-in-game-real-time-statistics-wnba.p.rapidapi.com'), '/'),
    'timeout' => (int) env('TANK01_TIMEOUT', 30),

    'endpoints' => [
        'teams' => 'getWNBATeams',
        'schedule' => 'getWNBASchedule',
        'scoreboard' => 'getWNBAScoreboard',
        'box_score' => 'getWNBABoxScore',
        'game_info' => 'getWNBAGameInfo',
        'betting_odds' => 'getWNBABettingOdds',
        'games_for_player' => 'getWNBAGamesForPlayer',
        'injuries' => 'getWNBAInjuries',
        'news' => 'getWNBANews',
        'player_info' => 'getWNBAPlayerInfo',
    ],

    'rate_limit' => [
        'requests_per_month' => (int) env('TANK01_MONTHLY_LIMIT', 1000),
        'daily_target' => (int) env('TANK01_DAILY_TARGET', 30),
        'warn_threshold' => 0.80,
        'block_threshold' => 0.95,
        'burst_limit' => (int) env('TANK01_BURST_LIMIT', 3),
        'cooldown_period' => (int) env('TANK01_COOLDOWN', 300),
        'track_usage' => true,
    ],

    'cache_ttl' => [
        'teams_schedule' => (int) env('TANK01_CACHE_TEAMS', 86400),
        'box_score_final' => (int) env('TANK01_CACHE_BOX_FINAL', 604800),
        'box_score_live' => (int) env('TANK01_CACHE_BOX_LIVE', 60),
        'odds' => (int) env('TANK01_CACHE_ODDS', 3600),
        'scoreboard' => (int) env('TANK01_CACHE_SCOREBOARD', 120),
        'games_for_player' => (int) env('TANK01_CACHE_PLAYER_GAMELOG', 900),
        'injuries' => (int) env('TANK01_CACHE_INJURIES', 1800),
        'news' => (int) env('TANK01_CACHE_NEWS', 600),
        'player_info' => (int) env('TANK01_CACHE_PLAYER_INFO', 1800),
    ],

    'live_sync' => [
        'max_calls_per_run' => (int) env('TANK01_LIVE_MAX_CALLS', 5),
        'enabled' => env('WNBA_ENABLE_LIVE_UPDATES', false),
    ],

    'cache_prefix' => 'tank01_',
];
