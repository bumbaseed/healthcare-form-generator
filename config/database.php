<?php
$config = [
    'host' => getenv('DB_HOST') ?: 'localhost',
    'port' => getenv('DB_PORT') ?: '5432',
    'database' => getenv('DB_NAME') ?: 'healthcare_generator',
    'username' => getenv('DB_USER') ?: 'postgres',
    'password' => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8',

    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false,
    ],
];

// Developer-local overrides, gitignored
if (is_file(__DIR__ . '/database.local.php')) {
    $local = require __DIR__ . '/database.local.php';
    if (is_array($local)) {
        $config = array_merge($config, $local);
    }
}

return $config;
