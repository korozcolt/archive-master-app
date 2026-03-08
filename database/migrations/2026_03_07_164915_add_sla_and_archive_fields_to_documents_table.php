<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('sla_policy_id')->nullable()->after('assigned_to')->constrained()->nullOnDelete();
            $table->string('pqrs_type')->nullable()->after('priority');
            $table->text('legal_basis')->nullable()->after('pqrs_type');
            $table->unsignedSmallInteger('legal_term_days')->nullable()->after('legal_basis');
            $table->timestamp('sla_started_at')->nullable()->after('sla_due_date');
            $table->string('sla_status')->nullable()->after('sla_started_at');
            $table->timestamp('sla_paused_at')->nullable()->after('sla_status');
            $table->string('sla_pause_reason')->nullable()->after('sla_paused_at');
            $table->timestamp('sla_resumed_at')->nullable()->after('sla_pause_reason');
            $table->timestamp('first_response_at')->nullable()->after('sla_resumed_at');
            $table->timestamp('closed_at')->nullable()->after('first_response_at');
            $table->timestamp('sla_frozen_at')->nullable()->after('closed_at');
            $table->timestamp('escalated_at')->nullable()->after('sla_frozen_at');
            $table->foreignId('trd_series_id')->nullable()->after('physical_location_id')->constrained('documentary_series')->nullOnDelete();
            $table->foreignId('trd_subseries_id')->nullable()->after('trd_series_id')->constrained('documentary_subseries')->nullOnDelete();
            $table->foreignId('documentary_type_id')->nullable()->after('trd_subseries_id')->constrained('documentary_types')->nullOnDelete();
            $table->string('access_level')->nullable()->after('documentary_type_id');
            $table->unsignedSmallInteger('retention_management_years')->nullable()->after('access_level');
            $table->unsignedSmallInteger('retention_central_years')->nullable()->after('retention_management_years');
            $table->string('retention_historical_action')->nullable()->after('retention_central_years');
            $table->string('final_disposition')->nullable()->after('retention_historical_action');
            $table->string('archive_phase')->nullable()->after('final_disposition');
            $table->string('archive_classification_code')->nullable()->after('archive_phase');

            $table->index(['company_id', 'pqrs_type']);
            $table->index(['company_id', 'sla_status']);
            $table->index(['company_id', 'archive_phase']);
            $table->index(['company_id', 'access_level']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'pqrs_type']);
            $table->dropIndex(['company_id', 'sla_status']);
            $table->dropIndex(['company_id', 'archive_phase']);
            $table->dropIndex(['company_id', 'access_level']);
            $table->dropConstrainedForeignId('sla_policy_id');
            $table->dropConstrainedForeignId('trd_series_id');
            $table->dropConstrainedForeignId('trd_subseries_id');
            $table->dropConstrainedForeignId('documentary_type_id');
            $table->dropColumn([
                'pqrs_type',
                'legal_basis',
                'legal_term_days',
                'sla_started_at',
                'sla_status',
                'sla_paused_at',
                'sla_pause_reason',
                'sla_resumed_at',
                'first_response_at',
                'closed_at',
                'sla_frozen_at',
                'escalated_at',
                'access_level',
                'retention_management_years',
                'retention_central_years',
                'retention_historical_action',
                'final_disposition',
                'archive_phase',
                'archive_classification_code',
            ]);
        });
    }
};
