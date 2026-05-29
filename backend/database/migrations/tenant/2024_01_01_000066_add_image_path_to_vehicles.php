<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vehicles') && !Schema::hasColumn('vehicles', 'image_path')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->string('image_path')->nullable()->after('current_mileage');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('vehicles', 'image_path')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }
};
