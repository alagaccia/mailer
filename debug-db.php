<?php
require_once __DIR__ . '/bootstrap/app.php';

echo "=== DEBUG CREDENZIALI DATABASE ===\n\n";

echo "File .env esiste? " . (file_exists('.env') ? "SÌ" : "NO") . "\n";
echo "Percorso .env: " . realpath('.env') . "\n\n";

// Leggi il file .env
if (file_exists('.env')) {
    echo "--- Contenuto .env ---\n";
    $content = file_get_contents('.env');
    echo $content;
    echo "\n--- Fine contenuto ---\n\n";
}

echo "--- Variabili d'ambiente caricate ---\n";
echo "DB_HOST: " . (getenv('DB_HOST') ?: '(NON TROVATO)') . "\n";
echo "DB_NAME: " . (getenv('DB_NAME') ?: '(NON TROVATO)') . "\n";
echo "DB_USER: " . (getenv('DB_USER') ?: '(NON TROVATO)') . "\n";
echo "DB_PASS: " . (getenv('DB_PASS') ?: '(NON TROVATO)') . "\n";
echo "DB_PREFIX: " . (getenv('DB_PREFIX') ?: '(NON TROVATO)') . "\n";

echo "\n--- Tentativo connessione ---\n";
try {
    $host = getenv('DB_HOST');
    $db   = getenv('DB_NAME');
    $user = getenv('DB_USER');
    $pass = getenv('DB_PASS');
    
    echo "Connettendo a: mysql:host=$host;dbname=$db\n";
    echo "Con utente: $user\n";
    
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass
    );
    echo "✓ Connessione RIUSCITA!\n";
} catch (PDOException $e) {
    echo "✗ Connessione FALLITA!\n";
    echo "Errore: " . $e->getMessage() . "\n";
}
