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
        'league_id' => env('SPORTSBLAZE_LEAGUE_ID', 'wnba'),
        'api_key' => env('SPORTSBLAZE_API_KEY'),
        'feeds' => [
            'player_boxscores' => env('SPORTSBLAZE_WNBA_PLAYER_BOXSCORES_URL'),
            'team_boxscores' => env('SPORTSBLAZE_WNBA_TEAM_BOXSCORES_URL'),
            'schedule' => env('SPORTSBLAZE_WNBA_SCHEDULE_URL'),
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

    'agents' => [
        // Lower number wins conflicts. ESPN is the canonical-ID source of truth.
        'source_priority' => [
            'espn' => 1,
            'tank01' => 2,
            'sportsdataverse' => 3,
            'sportsblaze' => 4,
        ],
        // Ingest current-season play-by-play in the nightly data-agent run.
        'pbp_default' => env('WNBA_PBP_DEFAULT', true),
        // Persist injury reports / odds snapshots during data-agent runs.
        'persist_injuries' => env('WNBA_PERSIST_INJURIES', true),
        'persist_odds' => env('WNBA_PERSIST_ODDS', true),
        // Raw payloads older than this are prunable (roughly one season + buffer).
        'raw_payload_retention_days' => (int) env('WNBA_RAW_PAYLOAD_RETENTION_DAYS', 400),
        'queue' => env('WNBA_AGENT_QUEUE', 'default'),
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
