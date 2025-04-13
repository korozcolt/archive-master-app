<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Referencias a otras tablas
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();

            // Campos adicionales de usuario
            $table->string('position')->nullable()->after('email');
            $table->string('phone')->nullable()->after('position');
            $table->string('profile_photo')->nullable()->after('phone');
            $table->string('language')->default('es')->after('profile_photo');
            $table->string('timezone')->default('America/Bogota')->after('language');
            $table->json('settings')->nullable()->after('timezone');
            $table->timestamp('last_login_at')->nullable()->after('settings');
            $table->boolean('is_active')->default(true)->after('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['department_id']);

            $table->dropColumn([
                'company_id',
                'branch_id',
                'department_id',
                'position',
                'phone',
                'profile_photo',
                'language',
                'timezone',
                'settings',
                'last_login_at',
                'is_active'
            ]);
        });
    }
};
