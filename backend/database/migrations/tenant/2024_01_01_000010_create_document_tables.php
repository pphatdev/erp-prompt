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
        // Folders Table
        if (!Schema::hasTable('folders')) {
            Schema::create('folders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->uuid('parent_id')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
            });

            Schema::table('folders', function (Blueprint $table) {
                $table->foreign('parent_id')->references('id')->on('folders')->onDelete('cascade');
            });
        }

        // Documents Table
        if (!Schema::hasTable('documents')) {
            Schema::create('documents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('filename');
                $table->string('mime_type');
                $table->integer('size_bytes');
                $table->string('path'); // Path within tenant's isolated storage
                $table->uuid('folder_id')->nullable();
                
                $table->uuid('uploader_id')->nullable();
                
                // Polymorphic link (optional, if linked to a specific record like Employee)
                $table->string('documentable_type')->nullable();
                $table->uuid('documentable_id')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->foreign('folder_id')->references('id')->on('folders')->onDelete('set null');
                $table->foreign('uploader_id')->references('id')->on('users')->onDelete('set null');
                $table->index('tenant_id');
                $table->index(['documentable_type', 'documentable_id']);
            });
        }

        // Tags Table
        if (!Schema::hasTable('tags')) {
            Schema::create('tags', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug');
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->index('tenant_id');
            });
        }

        // Document-Tag Pivot
        if (!Schema::hasTable('document_tag')) {
            Schema::create('document_tag', function (Blueprint $table) {
                $table->uuid('document_id');
                $table->uuid('tag_id');
                
                $table->primary(['document_id', 'tag_id']);
                $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
                $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('folders');
    }
};
