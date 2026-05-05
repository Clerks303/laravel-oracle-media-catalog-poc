<?php

return [
    'default' => env('DB_CONNECTION', 'oracle'),

    'connections' => [
        'oracle' => [
            'driver'         => 'oracle',
            'tns'            => env('DB_TNS', ''),
            'host'           => env('DB_HOST', 'oracle'),
            'port'           => env('DB_PORT', '1521'),
            'database'       => env('DB_DATABASE', 'XEPDB1'),
            'service_name'   => env('DB_SERVICE_NAME', 'XEPDB1'),
            'username'       => env('DB_USERNAME', 'mediacat'),
            'password'       => env('DB_PASSWORD', 'mediacat'),
            'charset'        => env('DB_CHARSET', 'AL32UTF8'),
            'prefix'         => '',
            'prefix_schema'  => env('DB_SCHEMA_PREFIX', ''),
            'edition'        => 'ora$base',
            'server_version' => 'oracle_21c',
            'load_balance'   => 'yes',
            'dynamic'        => [],
        ],

        'sqlite' => [
            'driver'   => 'sqlite',
            'url'      => env('DATABASE_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'   => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],
    ],

    'migrations' => 'migrations',

    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix'  => env('REDIS_PREFIX', 'mediacat_poc_'),
        ],
        'default' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
    ],
];
