<?php

$CONFIG = array (
    'SECURE_LOGIN' => false,
    'database' => array (
        'type' => 'sqlite',
        'mysql' => array (
            'host' => '172.17.0.1',
            'dbname' => 'agile_dashboard',
            'username' => 'agile_dashboard_user',
            'password' => 'agile_dashboard_password',
            'charset' => 'utf8mb4'
        ),
        'sqlite' => array (
            'path' => '/database/database.sqlite'
        )
    )
);