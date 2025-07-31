<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración del Sistema de Documentos
    |--------------------------------------------------------------------------
    |
    | Aquí puedes configurar varios aspectos del sistema de gestión de documentos
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Configuración de Archivos
    |--------------------------------------------------------------------------
    */
    'files' => [
        // Tamaño máximo de archivo en MB
        'max_size' => env('DOCUMENT_MAX_FILE_SIZE', 10),
        
        // Tipos de archivo permitidos
        'allowed_types' => [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'rtf', 'odt', 'ods', 'odp',
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg',
            'zip', 'rar', '7z'
        ],
        
        // Directorio de almacenamiento
        'storage_path' => env('DOCUMENT_STORAGE_PATH', 'documents'),
        
        // Usar almacenamiento en la nube
        'use_cloud_storage' => env('DOCUMENT_USE_CLOUD_STORAGE', false),
        
        // Disco de almacenamiento
        'storage_disk' => env('DOCUMENT_STORAGE_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Workflow
    |--------------------------------------------------------------------------
    */
    'workflow' => [
        // Habilitar transiciones automáticas
        'auto_transitions' => env('WORKFLOW_AUTO_TRANSITIONS', true),
        
        // Tiempo máximo para transiciones automáticas (en minutos)
        'auto_transition_timeout' => env('WORKFLOW_AUTO_TRANSITION_TIMEOUT', 60),
        
        // Habilitar notificaciones de workflow
        'notifications_enabled' => env('WORKFLOW_NOTIFICATIONS_ENABLED', true),
        
        // Estados finales (no se pueden cambiar)
        'final_statuses' => [
            'completed', 'approved', 'rejected', 'cancelled', 'archived'
        ],
        
        // Estados iniciales (se pueden eliminar)
        'initial_statuses' => [
            'draft', 'pending', 'created', 'new'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de SLA
    |--------------------------------------------------------------------------
    */
    'sla' => [
        // Habilitar monitoreo de SLA
        'enabled' => env('SLA_MONITORING_ENABLED', true),
        
        // Tiempo por defecto de SLA (en horas)
        'default_hours' => env('SLA_DEFAULT_HOURS', 72),
        
        // Notificar cuando queden X horas para vencer
        'warning_hours' => env('SLA_WARNING_HOURS', 24),
        
        // Notificar cuando esté vencido por X horas
        'overdue_notification_hours' => env('SLA_OVERDUE_NOTIFICATION_HOURS', 24),
        
        // Escalamiento automático cuando esté vencido
        'auto_escalation' => env('SLA_AUTO_ESCALATION', false),
        
        // Horas para escalamiento automático
        'escalation_hours' => env('SLA_ESCALATION_HOURS', 48),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Notificaciones
    |--------------------------------------------------------------------------
    */
    'notifications' => [
        // Canales de notificación habilitados
        'channels' => [
            'database' => env('NOTIFICATIONS_DATABASE', true),
            'mail' => env('NOTIFICATIONS_MAIL', true),
            'slack' => env('NOTIFICATIONS_SLACK', false),
        ],
        
        // Eventos que generan notificaciones
        'events' => [
            'document_created' => env('NOTIFY_DOCUMENT_CREATED', true),
            'document_updated' => env('NOTIFY_DOCUMENT_UPDATED', false),
            'status_changed' => env('NOTIFY_STATUS_CHANGED', true),
            'document_assigned' => env('NOTIFY_DOCUMENT_ASSIGNED', true),
            'document_overdue' => env('NOTIFY_DOCUMENT_OVERDUE', true),
            'sla_warning' => env('NOTIFY_SLA_WARNING', true),
        ],
        
        // Retrasar notificaciones no críticas (en minutos)
        'delay_non_critical' => env('NOTIFICATIONS_DELAY_NON_CRITICAL', 15),
        
        // Agrupar notificaciones similares
        'group_similar' => env('NOTIFICATIONS_GROUP_SIMILAR', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Reportes
    |--------------------------------------------------------------------------
    */
    'reports' => [
        // Habilitar generación automática de reportes
        'auto_generation' => env('REPORTS_AUTO_GENERATION', false),
        
        // Frecuencia de reportes automáticos
        'auto_frequency' => env('REPORTS_AUTO_FREQUENCY', 'monthly'), // daily, weekly, monthly
        
        // Formatos de exportación disponibles
        'export_formats' => ['pdf', 'excel', 'csv'],
        
        // Retener reportes por X días
        'retention_days' => env('REPORTS_RETENTION_DAYS', 90),
        
        // Directorio de almacenamiento de reportes
        'storage_path' => env('REPORTS_STORAGE_PATH', 'reports'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Seguridad
    |--------------------------------------------------------------------------
    */
    'security' => [
        // Habilitar auditoría de accesos
        'audit_access' => env('DOCUMENT_AUDIT_ACCESS', true),
        
        // Habilitar cifrado de archivos sensibles
        'encrypt_files' => env('DOCUMENT_ENCRYPT_FILES', false),
        
        // Requerir autenticación de dos factores para acciones críticas
        'require_2fa_critical' => env('DOCUMENT_REQUIRE_2FA_CRITICAL', false),
        
        // Tiempo de sesión para documentos sensibles (en minutos)
        'sensitive_session_timeout' => env('DOCUMENT_SENSITIVE_SESSION_TIMEOUT', 30),
        
        // Marcar como sensibles documentos con estas categorías
        'sensitive_categories' => [
            'confidential', 'restricted', 'classified'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Performance
    |--------------------------------------------------------------------------
    */
    'performance' => [
        // Habilitar caché de consultas
        'cache_queries' => env('DOCUMENT_CACHE_QUERIES', true),
        
        // Tiempo de caché en minutos
        'cache_ttl' => env('DOCUMENT_CACHE_TTL', 60),
        
        // Paginación por defecto
        'default_pagination' => env('DOCUMENT_DEFAULT_PAGINATION', 25),
        
        // Máximo de elementos por página
        'max_pagination' => env('DOCUMENT_MAX_PAGINATION', 100),
        
        // Habilitar compresión de archivos
        'compress_files' => env('DOCUMENT_COMPRESS_FILES', false),
        
        // Generar miniaturas para imágenes
        'generate_thumbnails' => env('DOCUMENT_GENERATE_THUMBNAILS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Integración
    |--------------------------------------------------------------------------
    */
    'integrations' => [
        // Habilitar API externa
        'api_enabled' => env('DOCUMENT_API_ENABLED', true),
        
        // Webhook para eventos de documentos
        'webhook_url' => env('DOCUMENT_WEBHOOK_URL', null),
        
        // Eventos que disparan webhooks
        'webhook_events' => [
            'document.created',
            'document.status_changed',
            'document.completed'
        ],
        
        // Integración con sistemas externos
        'external_systems' => [
            'erp_enabled' => env('INTEGRATION_ERP_ENABLED', false),
            'crm_enabled' => env('INTEGRATION_CRM_ENABLED', false),
            'accounting_enabled' => env('INTEGRATION_ACCOUNTING_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de Limpieza
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        // Eliminar documentos eliminados después de X días
        'delete_after_days' => env('DOCUMENT_DELETE_AFTER_DAYS', 30),
        
        // Archivar documentos completados después de X días
        'archive_after_days' => env('DOCUMENT_ARCHIVE_AFTER_DAYS', 365),
        
        // Limpiar logs de actividad después de X días
        'cleanup_activity_logs_days' => env('DOCUMENT_CLEANUP_ACTIVITY_LOGS_DAYS', 90),
        
        // Limpiar archivos temporales
        'cleanup_temp_files' => env('DOCUMENT_CLEANUP_TEMP_FILES', true),
        
        // Frecuencia de limpieza automática
        'cleanup_frequency' => env('DOCUMENT_CLEANUP_FREQUENCY', 'daily'), // daily, weekly
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración de UI
    |--------------------------------------------------------------------------
    */
    'ui' => [
        // Tema por defecto
        'default_theme' => env('DOCUMENT_UI_THEME', 'light'),
        
        // Mostrar ayuda contextual
        'show_help' => env('DOCUMENT_UI_SHOW_HELP', true),
        
        // Habilitar modo compacto
        'compact_mode' => env('DOCUMENT_UI_COMPACT_MODE', false),
        
        // Elementos por página en listas
        'items_per_page' => env('DOCUMENT_UI_ITEMS_PER_PAGE', 25),
        
        // Habilitar vista previa de documentos
        'enable_preview' => env('DOCUMENT_UI_ENABLE_PREVIEW', true),
        
        // Formato de fecha por defecto
        'date_format' => env('DOCUMENT_UI_DATE_FORMAT', 'd/m/Y H:i'),
    ],

];