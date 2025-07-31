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
        $branches = DB::table('branches')->get();
        
        // Convert name column to JSON
        Schema::table('branches', function (Blueprint $table) {
            $table->json('name')->change();
        });
        
        // Update existing data to JSON format with Spanish locale
        foreach ($branches as $branch) {
            $nameJson = json_encode(['es' => $branch->name]);
            
            DB::table('branches')
                ->where('id', $branch->id)
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
        $branches = DB::table('branches')->get();
        
        // Convert back to string column
        Schema::table('branches', function (Blueprint $table) {
            $table->string('name')->change();
        });
        
        // Update data back to string format
        foreach ($branches as $branch) {
            $nameData = json_decode($branch->name, true);
            $name = is_array($nameData) ? ($nameData['es'] ?? $nameData[array_key_first($nameData)] ?? '') : $branch->name;
            
            DB::table('branches')
                ->where('id', $branch->id)
                ->update([
                    'name' => $name
                ]);
        }
    }
};
