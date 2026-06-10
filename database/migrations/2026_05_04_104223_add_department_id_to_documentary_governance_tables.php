<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentary_series', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['company_id', 'department_id'], 'doc_series_company_department_idx');
            $table->unique(['company_id', 'department_id', 'code'], 'doc_series_company_department_code_uq');
            $table->dropUnique('documentary_series_company_id_code_unique');
        });

        Schema::table('documentary_subseries', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['company_id', 'department_id'], 'doc_subseries_company_department_idx');
            $table->unique(['company_id', 'department_id', 'documentary_series_id', 'code'], 'doc_subseries_company_department_series_code_uq');
            $table->dropUnique('doc_subseries_company_series_code_uq');
        });

        Schema::table('documentary_types', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['company_id', 'department_id'], 'doc_types_company_department_idx');
            $table->unique(['company_id', 'department_id', 'documentary_subseries_id', 'code'], 'doc_types_company_department_subseries_code_uq');
            $table->dropUnique('doc_types_company_subseries_code_uq');
        });

        Schema::table('retention_schedules', function (Blueprint $table) {
            $table->foreignId('department_id')
                ->nullable()
                ->after('company_id')
                ->constrained()
                ->nullOnDelete();

            $table->index(['company_id', 'department_id'], 'retention_schedules_company_department_idx');
            $table->index(['department_id', 'documentary_type_id'], 'retention_schedules_department_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('retention_schedules', function (Blueprint $table) {
            $table->dropIndex('retention_schedules_department_type_idx');
            $table->dropIndex('retention_schedules_company_department_idx');
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('documentary_types', function (Blueprint $table) {
            $table->dropIndex('doc_types_company_department_idx');
            $table->dropUnique('doc_types_company_department_subseries_code_uq');
            $table->unique(['company_id', 'documentary_subseries_id', 'code'], 'doc_types_company_subseries_code_uq');
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('documentary_subseries', function (Blueprint $table) {
            $table->dropIndex('doc_subseries_company_department_idx');
            $table->dropUnique('doc_subseries_company_department_series_code_uq');
            $table->unique(['company_id', 'documentary_series_id', 'code'], 'doc_subseries_company_series_code_uq');
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('documentary_series', function (Blueprint $table) {
            $table->dropIndex('doc_series_company_department_idx');
            $table->dropUnique('doc_series_company_department_code_uq');
            $table->unique(['company_id', 'code']);
            $table->dropConstrainedForeignId('department_id');
        });
    }
};
