<?php
require_once __DIR__ . '/bootstrap/app.php';

use App\Core\Database;

// Controllo se siamo in CLI (per sicurezza)
if (php_sapi_name() !== 'cli') {
    die("Questo comando può essere eseguito solo da terminale.\n");
}

$db = Database::getInstance();

echo "--- Inizio Seeding ---\n";

$seedersPath = __DIR__ . '/database/seeders/';
$files = scandir($seedersPath);

foreach ($files as $file) {
    if ($file === '.' || $file === '..') continue;

    echo "Eseguendo: $file... ";

    try {
        $seeder = require $seedersPath . $file;
        $seeder->run($db);
        echo "SUCCESS\n";
    } catch (PDOException $e) {
        echo "ERRORE: " . $e->getMessage() . "\n";
    }
}

echo "--- Seeding Completato ---\n";
