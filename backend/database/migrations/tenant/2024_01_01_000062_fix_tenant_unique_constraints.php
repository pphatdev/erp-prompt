<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. users
        if (Schema::hasTable('users')) {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');
            DB::statement('DROP INDEX IF EXISTS users_email_unique');
            Schema::table('users', function (Blueprint $table) {
                $table->unique(['email', 'tenant_id'], 'users_email_tenant_id_unique');
            });
        }

        // 2. accounts
        if (Schema::hasTable('accounts')) {
            DB::statement('ALTER TABLE accounts DROP CONSTRAINT IF EXISTS accounts_code_unique');
            DB::statement('DROP INDEX IF EXISTS accounts_code_unique');
            Schema::table('accounts', function (Blueprint $table) {
                $table->unique(['code', 'tenant_id'], 'accounts_code_tenant_id_unique');
            });
        }

        // 3. journal_entries
        if (Schema::hasTable('journal_entries')) {
            DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS journal_entries_reference_number_unique');
            DB::statement('DROP INDEX IF EXISTS journal_entries_reference_number_unique');
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->unique(['reference_number', 'tenant_id'], 'journal_entries_ref_tenant_id_unique');
            });
        }

        // 4. departments
        if (Schema::hasTable('departments')) {
            DB::statement('ALTER TABLE departments DROP CONSTRAINT IF EXISTS departments_code_unique');
            DB::statement('DROP INDEX IF EXISTS departments_code_unique');
            Schema::table('departments', function (Blueprint $table) {
                $table->unique(['code', 'tenant_id'], 'departments_code_tenant_id_unique');
            });
        }

        // 5. employees
        if (Schema::hasTable('employees')) {
            DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_employee_id_unique');
            DB::statement('DROP INDEX IF EXISTS employees_employee_id_unique');
            DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_email_unique');
            DB::statement('DROP INDEX IF EXISTS employees_email_unique');
            Schema::table('employees', function (Blueprint $table) {
                $table->unique(['employee_id', 'tenant_id'], 'employees_emp_id_tenant_id_unique');
            });
            // Partial index for email scoped to tenant and deleted_at
            DB::statement(
                'CREATE UNIQUE INDEX employees_email_tenant_id_unique ON employees (email, tenant_id) WHERE deleted_at IS NULL'
            );
        }

        // 6. purchase_orders
        if (Schema::hasTable('purchase_orders')) {
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_po_number_unique');
            DB::statement('DROP INDEX IF EXISTS purchase_orders_po_number_unique');
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->unique(['po_number', 'tenant_id'], 'purchase_orders_po_num_tenant_id_unique');
            });
        }

        // 7. customers
        if (Schema::hasTable('customers')) {
            DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_email_unique');
            DB::statement('DROP INDEX IF EXISTS customers_email_unique');
            Schema::table('customers', function (Blueprint $table) {
                $table->unique(['email', 'tenant_id'], 'customers_email_tenant_id_unique');
            });
        }

        // 8. orders
        if (Schema::hasTable('orders')) {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_number_unique');
            DB::statement('DROP INDEX IF EXISTS orders_order_number_unique');
            Schema::table('orders', function (Blueprint $table) {
                $table->unique(['order_number', 'tenant_id'], 'orders_order_num_tenant_id_unique');
            });
        }

        // 9. applications
        if (Schema::hasTable('applications')) {
            DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS applications_candidate_code_unique');
            DB::statement('DROP INDEX IF EXISTS applications_candidate_code_unique');
            Schema::table('applications', function (Blueprint $table) {
                $table->unique(['candidate_code', 'tenant_id'], 'applications_code_tenant_id_unique');
            });
        }

        // 10. product_variants
        if (Schema::hasTable('product_variants')) {
            DB::statement('ALTER TABLE product_variants DROP CONSTRAINT IF EXISTS product_variants_sku_unique');
            DB::statement('DROP INDEX IF EXISTS product_variants_sku_unique');
            Schema::table('product_variants', function (Blueprint $table) {
                $table->unique(['sku', 'tenant_id'], 'product_variants_sku_tenant_id_unique');
            });
        }

        // 11. warehouses
        if (Schema::hasTable('warehouses')) {
            DB::statement('ALTER TABLE warehouses DROP CONSTRAINT IF EXISTS warehouses_code_unique');
            DB::statement('DROP INDEX IF EXISTS warehouses_code_unique');
            Schema::table('warehouses', function (Blueprint $table) {
                $table->unique(['code', 'tenant_id'], 'warehouses_code_tenant_id_unique');
            });
        }

        // 12. products
        if (Schema::hasTable('products')) {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_sku_unique');
            DB::statement('DROP INDEX IF EXISTS products_sku_unique');
            Schema::table('products', function (Blueprint $table) {
                $table->unique(['sku', 'tenant_id'], 'products_sku_tenant_id_unique');
            });
        }
    }

    public function down(): void
    {
        // Best effort restore
        if (Schema::hasTable('users')) {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_tenant_id_unique');
            Schema::table('users', function (Blueprint $table) {
                $table->unique('email');
            });
        }
        if (Schema::hasTable('accounts')) {
            DB::statement('ALTER TABLE accounts DROP CONSTRAINT IF EXISTS accounts_code_tenant_id_unique');
            Schema::table('accounts', function (Blueprint $table) {
                $table->unique('code');
            });
        }
        if (Schema::hasTable('journal_entries')) {
            DB::statement('ALTER TABLE journal_entries DROP CONSTRAINT IF EXISTS journal_entries_ref_tenant_id_unique');
            Schema::table('journal_entries', function (Blueprint $table) {
                $table->unique('reference_number');
            });
        }
        if (Schema::hasTable('departments')) {
            DB::statement('ALTER TABLE departments DROP CONSTRAINT IF EXISTS departments_code_tenant_id_unique');
            Schema::table('departments', function (Blueprint $table) {
                $table->unique('code');
            });
        }
        if (Schema::hasTable('employees')) {
            DB::statement('ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_emp_id_tenant_id_unique');
            DB::statement('DROP INDEX IF EXISTS employees_email_tenant_id_unique');
            Schema::table('employees', function (Blueprint $table) {
                $table->unique('employee_id');
                $table->unique('email');
            });
        }
        if (Schema::hasTable('purchase_orders')) {
            DB::statement('ALTER TABLE purchase_orders DROP CONSTRAINT IF EXISTS purchase_orders_po_num_tenant_id_unique');
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->unique('po_number');
            });
        }
        if (Schema::hasTable('customers')) {
            DB::statement('ALTER TABLE customers DROP CONSTRAINT IF EXISTS customers_email_tenant_id_unique');
            Schema::table('customers', function (Blueprint $table) {
                $table->unique('email');
            });
        }
        if (Schema::hasTable('orders')) {
            DB::statement('ALTER TABLE orders DROP CONSTRAINT IF EXISTS orders_order_num_tenant_id_unique');
            Schema::table('orders', function (Blueprint $table) {
                $table->unique('order_number');
            });
        }
        if (Schema::hasTable('applications')) {
            DB::statement('ALTER TABLE applications DROP CONSTRAINT IF EXISTS applications_code_tenant_id_unique');
            Schema::table('applications', function (Blueprint $table) {
                $table->unique('candidate_code');
            });
        }
        if (Schema::hasTable('product_variants')) {
            DB::statement('ALTER TABLE product_variants DROP CONSTRAINT IF EXISTS product_variants_sku_tenant_id_unique');
            Schema::table('product_variants', function (Blueprint $table) {
                $table->unique('sku');
            });
        }
        if (Schema::hasTable('warehouses')) {
            DB::statement('ALTER TABLE warehouses DROP CONSTRAINT IF EXISTS warehouses_code_tenant_id_unique');
            Schema::table('warehouses', function (Blueprint $table) {
                $table->unique('code');
            });
        }
        if (Schema::hasTable('products')) {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_sku_tenant_id_unique');
            Schema::table('products', function (Blueprint $table) {
                $table->unique('sku');
            });
        }
    }
};
