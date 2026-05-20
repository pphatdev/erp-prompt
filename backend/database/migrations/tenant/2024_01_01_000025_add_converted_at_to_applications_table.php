<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Timestamp the application was converted into an Employee record.
            // Drives the revert-conversion window (≤ 7 days) — outside that
            // window the link is considered "settled" and the revert endpoint
            // refuses to act on it.
            $table->timestamp('converted_at')->nullable()->after('employee_id');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('converted_at');
        });
    }
};
