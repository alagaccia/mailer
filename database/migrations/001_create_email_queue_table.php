<?php

return function() {
    $prefix = getenv('DB_PREFIX') ?: '';
    return "CREATE TABLE IF NOT EXISTS {$prefix}email_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    attachments JSON DEFAULT NULL,
    status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
    attempts TINYINT UNSIGNED DEFAULT 0,
    last_error TEXT DEFAULT NULL,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX email_queue_status_index (status),
    INDEX email_queue_recipient_index (recipient)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
};