<?php
// Basic app configuration
return [
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'becca_stock',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'name' => 'Becca Stock Management',
        'base_url' => '/',
        'session_name' => 'becca_session',
        'timezone' => 'UTC',
    ],
];

