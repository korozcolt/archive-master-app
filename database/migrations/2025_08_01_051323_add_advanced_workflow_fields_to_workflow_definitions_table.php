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
        Schema::table('workflow_definitions', function (Blueprint $table) {
            // Campos para timeouts y escalamiento
            $table->integer('timeout_hours')->nullable()->after('sla_hours');
            $table->integer('auto_escalation_hours')->nullable()->after('timeout_hours');
            
            // Campos para notificaciones personalizadas
            $table->json('custom_notifications')->nullable()->after('auto_escalation_hours');
            
            // Campos para validaciones personalizadas
            $table->json('custom_validations')->nullable()->after('custom_notifications');
            
            // Campo para delegación automática por ausencias
            $table->boolean('auto_delegate_on_absence')->default(false)->after('custom_validations');
            
            // Campo para hooks personalizados
            $table->json('custom_hooks')->nullable()->after('auto_delegate_on_absence');
            
            // Campo para reglas de escalamiento
            $table->json('escalation_rules')->nullable()->after('custom_hooks');
            
            // Campo para configuración de aprobaciones
            $table->json('approval_config')->nullable()->after('escalation_rules');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_definitions', function (Blueprint $table) {
            $table->dropColumn([
                'timeout_hours',
                'auto_escalation_hours',
                'custom_notifications',
                'custom_validations',
                'auto_delegate_on_absence',
                'custom_hooks',
                'escalation_rules',
                'approval_config'
            ]);
        });
    }
};
