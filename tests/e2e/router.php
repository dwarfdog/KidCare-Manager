<?php

$projectDirectory = dirname(__DIR__, 2);
$publicDirectory = $projectDirectory . DIRECTORY_SEPARATOR . 'public';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestedFile = realpath($publicDirectory . DIRECTORY_SEPARATOR . ltrim((string) $requestPath, '/'));

if ($requestedFile !== false
    && str_starts_with($requestedFile, $publicDirectory . DIRECTORY_SEPARATOR)
    && is_file($requestedFile)
) {
    return false;
}

$databasePath = str_replace('\\', '/', $projectDirectory . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'e2e.db');
$environment = [
    'APP_ENV' => 'test',
    'APP_DEBUG' => '0',
    'DATABASE_URL' => 'sqlite:///' . $databasePath,
    'BANK_ENCRYPTION_KEY' => 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=',
    'MESSENGER_TRANSPORT_DSN' => 'doctrine://default?auto_setup=0',
];

foreach ($environment as $name => $value) {
    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$frontController = $publicDirectory . DIRECTORY_SEPARATOR . 'index.php';
$_SERVER['SCRIPT_FILENAME'] = $frontController;

require $frontController;
