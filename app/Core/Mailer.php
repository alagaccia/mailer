<?php
namespace App\Core;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);

        $this->mail->SMTPDebug = 2; // Attiva il debug dettagliato
        $this->mail->Debugoutput = function($str, $level) {
            file_put_contents(__DIR__ . '/../storage/logs/smtp.log', "[$level] $str\n", FILE_APPEND);
        };
        
        // Configurazioni fisse da .env
        $this->mail->isSMTP();
        $this->mail->Host       = getenv('SMTP_HOST');
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = getenv('SMTP_USER');
        $this->mail->Password   = getenv('SMTP_PASS');
        $this->mail->SMTPSecure = getenv('SMTP_SECURE') ?: 'ssl';
        $this->mail->Port       = getenv('SMTP_PORT');
        $this->mail->setFrom(getenv('SMTP_USER'), getenv('SMTP_FROM_NAME'));
        $this->mail->addReplyTo(getenv('SMTP_REPLY_TO') ?: getenv('SMTP_USER'));
        $this->mail->CharSet          = 'UTF-8';
    }

    public function send($to, $subject, $body, $attachments = []) {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();
            $this->mail->addAddress($to);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;

            // Aggiungi gli allegati
            foreach ($attachments as $attachment) {
                if (!empty($attachment['filename']) && !empty($attachment['content'])) {
                    // Se 'content' è base64, decodifica
                    $content = base64_decode($attachment['content'], true);
                    if ($content === false) {
                        // Se non è base64 valido, usa il contenuto così com'è
                        $content = $attachment['content'];
                    }
                    
                    $this->mail->addStringAttachment(
                        $content,
                        $attachment['filename'],
                        'base64',
                        $attachment['mime'] ?? 'application/octet-stream'
                    );
                }
            }

            $this->mail->send();
            return true;
        } catch (Exception $e) {
            return $this->mail->ErrorInfo;
        }
    }
}