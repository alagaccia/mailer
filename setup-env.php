<?php

/**
 * Script per la configurazione iniziale del progetto.
 * Copia .env.example in .env e genera una API_KEY casuale.
 */

$root = __DIR__;
$exampleFile = $root . '/.env.example';
$envFile = $root . '/.env';

if (!file_exists($exampleFile)) {
    echo "Errore: .env.example non trovato.\n";
    exit(1);
}

if (file_exists($envFile)) {
    echo ".env esiste già. Salto la copia.\n";
} else {
    if (copy($exampleFile, $envFile)) {
        echo ".env creato correttamente da .env.example.\n";
    } else {
        echo "Errore durante la copia di .env.example.\n";
        exit(1);
    }
}

// Genera API_KEY se non presente o vuota
$envContent = file_get_contents($envFile);
if (preg_match('/API_KEY=\s*$/m', $envContent) || !preg_match('/API_KEY=./', $envContent)) {
    $apiKey = bin2hex(random_bytes(32));
    $envContent = preg_replace('/^API_KEY=.*$/m', "API_KEY=$apiKey", $envContent);
    file_put_contents($envFile, $envContent);
    echo "API_KEY generata e salvata in .env.\n";
} else {
    echo "API_KEY già presente in .env.\n";
}

// Creazione cartelle di sistema e log
$directories = [
    $root . '/storage',
    $root . '/storage/logs'
];

foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "Cartella creata: $dir\n";
        }
    }
    // Assicuriamoci che i permessi siano corretti (0777 per permettere a PHP/Apache di scrivere)
    chmod($dir, 0777);
}

// Creazione file log
$logFile = $root . '/storage/logs/app.log';
if (!file_exists($logFile)) {
    touch($logFile);
    echo "File log creato: $logFile\n";
}
chmod($logFile, 0666);

echo "Configurazione permessi completata.\n";
