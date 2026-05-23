<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Fix oauth_access_tokens.user_id and oauth_auth_codes.user_id column types.
 *
 * Passport's default migrations define user_id as unsignedBigInteger, which
 * is incompatible with UUID-keyed user models. This migration alters both
 * columns to string(100) to support UUID primary keys.
 *
 * Affects tenant databases only (lives in migrations/tenant/).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Fix oauth_access_tokens.user_id — bigint → string(100)
        if (Schema::hasTable('oauth_access_tokens')) {
            Schema::table('oauth_access_tokens', function (Blueprint $table) {
                // Drop the index before altering the column
                $table->dropIndex(['user_id']);
            });
            Schema::table('oauth_access_tokens', function (Blueprint $table) {
                $table->string('user_id', 100)->nullable()->change();
            });
            Schema::table('oauth_access_tokens', function (Blueprint $table) {
                $table->index('user_id');
            });
        }

        // Fix oauth_auth_codes.user_id — bigint → string(100)
        if (Schema::hasTable('oauth_auth_codes')) {
            Schema::table('oauth_auth_codes', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
            });
            Schema::table('oauth_auth_codes', function (Blueprint $table) {
                $table->string('user_id', 100)->nullable()->change();
            });
            Schema::table('oauth_auth_codes', function (Blueprint $table) {
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('oauth_access_tokens')) {
            Schema::table('oauth_access_tokens', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
            });
            Schema::table('oauth_access_tokens', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
            Schema::table('oauth_access_tokens', function (Blueprint $table) {
                $table->index('user_id');
            });
        }

        if (Schema::hasTable('oauth_auth_codes')) {
            Schema::table('oauth_auth_codes', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
            });
            Schema::table('oauth_auth_codes', function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
            Schema::table('oauth_auth_codes', function (Blueprint $table) {
                $table->index('user_id');
            });
        }
    }
};
