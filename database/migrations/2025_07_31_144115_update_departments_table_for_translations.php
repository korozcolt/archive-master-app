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
        $departments = DB::table('departments')->get();
        
        // Convert name and description columns to JSON
        Schema::table('departments', function (Blueprint $table) {
            $table->json('name')->change();
            $table->json('description')->nullable()->change();
        });
        
        // Update existing data to JSON format with Spanish locale
        foreach ($departments as $department) {
            $nameJson = json_encode(['es' => $department->name]);
            $descriptionJson = $department->description ? json_encode(['es' => $department->description]) : null;
            
            DB::table('departments')
                ->where('id', $department->id)
                ->update([
                    'name' => $nameJson,
                    'description' => $descriptionJson
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get all existing data
        $departments = DB::table('departments')->get();
        
        // Convert back to string columns
        Schema::table('departments', function (Blueprint $table) {
            $table->string('name')->change();
            $table->text('description')->nullable()->change();
        });
        
        // Update data back to string format
        foreach ($departments as $department) {
            $nameData = json_decode($department->name, true);
            $descriptionData = json_decode($department->description, true);
            
            $name = is_array($nameData) ? ($nameData['es'] ?? $nameData[array_key_first($nameData)] ?? '') : $department->name;
            $description = is_array($descriptionData) ? ($descriptionData['es'] ?? $descriptionData[array_key_first($descriptionData)] ?? null) : $department->description;
            
            DB::table('departments')
                ->where('id', $department->id)
                ->update([
                    'name' => $name,
                    'description' => $description
                ]);
        }
    }
};
