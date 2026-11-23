<?php
declare(strict_types=1);

/**
 * Пример конфигурации. Рабочий файл config/config.php создаётся установщиком
 * и не попадает в Git (см. .gitignore).
 */
return [
    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'mahmadgv_piz',
        'user' => 'mahmadgv_piz',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'debug' => false,
];
