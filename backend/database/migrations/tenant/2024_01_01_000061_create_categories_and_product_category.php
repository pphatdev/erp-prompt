<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('slug', 120);
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->string('color', 32)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->uuid('parent_id')->nullable();
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['tenant_id', 'is_active']);
                $table->unique(['slug', 'tenant_id']);
            });

            Schema::table('categories', function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
            });
        }

        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->uuid('category_id')->nullable()->after('product_type');
                $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
                $table->index('category_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
                $table->dropIndex(['category_id']);
                $table->dropColumn('category_id');
            });
        }

        if (Schema::hasTable('categories')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropForeign(['parent_id']);
            });
            Schema::dropIfExists('categories');
        }
    }
};
