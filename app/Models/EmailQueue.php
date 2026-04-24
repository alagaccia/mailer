<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class EmailQueue {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($data) {
        $attachments = (isset($data['attachments']) && !empty($data['attachments'])) ? json_encode($data['attachments']) : null;
        $stmt = $this->db->prepare("INSERT INTO email_queue (recipient, subject, body, attachments) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['recipient'],
            $data['subject'],
            $data['body'],
            $attachments
        ]);
        return $this->db->lastInsertId();
    }

    public function getPending($limit = 10) {
        return $this->db->query("SELECT * FROM email_queue WHERE status = 'pending' LIMIT $limit")
                        ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status, $error = null) {
        if ($status === 'sent') {
            $stmt = $this->db->prepare("UPDATE email_queue SET status = ?, last_error = ?, attempts = attempts + 1, sent_at = CURRENT_TIMESTAMP WHERE id = ?");
        } else {
            $stmt = $this->db->prepare("UPDATE email_queue SET status = ?, last_error = ?, attempts = attempts + 1 WHERE id = ?");
        }
        $stmt->execute([$status, $error, $id]);
    }

    public function markAsSent($id) {
        $stmt = $this->db->prepare("UPDATE email_queue SET status = 'sent', sent_at = CURRENT_TIMESTAMP WHERE id = ? AND status != 'sent'");
        return $stmt->execute([$id]);
    }

    public function getById($id) {
        $stmt = $this->db->prepare("SELECT * FROM email_queue WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}