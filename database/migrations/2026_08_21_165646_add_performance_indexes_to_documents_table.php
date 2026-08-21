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
        Schema::table('documents', function (Blueprint $table) {
            if (! Schema::hasIndex('documents', ['deleted_at'])) {
                $table->index('deleted_at', 'idx_documents_deleted_at');
            }

            if (! Schema::hasIndex('documents', ['company_id', 'deleted_at', 'created_at'])) {
                $table->index(['company_id', 'deleted_at', 'created_at'], 'idx_documents_company_deleted_created');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_deleted_at');
            $table->dropIndex('idx_documents_company_deleted_created');
        });
    }
};
