<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen `customers.brand_logo_url` to TEXT so customers can store inline
 * base64-encoded logo uploads in addition to external URLs.
 *
 * Inline data URLs let logos travel with the row (no separate file-store /
 * signed-URL infra), which is fine for the tiny brand-mark assets we expect
 * (capped at ~200KB encoded).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->text('brand_logo_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('brand_logo_url', 500)->nullable()->change();
        });
    }
};
