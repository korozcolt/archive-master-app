<?php

$config = require base_path('vendor/opcodesio/log-viewer/config/log-viewer.php');

$config['route_path'] = env('LOG_VIEWER_ROUTE_PATH', 'admin/logs');
$config['require_auth_in_production'] = true;
$config['enabled'] = env('LOG_VIEWER_ENABLED', true);
$config['back_to_system_url'] = '/admin';
$config['back_to_system_label'] = 'Volver al administrador';

$config['middleware'] = [
    'web',
    'auth',
    \Opcodes\LogViewer\Http\Middleware\AuthorizeLogViewer::class,
];

$config['api_middleware'] = [
    'web',
    'auth',
    \Opcodes\LogViewer\Http\Middleware\AuthorizeLogViewer::class,
];

return $config;
