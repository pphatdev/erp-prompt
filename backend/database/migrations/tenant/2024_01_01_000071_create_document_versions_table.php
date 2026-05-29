<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_versions')) {
            return;
        }

        Schema::create('document_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->unsignedInteger('version_number');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->string('path');
            $table->text('change_summary')->nullable();
            $table->uuid('uploaded_by_id')->nullable();

            $table->string('tenant_id');
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('uploaded_by_id')->references('id')->on('users')->onDelete('set null');
            $table->index('tenant_id');
            $table->unique(['document_id', 'version_number'], 'doc_versions_doc_version_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
