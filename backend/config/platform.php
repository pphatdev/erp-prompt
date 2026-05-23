<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | System domain
    |--------------------------------------------------------------------------
    |
    | The base domain used to build tenant subdomains when a customer subscription
    | is confirmed.  Set APP_SYSTEM_DOMAIN in your .env to match the wildcard DNS
    | entry you have configured (e.g. *.systemdomain.app).
    |
    | Subdomains are assembled as: {tenant_handle}.{system_domain}
    |
    */
    'system_domain' => env('APP_SYSTEM_DOMAIN', 'localhost'),
];
