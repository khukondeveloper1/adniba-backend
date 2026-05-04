<?php

return [
    'defaults' => [
        'guard'     => 'api',
        'passwords' => 'admin_users',
    ],

    'guards' => [
        'api' => [
            'driver'   => 'jwt',
            'provider' => 'admin_users',
        ],
        'developer' => [
            'driver'   => 'jwt',
            'provider' => 'developers',
        ],
        'web' => [
            'driver'   => 'session',
            'provider' => 'admin_users',
        ],
    ],

    'providers' => [
        'admin_users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\AdminUser::class,
        ],
        'developers' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class,
        ],
    ],

    'passwords' => [
        'admin_users' => [
            'provider' => 'admin_users',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
        'developers' => [
            'provider' => 'developers',
            'table'    => 'password_reset_tokens',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];
