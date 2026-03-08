<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documentary_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('documentary_subseries_id')->constrained('documentary_subseries')->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('access_level_default')->default('interno');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'documentary_subseries_id', 'code'], 'doc_types_company_subseries_code_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentary_types');
    }
};
