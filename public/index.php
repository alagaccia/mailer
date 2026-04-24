<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_error.log');
error_reporting(E_ALL);

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

    $controller = new EmailController();
    $result = $controller->store($data);
    
    http_response_code($result['status']);
    echo json_encode($result['response']);
} else {
    // 3. GESTIONE METODO ERRATO
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed. Use POST.']);
}