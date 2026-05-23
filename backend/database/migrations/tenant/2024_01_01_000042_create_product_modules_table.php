<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('product_modules')) {
            return;
        }

        Schema::create('product_modules', function (Blueprint $table) {
            $table->uuid('product_id');
            $table->uuid('module_id');
            $table->primary(['product_id', 'module_id']);
            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_modules');
    }
};
