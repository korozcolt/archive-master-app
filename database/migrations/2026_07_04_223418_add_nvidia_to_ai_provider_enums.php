<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE company_ai_settings MODIFY provider ENUM('none', 'openai', 'gemini', 'nvidia') NOT NULL DEFAULT 'none'");
        DB::statement("ALTER TABLE document_ai_runs MODIFY provider ENUM('openai', 'gemini', 'nvidia') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('company_ai_settings')
            ->where('provider', 'nvidia')
            ->update(['provider' => 'none', 'is_enabled' => false]);

        DB::table('document_ai_runs')
            ->where('provider', 'nvidia')
            ->update(['provider' => 'openai']);

        DB::statement("ALTER TABLE company_ai_settings MODIFY provider ENUM('none', 'openai', 'gemini') NOT NULL DEFAULT 'none'");
        DB::statement("ALTER TABLE document_ai_runs MODIFY provider ENUM('openai', 'gemini') NOT NULL");
    }
};
