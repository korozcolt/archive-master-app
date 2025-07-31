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
        $tags = DB::table('tags')->get();
        
        // Convert name and description columns to JSON
        Schema::table('tags', function (Blueprint $table) {
            $table->json('name')->change();
            $table->json('description')->nullable()->change();
        });
        
        // Update existing data to JSON format with Spanish locale
        foreach ($tags as $tag) {
            $nameJson = json_encode(['es' => $tag->name]);
            $descriptionJson = $tag->description ? json_encode(['es' => $tag->description]) : null;
            
            DB::table('tags')
                ->where('id', $tag->id)
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
        $tags = DB::table('tags')->get();
        
        // Convert back to string columns
        Schema::table('tags', function (Blueprint $table) {
            $table->string('name')->change();
            $table->text('description')->nullable()->change();
        });
        
        // Update data back to string format
        foreach ($tags as $tag) {
            $nameData = json_decode($tag->name, true);
            $descriptionData = json_decode($tag->description, true);
            
            $name = is_array($nameData) ? ($nameData['es'] ?? $nameData[array_key_first($nameData)] ?? '') : $tag->name;
            $description = is_array($descriptionData) ? ($descriptionData['es'] ?? $descriptionData[array_key_first($descriptionData)] ?? null) : $tag->description;
            
            DB::table('tags')
                ->where('id', $tag->id)
                ->update([
                    'name' => $name,
                    'description' => $description
                ]);
        }
    }
};
