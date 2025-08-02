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
        // Índices para la tabla documents (más crítica)
        Schema::table('documents', function (Blueprint $table) {
            // Índice compuesto para búsquedas por empresa y estado
            $table->index(['company_id', 'status_id'], 'idx_documents_company_status');

            // Índice compuesto para búsquedas por empresa y categoría
            $table->index(['company_id', 'category_id'], 'idx_documents_company_category');

            // Índice compuesto para búsquedas por empresa y usuario asignado
            $table->index(['company_id', 'assigned_to'], 'idx_documents_company_assigned');

            // Índice compuesto para búsquedas por empresa y creador
            $table->index(['company_id', 'created_by'], 'idx_documents_company_creator');

            // Índice para documentos vencidos
            $table->index(['due_at', 'status_id'], 'idx_documents_due_status');

            // Índice para búsquedas por fecha de creación
            $table->index(['company_id', 'created_at'], 'idx_documents_company_created');

            // Índice para documentos confidenciales
            $table->index(['company_id', 'is_confidential'], 'idx_documents_company_confidential');

            // Índice para documentos archivados
            $table->index(['company_id', 'is_archived'], 'idx_documents_company_archived');

            // Índice para prioridad
            $table->index(['company_id', 'priority'], 'idx_documents_company_priority');
        });

        // Índices para la tabla users
        Schema::table('users', function (Blueprint $table) {
            // Índice compuesto para usuarios por empresa y departamento
            $table->index(['company_id', 'department_id'], 'idx_users_company_department');

            // Índice compuesto para usuarios por empresa y sucursal
            $table->index(['company_id', 'branch_id'], 'idx_users_company_branch');

            // Índice para usuarios activos
            $table->index(['company_id', 'is_active'], 'idx_users_company_active');

            // Índice para último login
            $table->index(['last_login_at'], 'idx_users_last_login');
        });

        // Índices para la tabla workflow_histories
        Schema::table('workflow_histories', function (Blueprint $table) {
            // Índice compuesto para historial por documento
            $table->index(['document_id', 'created_at'], 'idx_workflow_document_date');

            // Índice para búsquedas por usuario que realizó la acción
            $table->index(['performed_by', 'created_at'], 'idx_workflow_user_date');

            // Índice para transiciones por estado origen
            $table->index(['from_status_id', 'created_at'], 'idx_workflow_from_status');

            // Índice para transiciones por estado destino
            $table->index(['to_status_id', 'created_at'], 'idx_workflow_to_status');
        });

        // Índices para la tabla categories
        Schema::table('categories', function (Blueprint $table) {
            // Índice compuesto para categorías por empresa y padre
            $table->index(['company_id', 'parent_id'], 'idx_categories_company_parent');

            // Índice para categorías activas
            $table->index(['company_id', 'active'], 'idx_categories_company_active');
        });

        // Índices para la tabla tags
        Schema::table('tags', function (Blueprint $table) {
            // Índice para tags por empresa
            $table->index(['company_id', 'active'], 'idx_tags_company_active');

            // Índice para búsqueda por nombre
            $table->index(['company_id', 'name'], 'idx_tags_company_name');
        });

        // Índices para la tabla document_tags (tabla pivot)
        Schema::table('document_tags', function (Blueprint $table) {
            // Índice para búsquedas por documento
            $table->index(['document_id'], 'idx_document_tags_document');

            // Índice para búsquedas por tag
            $table->index(['tag_id'], 'idx_document_tags_tag');

            // Índice compuesto para la relación
            $table->index(['document_id', 'tag_id'], 'idx_document_tags_relation');
        });

        // Índices para la tabla statuses
        Schema::table('statuses', function (Blueprint $table) {
            // Índice para estados por empresa
            $table->index(['company_id', 'active'], 'idx_statuses_company_active');

            // Índice para estados iniciales
            $table->index(['company_id', 'is_initial'], 'idx_statuses_company_initial');

            // Índice para estados finales
            $table->index(['company_id', 'is_final'], 'idx_statuses_company_final');
        });

        // Índices para la tabla branches
        Schema::table('branches', function (Blueprint $table) {
            // Índice para sucursales por empresa
            $table->index(['company_id', 'active'], 'idx_branches_company_active');
        });

        // Índices para la tabla departments
        Schema::table('departments', function (Blueprint $table) {
            // Índice compuesto para departamentos por empresa y sucursal
            $table->index(['company_id', 'branch_id'], 'idx_departments_company_branch');

            // Índice para departamentos activos
            $table->index(['company_id', 'active'], 'idx_departments_company_active');
        });

        // Índices para la tabla document_versions
        Schema::table('document_versions', function (Blueprint $table) {
            // Índice para versiones por documento
            $table->index(['document_id', 'version_number'], 'idx_versions_document_number');

            // Índice para versión actual
            $table->index(['document_id', 'is_current'], 'idx_versions_document_current');

            // Índice para versiones por creador
            $table->index(['created_by', 'created_at'], 'idx_versions_creator_date');
        });

        // Índices para la tabla notifications (si existe)
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                // Índice para notificaciones por usuario
                $table->index(['notifiable_id', 'notifiable_type'], 'idx_notifications_notifiable');

                // Índice para notificaciones no leídas
                $table->index(['notifiable_id', 'read_at'], 'idx_notifications_unread');

                // Índice para notificaciones por fecha
                $table->index(['created_at'], 'idx_notifications_created');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar índices de documents
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex('idx_documents_company_status');
            $table->dropIndex('idx_documents_company_category');
            $table->dropIndex('idx_documents_company_assigned');
            $table->dropIndex('idx_documents_company_creator');
            $table->dropIndex('idx_documents_due_status');
            $table->dropIndex('idx_documents_company_created');
            $table->dropIndex('idx_documents_company_confidential');
            $table->dropIndex('idx_documents_company_archived');
            $table->dropIndex('idx_documents_company_priority');
        });

        // Eliminar índices de users
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_company_department');
            $table->dropIndex('idx_users_company_branch');
            $table->dropIndex('idx_users_company_active');
            $table->dropIndex('idx_users_last_login');
        });

        // Eliminar índices de workflow_histories
        Schema::table('workflow_histories', function (Blueprint $table) {
            $table->dropIndex('idx_workflow_document_date');
            $table->dropIndex('idx_workflow_user_date');
            $table->dropIndex('idx_workflow_from_status');
            $table->dropIndex('idx_workflow_to_status');
        });

        // Eliminar índices de categories
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('idx_categories_company_parent');
            $table->dropIndex('idx_categories_company_active');
        });

        // Eliminar índices de tags
        Schema::table('tags', function (Blueprint $table) {
            $table->dropIndex('idx_tags_company_active');
            $table->dropIndex('idx_tags_company_name');
        });

        // Eliminar índices de document_tags
        Schema::table('document_tags', function (Blueprint $table) {
            $table->dropIndex('idx_document_tags_document');
            $table->dropIndex('idx_document_tags_tag');
            $table->dropIndex('idx_document_tags_relation');
        });

        // Eliminar índices de statuses
        Schema::table('statuses', function (Blueprint $table) {
            $table->dropIndex('idx_statuses_company_active');
            $table->dropIndex('idx_statuses_company_initial');
            $table->dropIndex('idx_statuses_company_final');
        });

        // Eliminar índices de branches
        Schema::table('branches', function (Blueprint $table) {
            $table->dropIndex('idx_branches_company_active');
        });

        // Eliminar índices de departments
        Schema::table('departments', function (Blueprint $table) {
            $table->dropIndex('idx_departments_company_branch');
            $table->dropIndex('idx_departments_company_active');
        });

        // Eliminar índices de document_versions
        Schema::table('document_versions', function (Blueprint $table) {
            $table->dropIndex('idx_versions_document_number');
            $table->dropIndex('idx_versions_document_current');
            $table->dropIndex('idx_versions_creator_date');
        });

        // Eliminar índices de notifications
        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('idx_notifications_notifiable');
                $table->dropIndex('idx_notifications_unread');
                $table->dropIndex('idx_notifications_created');
            });
        }
    }
};
