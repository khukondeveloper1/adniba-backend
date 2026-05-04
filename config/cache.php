<?php

use Illuminate\Support\Str;

return [

    'default' => env('CACHE_STORE', 'redis'),

    'stores' => [

        'redis' => [
            'driver'            => 'redis',
            'connection'        => 'cache',      // uses REDIS_CACHE_DB
            'lock_connection'   => 'default',
        ],

        'array' => [
            'driver'    => 'array',
            'serialize' => false,
        ],

        'file' => [
            'driver' => 'file',
            'path'   => storage_path('framework/cache/data'),
        ],
    ],

    'prefix' => env('CACHE_PREFIX', Str::slug(env('APP_NAME', 'adniba'), '_') . '_cache'),

];
