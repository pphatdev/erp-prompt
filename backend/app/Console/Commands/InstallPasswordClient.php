<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Installs a Passport Password Grant client into the CURRENT tenant database.
 *
 * Why: `php artisan passport:client --password` runs once and assigns an
 * auto-increment ID per DB. In a multi-tenant setup with per-tenant
 * `oauth_clients` tables, every tenant gets a different ID/secret, which
 * doesn't fit a single PASSPORT_PASSWORD_CLIENT_ID env var.
 *
 * This command forces a known ID + hashes a known secret so every tenant
 * shares the same credentials. Run via:
 *
 *   php artisan tenants:run passport:install-password-client
 */
class InstallPasswordClient extends Command
{
    protected $signature = 'passport:install-password-client
                            {--id=1 : Numeric client ID to install}
                            {--secret= : Plaintext secret (defaults to env(PASSPORT_PASSWORD_CLIENT_SECRET) or generated)}
                            {--name=ERP Password Grant : Client display name}';

    protected $description = 'Idempotently install a Password Grant Passport client in the current (tenant) database';

    public function handle(): int
    {
        if (!Schema::hasTable('oauth_clients')) {
            $this->error('oauth_clients table is missing on the current connection. Run tenant migrations first.');
            return self::FAILURE;
        }

        $id     = (int) $this->option('id');
        $name   = (string) $this->option('name');
        $secret = $this->option('secret') ?: env('PASSPORT_PASSWORD_CLIENT_SECRET');

        $generated = false;
        if (!$secret) {
            $secret = Str::random(40);
            $generated = true;
        }

        DB::table('oauth_clients')->where('id', $id)->delete();

        DB::table('oauth_clients')->insert([
            'id'                     => $id,
            'user_id'                => null,
            'name'                   => $name,
            'secret'                 => Hash::make($secret),
            'provider'               => null,
            'redirect'               => '',
            'personal_access_client' => false,
            'password_client'        => true,
            'revoked'                => false,
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);

        $this->info("Password client installed on connection: " . DB::connection()->getName());
        $this->line("  Client ID:     {$id}");
        $this->line("  Client Secret: {$secret}");

        if ($generated) {
            $this->newLine();
            $this->warn('A new secret was generated. Put these in your .env so the same pair applies to every tenant:');
            $this->line("  PASSPORT_PASSWORD_CLIENT_ID={$id}");
            $this->line("  PASSPORT_PASSWORD_CLIENT_SECRET={$secret}");
        }

        return self::SUCCESS;
    }
}
