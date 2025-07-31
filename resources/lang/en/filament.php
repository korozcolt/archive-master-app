<?php

return [
    /*
    |--------------------------------------------------------------------------
    | General translations
    |--------------------------------------------------------------------------
    |
    | Here you can define general translations for the Filament interface
    |
    */
    
    // Common actions
    'actions' => [
        'create' => 'Create',
        'edit' => 'Edit',
        'view' => 'View',
        'delete' => 'Delete',
        'save' => 'Save',
        'cancel' => 'Cancel',
        'confirm' => 'Confirm',
        'back' => 'Back',
        'search' => 'Search',
        'filter' => 'Filter',
        'reset' => 'Reset',
        'download' => 'Download',
        'upload' => 'Upload',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'reset_password' => 'Reset password',
    ],
    
    // Interface components
    'interface' => [
        'search_placeholder' => 'Search...',
        'no_results' => 'No results found',
        'loading' => 'Loading...',
        'select_placeholder' => 'Select an option',
        'toggle_navigation' => 'Toggle navigation',
        'toggle_sidebar' => 'Toggle sidebar',
        'toggle_dark_mode' => 'Toggle dark mode',
        'toggle_light_mode' => 'Toggle light mode',
    ],
    
    // Confirmation messages
    'confirmation' => [
        'title' => 'Are you sure?',
        'content' => 'This action cannot be undone.',
        'confirm' => 'Yes, continue',
        'cancel' => 'No, cancel',
    ],
    
    // Notification messages
    'notification' => [
        'created' => 'Created successfully',
        'updated' => 'Updated successfully',
        'deleted' => 'Deleted successfully',
        'error' => 'An error has occurred',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | User administration
    |--------------------------------------------------------------------------
    |
    | Specific translations for user administration
    |
    */
    
    // User resource
    'resources' => [
        'user' => [
            'label' => 'User',
            'plural_label' => 'Users',
            'navigation_label' => 'Users',
            'navigation_group' => 'Administration',
            'columns' => [
                'profile_photo' => 'Photo',
                'name' => 'Name',
                'email' => 'Email',
                'position' => 'Position',
                'company' => 'Company',
                'branch' => 'Branch',
                'department' => 'Department',
                'roles' => 'Roles',
                'is_active' => 'Active',
                'created_at' => 'Created at',
                'last_login_at' => 'Last login',
            ],
            'placeholders' => [
                'enter_key' => 'Enter a key',
                'enter_value' => 'Enter a value',
            ],
        ],
    ],
    
    // User fields
    'fields' => [
        'user' => [
            'name' => 'Name',
            'email' => 'Email',
            'position' => 'Position',
            'phone' => 'Phone',
            'company' => 'Company',
            'branch' => 'Branch',
            'department' => 'Department',
            'password' => 'Password',
            'password_confirmation' => 'Confirm password',
            'roles' => 'Roles',
            'profile_photo' => 'Profile photo',
            'is_active' => 'Active user',
            'language' => 'Language',
            'timezone' => 'Timezone',
            'settings' => 'Additional settings',
            'settings_key' => 'Key',
            'settings_value' => 'Value',
            'new_password' => 'New password',
            'created_at' => 'Created at',
            'last_login_at' => 'Last login',
        ],
    ],
    
    // Form sections
    'sections' => [
        'user' => [
            'personal_info' => 'Personal information',
            'organizational_assignment' => 'Organizational assignment',
            'access_security' => 'Access and security',
            'image_status' => 'Image and status',
            'preferences' => 'Preferences',
        ],
    ],
    
    // User-specific actions
    'user_actions' => [
        'reset_password' => 'Reset password',
        'activate_users' => 'Activate users',
        'deactivate_users' => 'Deactivate users',
    ],
    
    // User filters
    'filters' => [
        'user' => [
            'active_users' => 'Active users',
            'company' => 'Company',
            'branch' => 'Branch',
            'department' => 'Department',
            'role' => 'Role',
        ],
    ],
];