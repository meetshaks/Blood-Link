<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'bloodlink',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'base_url' => 'http://localhost/BloodLink/public',
    'timezone' => 'Asia/Dhaka',
    'debug' => true,
    'upload_max' => 2 * 1024 * 1024,
    'session_name' => 'bloodlink_session',
    'session_secure' => false,
    'default_lang' => 'bn',
];
