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
        $statuses = DB::table('statuses')->get();
        
        // Convert name and description columns to JSON
        Schema::table('statuses', function (Blueprint $table) {
            $table->json('name')->change();
            $table->json('description')->nullable()->change();
        });
        
        // Update existing data to JSON format with Spanish locale
        foreach ($statuses as $status) {
            $nameJson = json_encode(['es' => $status->name]);
            $descriptionJson = $status->description ? json_encode(['es' => $status->description]) : null;
            
            DB::table('statuses')
                ->where('id', $status->id)
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
        $statuses = DB::table('statuses')->get();
        
        // Convert back to string columns
        Schema::table('statuses', function (Blueprint $table) {
            $table->string('name')->change();
            $table->text('description')->nullable()->change();
        });
        
        // Update data back to string format
        foreach ($statuses as $status) {
            $nameData = json_decode($status->name, true);
            $descriptionData = json_decode($status->description, true);
            
            $name = is_array($nameData) ? ($nameData['es'] ?? $nameData[array_key_first($nameData)] ?? '') : $status->name;
            $description = is_array($descriptionData) ? ($descriptionData['es'] ?? $descriptionData[array_key_first($descriptionData)] ?? null) : $status->description;
            
            DB::table('statuses')
                ->where('id', $status->id)
                ->update([
                    'name' => $name,
                    'description' => $description
                ]);
        }
    }
};
