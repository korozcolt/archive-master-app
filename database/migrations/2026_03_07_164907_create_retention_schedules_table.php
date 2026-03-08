<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('documentary_subseries_id')->nullable()->constrained('documentary_subseries')->nullOnDelete();
            $table->foreignId('documentary_type_id')->nullable()->constrained('documentary_types')->nullOnDelete();
            $table->string('archive_phase')->default('gestion');
            $table->unsignedSmallInteger('management_years')->default(0);
            $table->unsignedSmallInteger('central_years')->default(0);
            $table->string('historical_action')->nullable();
            $table->string('final_disposition')->default('conservacion_total');
            $table->text('legal_basis')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'archive_phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_schedules');
    }
};
