<?php

return [
    'features' => [
        'enable_live_updates' => env('WNBA_ENABLE_LIVE_UPDATES', false),
    ],

    'import' => [
        'game_batch_size' => (int) env('WNBA_IMPORT_GAME_BATCH_SIZE', 10),
        'memory_limit' => env('WNBA_IMPORT_MEMORY_LIMIT', '512M'),
        'sync_identities' => env('WNBA_IMPORT_SYNC_IDENTITIES', true),
    ],

    'data_source' => [
        'provider' => env('WNBA_DATA_PROVIDER', 'sportsblaze'),
        'routing' => [
            'bulk_import' => env('WNBA_BULK_PROVIDER', env('WNBA_DATA_PROVIDER', 'sportsdataverse')),
            'incremental' => env('WNBA_INCREMENTAL_PROVIDER', env('WNBA_DATA_PROVIDER', 'espn')),
            'live_sync' => env('WNBA_LIVE_PROVIDER', 'tank01'),
            'player_gamelog' => env('WNBA_GAMELOG_PROVIDER', 'espn'),
            'play_by_play' => env('WNBA_PBP_PROVIDER', 'sportsdataverse'),
        ],
        'base_url' => rtrim(env('SPORTSBLAZE_WNBA_BASE_URL', 'https://api.sportsblaze.com'), '/'),
        'cache_base_url' => rtrim(env('SPORTSBLAZE_CACHE_BASE_URL', 'https://cache.sportsblaze.com'), '/'),
        'league_id' => env('SPORTSBLAZE_LEAGUE_ID', 'wnba'),
        'api_key' => env('SPORTSBLAZE_API_KEY'),
        'feeds' => [
            'player_boxscores' => env('SPORTSBLAZE_WNBA_PLAYER_BOXSCORES_URL'),
            'team_boxscores' => env('SPORTSBLAZE_WNBA_TEAM_BOXSCORES_URL'),
            'schedule' => env('SPORTSBLAZE_WNBA_SCHEDULE_URL'),
            'rosters' => env('SPORTSBLAZE_WNBA_ROSTERS_URL'),
            'play_by_play' => env('SPORTSBLAZE_WNBA_PLAY_BY_PLAY_URL'),
        ],
        'fallback_to_sportsdataverse' => env('WNBA_FALLBACK_TO_SPORTSDATAVERSE', false),
    ],

    'api' => [
        'timeout' => env('WNBA_API_TIMEOUT', 30),
    ],

    'seasons' => [
        'current_season' => (int) env('WNBA_CURRENT_SEASON', 2026),
        'current_season_label' => env('WNBA_CURRENT_SEASON_LABEL', '2026-2027'),
    ],

    'teams' => [
        // Provider abbreviations mapped to ESPN canonical values used in wnba_teams.
        'abbreviation_aliases' => [
            'LAS' => 'LA',   // Los Angeles Sparks (Tank01)
            'LVA' => 'LV',   // Las Vegas Aces (Tank01 / odds)
            'NYL' => 'NY',   // New York Liberty (Tank01)
            'WAS' => 'WSH',  // Washington Mystics (Tank01)
            'CONN' => 'CON', // Connecticut Sun (legacy)
            'GSV' => 'GS',   // Golden State Valkyries (alternate)
            'PHO' => 'PHX',  // Phoenix Mercury (legacy)
        ],

        // All-star / exhibition national teams — hide from league team lists.
        'excluded_team_ids' => [
            '17475', // Japan
            '17476', // Nigeria
        ],
    ],
];
