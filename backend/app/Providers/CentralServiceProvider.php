<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class CentralServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register landlord services
    }

    public function boot(): void
    {
        // Boot landlord services
        $this->loadMigrationsFrom(database_path('migrations/central'));

        // -----------------------------------------------------------------------
        // PASSPORT MIGRATION GUARD
        // -----------------------------------------------------------------------
        // Remove passport-migrations from the vendor:publish registry.
        // Passport's oauth_* migration files are already committed under
        // database/migrations/2026_05_21_014519_* and must never be re-published.
        //
        // Without this, every run of `php artisan passport:install` (with or
        // without --force) copies new timestamped migration files into
        // database/migrations/, causing SQLSTATE[42P07] duplicate table errors
        // on the next `php artisan migrate`.
        //
        // Use `php artisan passport:setup` instead of `passport:install`.
        // -----------------------------------------------------------------------
        ServiceProvider::$publishGroups['passport-migrations'] = [];
    }
}
