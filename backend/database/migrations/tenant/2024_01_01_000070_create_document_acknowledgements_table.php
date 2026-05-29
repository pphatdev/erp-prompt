<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('document_acknowledgements')) {
            return;
        }

        Schema::create('document_acknowledgements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->uuid('user_id');
            $table->timestamp('acknowledged_at');

            $table->string('tenant_id');
            $table->timestamps();

            $table->foreign('document_id')->references('id')->on('documents')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('tenant_id');
            $table->unique(['document_id', 'user_id', 'tenant_id'], 'doc_ack_doc_user_tenant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_acknowledgements');
    }
};
