<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Authentication Constants
    |--------------------------------------------------------------------------
    |
    | This file contains constants used throughout the authentication system
    |
    */

    'required_role_id' => env('REQUIRED_ROLE_ID', 6),
    'token_expiry_hours' => env('TOKEN_EXPIRY_HOURS', 1),
    'default_currency' => env('DEFAULT_CURRENCY', 'USD'),
    'default_language' => env('DEFAULT_LANGUAGE', 'km'),
    'dashboard_url' => env('DASHBOARD_URL', 'https://khlakhlok_khr.g388g.com/dashboard'),
    'api_timeout' => env('API_TIMEOUT', 30),
];
