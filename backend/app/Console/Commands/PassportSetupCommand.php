<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Safe wrapper around passport:install that prevents the duplicate-migration trap.
 *
 * Problem: `php artisan passport:install` (and `--force`) copies Passport's
 * migration files into database/migrations/ with NEW timestamps on every run.
 * Each new set of duplicates fails with SQLSTATE[42P07] on the next `migrate`.
 *
 * Solution: This command only generates encryption keys and creates clients.
 * It never publishes migration files — those are already committed under
 * database/migrations/2026_05_21_014519_* and must not be re-published.
 *
 * Usage:
 *   php artisan passport:setup              # First-time: keys + clients
 *   php artisan passport:setup --keys-only  # Rotate keys only (after initial setup)
 */
class PassportSetupCommand extends Command
{
    protected $signature   = 'passport:setup {--keys-only : Only regenerate encryption keys, skip client creation}';
    protected $description = 'Safely set up Passport: generates keys and creates OAuth clients without publishing duplicate migration files.';

    public function handle(): int
    {
        $this->info('Setting up Laravel Passport (safe mode — no migration publishing)...');

        // Step 1: Generate/rotate encryption keys
        $this->info('Generating Passport encryption keys...');
        $this->call('passport:keys', ['--force' => true]);

        if ($this->option('keys-only')) {
            $this->info('Keys regenerated. Skipping client creation (--keys-only).');
            return self::SUCCESS;
        }

        // Step 2: Create the personal access client
        $this->info('Creating personal access client...');
        $this->call('passport:client', [
            '--personal' => true,
            '--name'     => config('app.name') . ' Personal Access Client',
        ]);

        // Step 3: Create the password grant client
        $this->info('Creating password grant client...');
        $this->call('passport:client', [
            '--password' => true,
            '--name'     => config('app.name') . ' Password Grant Client',
        ]);

        $this->newLine();
        $this->warn('ACTION REQUIRED: Copy the Client ID and Secret printed above into your .env:');
        $this->line('  PASSPORT_PERSONAL_ACCESS_CLIENT_ID=<personal-client-id>');
        $this->line('  PASSPORT_PERSONAL_ACCESS_CLIENT_SECRET=<personal-client-secret>');
        $this->line('  PASSPORT_PASSWORD_CLIENT_ID=<password-client-id>');
        $this->line('  PASSPORT_PASSWORD_CLIENT_SECRET=<password-client-secret>');
        $this->newLine();
        $this->info('Done. Run `php artisan config:clear` after updating .env.');

        return self::SUCCESS;
    }
}
