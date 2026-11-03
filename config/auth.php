<?php

use App\Models\WorkProfile;

return [
    'defaults' => [
        // Framework middleware resolves this guard even though OTP access uses ProfileAccessService.
        'guard' => 'web',
        'passwords' => null,
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'work_profiles',
        ],
    ],

    'providers' => [
        'work_profiles' => [
            'driver' => 'eloquent',
            'model' => WorkProfile::class,
        ],
    ],

    'passwords' => [],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
