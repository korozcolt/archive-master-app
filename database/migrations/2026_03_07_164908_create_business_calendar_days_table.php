<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_calendar_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_calendar_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->boolean('is_business_day')->default(true);
            $table->string('note')->nullable();

            $table->unique(['business_calendar_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_calendar_days');
    }
};
