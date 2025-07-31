<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Traducciones generales
    |--------------------------------------------------------------------------
    |
    | Aquí se definen las traducciones generales para la interfaz de Filament
    |
    */
    
    // Acciones comunes
    'actions' => [
        'create' => 'Crear',
        'edit' => 'Editar',
        'view' => 'Ver',
        'delete' => 'Eliminar',
        'save' => 'Guardar',
        'cancel' => 'Cancelar',
        'confirm' => 'Confirmar',
        'back' => 'Volver',
        'search' => 'Buscar',
        'filter' => 'Filtrar',
        'reset' => 'Restablecer',
        'download' => 'Descargar',
        'upload' => 'Subir',
        'activate' => 'Activar',
        'deactivate' => 'Desactivar',
        'reset_password' => 'Restablecer contraseña',
    ],
    
    // Componentes de interfaz
    'interface' => [
        'search_placeholder' => 'Buscar...',
        'no_results' => 'No se encontraron resultados',
        'loading' => 'Cargando...',
        'select_placeholder' => 'Seleccione una opción',
        'toggle_navigation' => 'Alternar navegación',
        'toggle_sidebar' => 'Alternar barra lateral',
        'toggle_dark_mode' => 'Alternar modo oscuro',
        'toggle_light_mode' => 'Alternar modo claro',
    ],
    
    // Mensajes de confirmación
    'confirmation' => [
        'title' => '¿Está seguro?',
        'content' => 'Esta acción no se puede deshacer.',
        'confirm' => 'Sí, continuar',
        'cancel' => 'No, cancelar',
    ],
    
    // Mensajes de notificación
    'notification' => [
        'created' => 'Creado correctamente',
        'updated' => 'Actualizado correctamente',
        'deleted' => 'Eliminado correctamente',
        'error' => 'Ha ocurrido un error',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Administración de usuarios
    |--------------------------------------------------------------------------
    |
    | Traducciones específicas para la administración de usuarios
    |
    */
    
    // Recursos
    'resources' => [
        'user' => [
            'label' => 'Usuario',
            'plural_label' => 'Usuarios',
            'navigation_label' => 'Usuarios',
            'navigation_group' => 'Administración',
            'columns' => [
                'profile_photo' => 'Foto',
                'name' => 'Nombre',
                'email' => 'Correo electrónico',
                'position' => 'Cargo',
                'company' => 'Empresa',
                'branch' => 'Sucursal',
                'department' => 'Departamento',
                'roles' => 'Roles',
                'is_active' => 'Activo',
                'created_at' => 'Creado el',
                'last_login_at' => 'Último acceso',
            ],
            'placeholders' => [
                'enter_key' => 'Ingrese una clave',
                'enter_value' => 'Ingrese un valor',
            ],
        ],
    ],
    
    // Campos de usuario
    'fields' => [
        'user' => [
            'name' => 'Nombre',
            'email' => 'Correo electrónico',
            'position' => 'Cargo',
            'phone' => 'Teléfono',
            'company' => 'Empresa',
            'branch' => 'Sucursal',
            'department' => 'Departamento',
            'password' => 'Contraseña',
            'password_confirmation' => 'Confirmar contraseña',
            'roles' => 'Roles',
            'profile_photo' => 'Foto de perfil',
            'is_active' => 'Usuario activo',
            'language' => 'Idioma',
            'timezone' => 'Zona horaria',
            'settings' => 'Configuración adicional',
            'settings_key' => 'Clave',
            'settings_value' => 'Valor',
            'new_password' => 'Nueva contraseña',
            'created_at' => 'Creado el',
            'last_login_at' => 'Último acceso',
        ],
    ],
    
    // Secciones de formulario
    'sections' => [
        'user' => [
            'personal_info' => 'Información personal',
            'organizational_assignment' => 'Asignación organizacional',
            'access_security' => 'Acceso y seguridad',
            'image_status' => 'Imagen y estado',
            'preferences' => 'Preferencias',
        ],
    ],
    
    // Acciones específicas de usuario
    'user_actions' => [
        'reset_password' => 'Restablecer contraseña',
        'activate_users' => 'Activar usuarios',
        'deactivate_users' => 'Desactivar usuarios',
    ],
    
    // Filtros de usuario
    'filters' => [
        'user' => [
            'active_users' => 'Usuarios activos',
            'company' => 'Empresa',
            'branch' => 'Sucursal',
            'department' => 'Departamento',
            'role' => 'Rol',
        ],
    ],
    
    // Recursos de usuario
    'user' => [
        'label' => 'Usuario',
        'plural_label' => 'Usuarios',
        'navigation_label' => 'Usuarios',
        'navigation_group' => 'Administración',
        'columns' => [
            'profile_photo' => 'Foto',
            'name' => 'Nombre',
            'email' => 'Correo electrónico',
            'position' => 'Cargo',
            'company' => 'Empresa',
            'branch' => 'Sucursal',
            'department' => 'Departamento',
            'roles' => 'Roles',
            'is_active' => 'Activo',
            'created_at' => 'Creado el',
            'last_login_at' => 'Último acceso',
        ],
        'placeholders' => [
            'enter_key' => 'Ingrese una clave',
            'enter_value' => 'Ingrese un valor',
        ],
    ],
];