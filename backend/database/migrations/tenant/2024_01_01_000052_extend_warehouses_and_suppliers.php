<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foundation extensions for Warehouse + Supplier so they're usable beyond
 * the placeholder fields the original `create_inventory_tables` migration
 * shipped with. Both tables already exist — this adds the operational
 * columns the Inventory phase-1 backend needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            if (!Schema::hasColumn('warehouses', 'manager_id')) {
                $table->uuid('manager_id')->nullable()->after('location');
            }
            if (!Schema::hasColumn('warehouses', 'address_line')) {
                $table->string('address_line')->nullable()->after('manager_id');
            }
            if (!Schema::hasColumn('warehouses', 'city')) {
                $table->string('city', 120)->nullable()->after('address_line');
            }
            if (!Schema::hasColumn('warehouses', 'country')) {
                $table->string('country', 2)->nullable()->after('city');
            }
            if (!Schema::hasColumn('warehouses', 'capacity')) {
                // Soft logical capacity (e.g. pallet slots). Used by the UI gauge
                // and the "near-capacity" warning. NULL = unlimited.
                $table->integer('capacity')->nullable()->after('country');
            }
            if (!Schema::hasColumn('warehouses', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('capacity');
            }
            if (!Schema::hasColumn('warehouses', 'notes')) {
                $table->text('notes')->nullable()->after('is_active');
            }
        });

        Schema::table('suppliers', function (Blueprint $table) {
            if (!Schema::hasColumn('suppliers', 'code')) {
                $table->string('code', 40)->nullable()->after('id');
                // Unique-per-tenant; partial index via raw or composite — for
                // multi-tenant DBs (stancl) a plain unique on code is fine
                // because each tenant has its own physical DB.
                $table->unique('code', 'suppliers_code_unique');
            }
            if (!Schema::hasColumn('suppliers', 'contact_name')) {
                $table->string('contact_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('suppliers', 'website')) {
                $table->string('website')->nullable()->after('address');
            }
            if (!Schema::hasColumn('suppliers', 'tax_id')) {
                $table->string('tax_id', 60)->nullable()->after('website');
            }
            if (!Schema::hasColumn('suppliers', 'payment_terms')) {
                // Free-text — e.g. "Net 30", "Prepaid", "COD". A future P2P
                // phase can normalize this into days_to_pay + discount terms.
                $table->string('payment_terms', 60)->nullable()->after('tax_id');
            }
            if (!Schema::hasColumn('suppliers', 'lead_time_days')) {
                $table->unsignedSmallInteger('lead_time_days')->nullable()->after('payment_terms');
            }
            if (!Schema::hasColumn('suppliers', 'rating')) {
                // 1-5; null when no rating yet.
                $table->unsignedTinyInteger('rating')->nullable()->after('lead_time_days');
            }
            if (!Schema::hasColumn('suppliers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('rating');
            }
            if (!Schema::hasColumn('suppliers', 'notes')) {
                $table->text('notes')->nullable()->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $cols = ['manager_id', 'address_line', 'city', 'country', 'capacity', 'is_active', 'notes'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('warehouses', $c)) $table->dropColumn($c);
            }
        });

        Schema::table('suppliers', function (Blueprint $table) {
            if (Schema::hasColumn('suppliers', 'code')) {
                $table->dropUnique('suppliers_code_unique');
            }
            $cols = ['code', 'contact_name', 'website', 'tax_id', 'payment_terms',
                     'lead_time_days', 'rating', 'is_active', 'notes'];
            foreach ($cols as $c) {
                if (Schema::hasColumn('suppliers', $c)) $table->dropColumn($c);
            }
        });
    }
};
