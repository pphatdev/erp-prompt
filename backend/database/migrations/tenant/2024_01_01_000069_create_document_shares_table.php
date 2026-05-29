<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_shares')) {
            return;
        }

        Schema::create('document_shares', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at')->nullable();
            $table->string('password_hash')->nullable();
            $table->unsignedInteger('max_downloads')->nullable();
            $table->unsignedInteger('downloads_count')->default(0);
            $table->uuid('created_by')->nullable();

            $table->string('tenant_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
            $table->index('tenant_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_shares');
    }
};
