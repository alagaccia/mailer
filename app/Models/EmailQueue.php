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
        $tableName = Database::getPrefix() . 'email_queue';
        $stmt = $this->db->prepare("INSERT INTO $tableName (recipient, subject, body, attachments) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $data['recipient'],
            $data['subject'],
            $data['body'],
            $attachments
        ]);
        return $this->db->lastInsertId();
    }

    public function getPending($limit = 10) {
        $tableName = Database::getPrefix() . 'email_queue';
        return $this->db->query("SELECT * FROM $tableName WHERE status = 'pending' LIMIT $limit")
                        ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status, $error = null) {
        $tableName = Database::getPrefix() . 'email_queue';
        if ($status === 'sent') {
            $stmt = $this->db->prepare("UPDATE $tableName SET status = ?, last_error = ?, attempts = attempts + 1, sent_at = CURRENT_TIMESTAMP WHERE id = ?");
        } else {
            $stmt = $this->db->prepare("UPDATE $tableName SET status = ?, last_error = ?, attempts = attempts + 1 WHERE id = ?");
        }
        $stmt->execute([$status, $error, $id]);
    }

    public function markAsSent($id) {
        $tableName = Database::getPrefix() . 'email_queue';
        $stmt = $this->db->prepare("UPDATE $tableName SET status = 'sent', sent_at = CURRENT_TIMESTAMP WHERE id = ? AND status != 'sent'");
        return $stmt->execute([$id]);
    }

    public function getById($id) {
        $tableName = Database::getPrefix() . 'email_queue';
        $stmt = $this->db->prepare("SELECT * FROM $tableName WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}