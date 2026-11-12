<?php
declare(strict_types=1);

return [
    'db' => [
        'host' => '127.0.0.1',
        'name' => 'bl',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',

//         'host' => 'sql300.infinityfree.com',
//         'name' => 'if0_43330617_bl',
//         'user' => 'if0_43330617',
//         'pass' => '01716488107sS',
//         'charset' => 'utf8mb4',
    ],
    'base_url' => 'http://localhost/BloodLink_2/public',
    //  'base_url' => 'https://bloodlink.free.nf/public',
    'timezone' => 'Asia/Dhaka',
    'debug' => true,
    'upload_max' => 2 * 1024 * 1024,
    'session_name' => 'bloodlink_session',
    'session_secure' => false,
    'default_lang' => 'bn',
];
