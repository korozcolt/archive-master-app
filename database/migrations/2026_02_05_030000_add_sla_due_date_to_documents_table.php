<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->timestamp('sla_due_date')->nullable()->after('due_date');
        });

        DB::table('documents')
            ->whereNull('sla_due_date')
            ->update([
                'sla_due_date' => DB::raw('COALESCE(due_date, due_at)'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('sla_due_date');
        });
    }
};
