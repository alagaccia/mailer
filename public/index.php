<?php

require_once __DIR__ . '/../bootstrap/app.php';

use App\Controllers\EmailController;

header('Content-Type: application/json');

/**
 * 1. FIX HEADER PER SERVERPLAN
 * Su molti server Apache, l'header X-API-KEY non viene inserito in $_SERVER.
 * Questo blocco assicura che venga trovato ovunque sia nascosto.
 */
$headers = getallheaders();
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? ($headers['X-API-KEY'] ?? ($headers['x-api-key'] ?? ''));

if ($apiKey !== getenv('API_KEY')) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized',
        'debug' => 'Header non trovato o chiave errata' // Rimuovi il debug dopo i test
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 2. CONTROLLO JSON MALFORMATO
    $jsonInput = file_get_contents('php://input');
    $data = json_decode($jsonInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Malformed JSON',
            'details' => json_last_error_msg()
        ]);
        exit;
    }

    try {
        $controller = new EmailController();
        $result = $controller->store($data);
        
        http_response_code($result['status']);
        echo json_encode($result['response']);
    } catch (\Throwable $e) {
        $logFile = __DIR__ . '/../api_errors.log';
        $logMessage = "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
        $logMessage .= "Stack trace:\n" . $e->getTraceAsString() . "\n";
        $logMessage .= "Request data: " . $jsonInput . "\n";
        $logMessage .= str_repeat("-", 80) . "\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);

        http_response_code(500);
        echo json_encode([
            'error' => 'Internal Server Error',
            'message' => 'An unexpected error occurred. Check the logs.'
        ]);
    }
} else {
    // 3. GESTIONE METODO ERRATO
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Use POST.']);
}