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
        // CMS Folders Table
        if (!Schema::hasTable('cms_folders')) {
            Schema::create('cms_folders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->uuid('parent_id')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
            });

            Schema::table('cms_folders', function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on('cms_folders')->onDelete('cascade');
            });
        }

        // CMS Documents Table
        if (!Schema::hasTable('cms_documents')) {
            Schema::create('cms_documents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->uuid('cms_folder_id')->nullable();
                
                // Locking mechanism (Checkout)
                $table->uuid('locked_by_id')->nullable();
                $table->timestamp('locked_at')->nullable();
                
                $table->date('retention_expiry')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->foreign('cms_folder_id')->references('id')->on('cms_folders')->onDelete('set null');
                $table->foreign('locked_by_id')->references('id')->on('users')->onDelete('set null');
                $table->index('tenant_id');
            });
        }

        // CMS Document Versions Table
        if (!Schema::hasTable('cms_document_versions')) {
            Schema::create('cms_document_versions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('cms_document_id');
                $table->integer('version_number');
                $table->string('filename');
                $table->string('mime_type');
                $table->integer('size_bytes');
                $table->string('path'); // Path within tenant's isolated storage
                $table->text('change_summary')->nullable();
                
                $table->uuid('uploaded_by_id')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('cms_document_id')->references('id')->on('cms_documents')->onDelete('cascade');
                $table->foreign('uploaded_by_id')->references('id')->on('users')->onDelete('set null');
                $table->index('tenant_id');
                $table->unique(['cms_document_id', 'version_number']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cms_document_versions');
        Schema::dropIfExists('cms_documents');
        Schema::dropIfExists('cms_folders');
    }
};
