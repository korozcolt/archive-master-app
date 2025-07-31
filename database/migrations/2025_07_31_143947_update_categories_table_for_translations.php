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
        $categories = DB::table('categories')->get();
        
        // Convert name and description columns to JSON
        Schema::table('categories', function (Blueprint $table) {
            $table->json('name')->change();
            $table->json('description')->nullable()->change();
        });
        
        // Update existing data to JSON format with Spanish locale
        foreach ($categories as $category) {
            $nameJson = json_encode(['es' => $category->name]);
            $descriptionJson = $category->description ? json_encode(['es' => $category->description]) : null;
            
            DB::table('categories')
                ->where('id', $category->id)
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
        $categories = DB::table('categories')->get();
        
        // Convert back to string columns
        Schema::table('categories', function (Blueprint $table) {
            $table->string('name')->change();
            $table->text('description')->nullable()->change();
        });
        
        // Update data back to string format
        foreach ($categories as $category) {
            $nameData = json_decode($category->name, true);
            $descriptionData = json_decode($category->description, true);
            
            $name = is_array($nameData) ? ($nameData['es'] ?? $nameData[array_key_first($nameData)] ?? '') : $category->name;
            $description = is_array($descriptionData) ? ($descriptionData['es'] ?? $descriptionData[array_key_first($descriptionData)] ?? null) : $category->description;
            
            DB::table('categories')
                ->where('id', $category->id)
                ->update([
                    'name' => $name,
                    'description' => $description
                ]);
        }
    }
};
