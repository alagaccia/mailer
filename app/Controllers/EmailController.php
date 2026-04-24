<?php
namespace App\Controllers;

use App\Models\EmailQueue;
use App\Core\Mailer;
use App\Models\Setting;

class EmailController {
    public function store($data) {
        // 0. Controllo se il mailer è abilitato
        if (Setting::get('mailer_enabled', '1') !== '1') {
            return [
                'status' => 403,
                'response' => ['error' => 'Mailer disabilitato dalle impostazioni']
            ];
        }
        // 1. Controllo che $data non sia null (JSON malformato)
        if (!$data) {
            return ['status' => 400, 'response' => ['error' => 'Invalid JSON body']];
        }

        // 2. Validazione completa (aggiunto body)
        $required = ['to', 'subject', 'body'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return [
                    'status' => 400, 
                    'response' => [
                        'error' => 'Missing fields', 
                        'field' => $field // Ti dice esattamente quale manca
                    ]
                ];
            }
        }

        // Normalizza "to" in un array (supporta sia stringa che array)
        $recipients = is_array($data['to']) ? $data['to'] : [$data['to']];

        // Valida ogni indirizzo
        foreach ($recipients as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'status' => 400,
                    'response' => [
                        'error' => 'Invalid email address',
                        'email' => $email
                    ]
                ];
            }
        }

        // Estrai gli allegati (opzionale)
        $attachments = $data['attachments'] ?? [];
        if (!is_array($attachments)) {
            $attachments = [];
        }

        // Modalità sincrona: invia subito senza passare dalla coda
        $sync = isset($data['sync']) && filter_var($data['sync'], FILTER_VALIDATE_BOOLEAN);

        if ($sync) {
            return $this->sendSync($recipients, $data['subject'], $data['body'], $attachments);
        }

        return $this->sendAsync($recipients, $data['subject'], $data['body'], $attachments);
    }

    /**
     * Invio sincrono: spedisce immediatamente e restituisce il risultato.
     */
    private function sendSync(array $recipients, string $subject, string $body, $attachments = []): array {
        try {
            $mailer = new Mailer();
            $sent = [];
            $failed = [];

            foreach ($recipients as $recipient) {
                $result = $mailer->send($recipient, $subject, $body, $attachments);
                if ($result === true) {
                    $sent[] = $recipient;
                } else {
                    $failed[] = ['email' => $recipient, 'error' => $result];
                }
            }

            if (!empty($failed) && empty($sent)) {
                return [
                    'status' => 500,
                    'response' => [
                        'message' => 'All emails failed',
                        'failed' => $failed
                    ]
                ];
            }

            return [
                'status' => 200,
                'response' => [
                    'message' => 'Sent',
                    'sent' => $sent,
                    'failed' => $failed
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 500,
                'response' => ['error' => 'Mailer error', 'details' => $e->getMessage()]
            ];
        }
    }

    /**
     * Invio asincrono: accoda l'email e triggera il worker in background.
     */
    private function sendAsync(array $recipients, string $subject, string $body, $attachments = []): array {
        try {
            $queue = new EmailQueue();
            $ids = [];

            foreach ($recipients as $recipient) {
                $ids[] = $queue->create([
                    'recipient' => $recipient,
                    'subject'   => $subject,
                    'body'      => $body,
                    'attachments' => $attachments
                ]);
            }

            // Trigger immediato del worker
            $workerPath = dirname(__DIR__, 2) . "/worker.php";
            shell_exec("/usr/local/bin/php $workerPath > /dev/null 2>&1 &");

            return [
                'status' => 201, 
                'response' => [
                    'message' => 'Queued', 
                    'ids' => $ids,
                    'recipients' => count($ids)
                ]
            ];

        } catch (\Exception $e) {
            return [
                'status' => 500, 
                'response' => ['error' => 'Database error', 'details' => $e->getMessage()]
            ];
        }
    }

    /**
     * Invia immediatamente un'email dalla coda, controllando se già inviata
     */
    public function sendFromQueue($id) {
        try {

            // Controllo se il mailer è abilitato
            if (Setting::get('mailer_enabled', '1') !== '1') {
                return [
                    'status' => 403,
                    'response' => ['error' => 'Mailer disabilitato dalle impostazioni']
                ];
            }

            $queue = new EmailQueue();
            $email = $queue->getById($id);

            if (!$email) {
                return [
                    'status' => 404,
                    'response' => ['error' => 'Email not found']
                ];
            }

            // Se già inviata, ritorna errore
            if ($email['status'] === 'sent') {
                return [
                    'status' => 409,
                    'response' => ['error' => 'Email already sent']
                ];
            }

            // Invia l'email con gli allegati
            $attachments = !empty($email['attachments']) ? json_decode($email['attachments'], true) : [];
            $mailer = new Mailer();
            $result = $mailer->send($email['recipient'], $email['subject'], $email['body'], $attachments);

            if ($result === true) {
                $queue->markAsSent($id);
                return [
                    'status' => 200,
                    'response' => [
                        'message' => 'Email sent successfully',
                        'id' => $id,
                        'recipient' => $email['recipient']
                    ]
                ];
            } else {
                $queue->updateStatus($id, 'failed', $result);
                return [
                    'status' => 500,
                    'response' => [
                        'error' => 'Failed to send email',
                        'id' => $id,
                        'details' => $result
                    ]
                ];
            }

        } catch (\Exception $e) {
            return [
                'status' => 500,
                'response' => ['error' => 'Server error', 'details' => $e->getMessage()]
            ];
        }
    }
}