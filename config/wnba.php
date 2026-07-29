<?php

return [
    'features' => [
        'enable_live_updates' => env('WNBA_ENABLE_LIVE_UPDATES', false),
    ],

    'import' => [
        'game_batch_size' => (int) env('WNBA_IMPORT_GAME_BATCH_SIZE', 10),
        'memory_limit' => env('WNBA_IMPORT_MEMORY_LIMIT', '1024M'),
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

    'predictions' => [
        'auto_tune_enabled' => env('WNBA_PREDICTION_AUTO_TUNE', true),
        'eval_window_size' => (int) env('WNBA_PREDICTION_EVAL_WINDOW', 120),
        'min_learn_samples' => (int) env('WNBA_PREDICTION_MIN_LEARN_SAMPLES', 40),
        'max_weight_step' => (float) env('WNBA_PREDICTION_MAX_WEIGHT_STEP', 0.03),
        'promotion' => [
            'brier_improvement' => 0.002,
            'hit_rate_improvement' => 0.02,
            'max_brier_regression' => 0.005,
        ],
        'defaults' => [
            'adjustments' => [
                'rest_b2b' => 0.9,
                'rest_well' => 1.1,
                'home' => 1.05,
                'opponent_scale' => 0.001,
            ],
            'calibration' => [
                'shrinkage' => 0.0,
            ],
            'gates' => [
                'min_confidence' => 0.6,
                'min_ev' => 0.05,
                // Season avg minutes required before a player is eligible for prop
                // suggestions / prop of the day. Override via WNBA_PROP_MIN_AVG_MINUTES.
                'min_avg_minutes' => (float) env('WNBA_PROP_MIN_AVG_MINUTES', 15),
                // Per-game minutes floor for hit-rate samples (excludes DNP / 0-min rows).
                'min_game_minutes' => (float) env('WNBA_PROP_MIN_GAME_MINUTES', 1),
                'by_stat' => [],
            ],
        ],
        'clamps' => [
            'rest_b2b' => [0.80, 1.00],
            'rest_well' => [1.00, 1.15],
            'home' => [1.00, 1.10],
            'opponent_scale' => [0.0005, 0.002],
            'shrinkage' => [0.0, 0.5],
            'min_confidence' => [0.5, 0.8],
            'min_ev' => [0.0, 0.15],
            'min_avg_minutes' => [0.0, 30.0],
            'min_game_minutes' => [0.0, 20.0],
        ],
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
        // Payloads above this size store only their hash + metadata (8MB default).
        'raw_payload_max_bytes' => (int) env('WNBA_RAW_PAYLOAD_MAX_BYTES', 8388608),
        'queue' => env('WNBA_AGENT_QUEUE', 'default'),
    ],

    // External WNBA-only news feeds merged into /api/wnba/news (live, cached).
    'news_feeds' => [
        'enabled' => env('WNBA_NEWS_FEEDS_ENABLED', true),
        'cache_ttl' => (int) env('WNBA_NEWS_FEEDS_CACHE_TTL', 600),
        // Keep short: a hung upstream used to block /api/wnba/news for the full
        // timeout and stall the homepage Promise.all.
        'timeout' => (int) env('WNBA_NEWS_FEEDS_TIMEOUT', 4),
        'connect_timeout' => (int) env('WNBA_NEWS_FEEDS_CONNECT_TIMEOUT', 2),
        'user_agent' => env(
            'WNBA_NEWS_FEEDS_USER_AGENT',
            'Mozilla/5.0 (compatible; WnbaStatSpot/1.0; +https://wnbastatspot.com)'
        ),
        'sources' => [
            // NBA content API often hangs from the VPS; leave off unless verified.
            'wnba_com' => [
                'enabled' => env('WNBA_NEWS_WNBA_COM_ENABLED', false),
                'label' => 'WNBA',
                'url' => env(
                    'WNBA_NEWS_WNBA_COM_URL',
                    'https://content-api-prod.nba.com/public/1/leagues/wnba/content'
                ),
                'query' => [
                    'types' => 'article',
                    'count' => 25,
                ],
            ],
            'yahoo' => [
                'enabled' => env('WNBA_NEWS_YAHOO_ENABLED', true),
                'label' => 'Yahoo Sports',
                'url' => env('WNBA_NEWS_YAHOO_URL', 'https://sports.yahoo.com/wnba/rss/'),
            ],
            'fox_sports' => [
                'enabled' => env('WNBA_NEWS_FOX_ENABLED', true),
                'label' => 'FOX Sports',
                'url' => env(
                    'WNBA_NEWS_FOX_URL',
                    'https://api.foxsports.com/v2/content/optimized-rss'
                ),
                'query' => [
                    'partnerKey' => env('WNBA_NEWS_FOX_PARTNER_KEY', 'MB0Wehpmuj2lUhuRhQaafhBjAJqaPU244mlTDK1i'),
                    'size' => 30,
                    'tags' => 'fs/wnba',
                ],
                // Keep only articles under the WNBA story path (feed also returns cross-sport tags).
                'url_must_contain' => '/stories/wnba/',
            ],
        ],
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

        // Exhibition / national / All-Star sides — secondary to league franchises.
        // Hidden from primary team lists; their box scores are excluded from
        // primary season aggregates (player GP, trends, hit rates, rankings).
        // Deep links (/teams/{id}) still work for All-Star roster views.
        'excluded_team_ids' => [
            '17475', // Japan (exhibition)
            '17476', // Nigeria (exhibition)
            '133383', // TEAM SPOON (2026 All-Star)
            '133384', // TEAM COOP (2026 All-Star)
        ],
    ],
];
