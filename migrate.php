<?php
require_once __DIR__ . '/bootstrap/app.php';

use App\Core\Database;

// Controllo se siamo in CLI (per sicurezza)
if (php_sapi_name() !== 'cli') {
    die("Questo comando può essere eseguito solo da terminale.\n");
}

$db = Database::getInstance();

echo "--- Inizio Migrazione ---\n";

$migrationsPath = __DIR__ . '/database/migrations/';
$files = scandir($migrationsPath);

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;

    echo "Eseguendo: $file... ";

    try {
        $sql = require $migrationsPath . $file;
        $db->exec($sql);
        echo "SUCCESS\n";
    } catch (PDOException $e) {
        echo "ERRORE: " . $e->getMessage() . "\n";
    }
}

echo "--- Migrazione Completata ---\n";