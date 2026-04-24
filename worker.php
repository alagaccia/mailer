<?php
require_once __DIR__ . '/bootstrap/app.php';

use App\Models\EmailQueue;
use App\Core\Mailer;

// Funzione di logging per capire cosa succede
function logWorker($message) {
    $logPath = __DIR__ . '/storage/logs/worker.log';
    $date = date('Y-m-d H:i:s');
    // Crea la cartella se non esiste
    if (!is_dir(__DIR__ . '/storage/logs')) {
        mkdir(__DIR__ . '/storage/logs', 0777, true);
    }
    file_put_contents($logPath, "[$date] $message" . PHP_EOL, FILE_APPEND);
}

logWorker("Avvio worker...");

try {
    $queue = new EmailQueue();
    $mailer = new Mailer();

    // Recupera le mail in attesa
    $tasks = $queue->getPending(50);

    if (empty($tasks)) {
        logWorker("Nessuna mail da inviare.");
        exit;
    }

    foreach ($tasks as $task) {
        try {
            logWorker("Invio a: " . $task['recipient']);
            
            // Decodifica gli allegati se presenti
            $attachments = !empty($task['attachments']) ? json_decode($task['attachments'], true) : [];
            
            // Il metodo send deve restituire true o una stringa di errore
            $result = $mailer->send($task['recipient'], $task['subject'], $task['body'], $attachments);
            
            if ($result === true) {
                $queue->updateStatus($task['id'], 'sent');
                logWorker("ID {$task['id']}: Inviata correttamente.");
            } else {
                // Se $result non è true, è l'errore restituito da PHPMailer
                $queue->updateStatus($task['id'], 'failed', $result);
                logWorker("ID {$task['id']}: Fallita. Errore: " . $result);
            }
        } catch (\Exception $e) {
            $queue->updateStatus($task['id'], 'failed', $e->getMessage());
            logWorker("Errore durante l'invio dell'ID {$task['id']}: " . $e->getMessage());
        }
    }

} catch (\Exception $e) {
    logWorker("ERRORE CRITICO: " . $e->getMessage());
}

logWorker("Fine worker.");