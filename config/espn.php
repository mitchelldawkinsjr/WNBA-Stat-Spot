<?php

return [
    'site_base' => rtrim(env('ESPN_SITE_BASE_URL', 'https://site.api.espn.com/apis/site/v2/sports/basketball/wnba'), '/'),
    'web_base' => rtrim(env('ESPN_WEB_BASE_URL', 'https://site.web.api.espn.com/apis/common/v3/sports/basketball/wnba'), '/'),
    'timeout' => (int) env('ESPN_TIMEOUT', 30),
    'rate_limit_per_minute' => (int) env('ESPN_RATE_LIMIT', 30),
    'cache_prefix' => 'espn_wnba_',

    'cache_ttl' => [
        'teams' => (int) env('ESPN_CACHE_TEAMS', 86400),
        'roster' => (int) env('ESPN_CACHE_ROSTER', 3600),
        'schedule' => (int) env('ESPN_CACHE_SCHEDULE', 1800),
        'summary_final' => (int) env('ESPN_CACHE_SUMMARY_FINAL', 604800),
        'summary_live' => (int) env('ESPN_CACHE_SUMMARY_LIVE', 120),
        'gamelog' => (int) env('ESPN_CACHE_GAMELOG', 900),
        'scoreboard' => (int) env('ESPN_CACHE_SCOREBOARD', 300),
        'overview' => (int) env('ESPN_CACHE_OVERVIEW', 900),
        'news' => (int) env('ESPN_CACHE_NEWS', 600),
        'injuries' => (int) env('ESPN_CACHE_INJURIES', 600),
    ],
];
