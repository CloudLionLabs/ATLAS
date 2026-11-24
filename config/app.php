<?php
declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Atlas 全球运营'),
    'env' => env('APP_ENV', 'production'),
    'host' => env('APP_HOST', '127.0.0.1'),
    'port' => (int) env('APP_PORT', 9000),
    'url' => rtrim((string) env('APP_URL', 'http://127.0.0.1:9000'), '/'),
    'timezone' => env('APP_TIMEZONE', 'UTC'),
    'session_name' => env('SESSION_NAME', 'atlas_gbs_session'),
    'database' => [
        'driver' => env('DB_DRIVER', 'mysql'),
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => (int) env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'atlas_global'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', 'root'),
        'charset' => env('DB_CHARSET', 'utf8mb4'),
        'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
    ],
    'geo_database' => [
        'path' => base_path(env('GEO_DB_PATH', 'storage/geo.sqlite')),
    ],
    'scraper' => [
        'base_url' => rtrim((string) env('SCRAPER_BASE_URL', 'http://127.0.0.1:8080'), '/'),
        'connect_timeout' => (int) env('SCRAPER_CONNECT_TIMEOUT', 5),
        'read_timeout' => (int) env('SCRAPER_READ_TIMEOUT', 45),
        'poll_interval' => (int) env('SCRAPER_POLL_INTERVAL', 10),
        'max_concurrent_jobs' => (int) env('SCRAPER_MAX_CONCURRENT_JOBS', 1),
    ],
    'geocoder' => [
        'url' => rtrim((string) env('GEOCODER_URL', 'https://nominatim.openstreetmap.org'), '/'),
        'user_agent' => env('GEOCODER_USER_AGENT', 'AtlasGlobalBusinessAdmin/1.0'),
        'language' => env('GEOCODER_LANGUAGE', 'zh-CN,en'),
    ],
    'map' => [
        'provider' => env('TILE_PROVIDER', 'carto_dark'),
        'default_lat' => (float) env('MAP_DEFAULT_LAT', 20.0),
        'default_lng' => (float) env('MAP_DEFAULT_LNG', 10.0),
        'default_zoom' => (int) env('MAP_DEFAULT_ZOOM', 2),
    ],
];
