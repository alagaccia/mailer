<?php
// database/seeders/SettingsSeeder.php

use PDO;

return new class {
    public function run(PDO $pdo)
    {
        $stmt = $pdo->prepare("INSERT INTO settings (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $stmt->execute([
            ':name' => 'mailer_enabled',
            ':value' => '1',
        ]);
    }
};
