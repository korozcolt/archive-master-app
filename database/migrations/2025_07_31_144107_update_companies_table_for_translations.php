<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, get all existing data
        $companies = DB::table('companies')->get();
        
        // Convert name column to JSON
        Schema::table('companies', function (Blueprint $table) {
            $table->json('name')->change();
        });
        
        // Update existing data to JSON format with Spanish locale
        foreach ($companies as $company) {
            $nameJson = json_encode(['es' => $company->name]);
            
            DB::table('companies')
                ->where('id', $company->id)
                ->update([
                    'name' => $nameJson
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get all existing data
        $companies = DB::table('companies')->get();
        
        // Convert back to string column
        Schema::table('companies', function (Blueprint $table) {
            $table->string('name')->change();
        });
        
        // Update data back to string format
        foreach ($companies as $company) {
            $nameData = json_decode($company->name, true);
            $name = is_array($nameData) ? ($nameData['es'] ?? $nameData[array_key_first($nameData)] ?? '') : $company->name;
            
            DB::table('companies')
                ->where('id', $company->id)
                ->update([
                    'name' => $name
                ]);
        }
    }
};
