<?php

class DefaultConfig {
    public static $settings = [
        // secure the dashboard with a login and password
        'SECURE_LOGIN' => false,

        'database' => [
            'type' => 'sqlite', // sqlite or mysql
            'mysql' => [
                'host' => '192.168.0.90',
                'dbname' => 'agile_dashboard',
                'username' => 'agile_dashboard_user',
                'password' => 'agile_dashboard_password',
                'charset' => 'utf8mb4'
            ],
            
            'sqlite' => [
                'path' => '/database/database.sqlite' // or ':memory:' for in-memory DB
            ]
        ],


        // Security settings
        'SESSION_NAME' => 'AHD_SESSION',
        'SESSION_LIFETIME' => 86400, // 24 hours
        'TOKEN_LIFETIME' => 2592000, // 30 days
        'COOKIE_PATH' => '/',
        'COOKIE_DOMAIN' => "", // Set to your domain
        'COOKIE_SECURE' => true, // HTTPS only
        'COOKIE_HTTPONLY' => true,

        'TUYA_SUPPORTED_PRODUCTS' => [
            'Smart Plug',
            'Smart Switch'
        ],
        'TUYA_PAGE_SIZE' => '50', // 50 devices is max on free accounts
        'TUYA_AUTO_REFRESH_INTERVAL' => 30, // in seconds, increase to reduce API calls 30,000 on free accounts
    ];
}