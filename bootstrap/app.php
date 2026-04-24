<?php
// Carica l'autoloader di Composer (gestisce PHPMailer e le tue classi in app/)
require_once __DIR__ . '/../vendor/autoload.php';

// Carica variabili da .env
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            putenv(trim($parts[0]) . "=" . trim($parts[1]));
        }
    }
}

/**
 * Helper globale per accedere alla configurazione (stile Laravel)
 */
function config($key, $default = null) {
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config/app.php';
    }
    
    // Semplificato: accede solo al primo livello dell'array
    $parts = explode('.', $key);
    $main = $parts[0];
    $sub = $parts[1] ?? null;

    if ($sub && isset($config[$main][$sub])) {
        return $config[$main][$sub];
    }
    
    return $config[$main] ?? $default;
}