<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('employees') && !Schema::hasColumn('employees', 'image_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('image_path')->nullable()->after('phone');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('employees', 'image_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }
};
