<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Dashboards Table (Customizable user views)
        if (!Schema::hasTable('dashboards')) {
            Schema::create('dashboards', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->uuid('user_id'); // Owner of the dashboard
                $table->boolean('is_default')->default(false);
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }

        // Widgets Table (Components on a dashboard)
        if (!Schema::hasTable('widgets')) {
            Schema::create('widgets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('dashboard_id');
                $table->string('type'); // line_chart, bar_chart, metric_card, table
                $table->string('data_source'); // e.g., 'sales_revenue', 'hr_headcount'
                $table->json('config'); // Layout coordinates, colors, filters
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('dashboard_id')->references('id')->on('dashboards')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }

        // Scheduled Reports Table
        if (!Schema::hasTable('scheduled_reports')) {
            Schema::create('scheduled_reports', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('report_type'); // e.g., 'monthly_pnl', 'weekly_sales'
                $table->json('parameters')->nullable(); // Date ranges, filters
                $table->string('format')->default('pdf'); // pdf, excel, csv
                $table->string('schedule_cron'); // Cron expression
                $table->json('recipients'); // Array of email addresses or user IDs
                
                $table->uuid('created_by_id');
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('created_by_id')->references('id')->on('users')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_reports');
        Schema::dropIfExists('widgets');
        Schema::dropIfExists('dashboards');
    }
};
