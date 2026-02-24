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
        Schema::table('document_distribution_targets', function (Blueprint $table) {
            $table->string('response_type', 32)->nullable()->after('status');
            $table->foreignId('response_document_id')->nullable()->after('response_note')->constrained('documents')->nullOnDelete();
            $table->text('rejected_reason')->nullable()->after('response_document_id');
            $table->foreignId('responded_by')->nullable()->after('last_updated_by')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_distribution_targets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responded_by');
            $table->dropConstrainedForeignId('response_document_id');
            $table->dropColumn(['response_type', 'rejected_reason']);
        });
    }
};
