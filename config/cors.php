<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'health*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => [
        'http://localhost:4173',
        'http://localhost:3000',
        'http://localhost:5173',
        'http://localhost:8000',
        'https://wnba-stat-spot.onrender.com',
        env('FRONTEND_URL', 'http://localhost:4173'),
        env('STAGING_URL'),
        env('PRODUCTION_URL'),
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept', 'Origin', 'Cache-Control', 'X-HTTP-Method-Override'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];
