<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Personal Access Client
    |--------------------------------------------------------------------------
    |
    | Created via `php artisan passport:client --personal`. Used by
    | $user->createToken() in tests or admin tooling.
    |
    */
    'personal_access_client_id'     => env('PASSPORT_PERSONAL_ACCESS_CLIENT_ID'),
    'personal_access_client_secret' => env('PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Password Grant Client
    |--------------------------------------------------------------------------
    |
    | Created via `php artisan passport:client --password`. The IAM AuthController
    | uses this client to forward credentials to /oauth/token and to exchange
    | refresh tokens — see App\Tenants\Modules\IAM\Controllers\AuthController.
    |
    */
    'password_client_id'     => env('PASSPORT_PASSWORD_CLIENT_ID'),
    'password_client_secret' => env('PASSPORT_PASSWORD_CLIENT_SECRET'),

];
