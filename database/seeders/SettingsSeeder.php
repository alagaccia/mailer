<?php
// database/seeders/SettingsSeeder.php

use PDO;
use App\Core\Database;

return new class {
    public function run(PDO $pdo)
    {
        $tableName = Database::getPrefix() . 'settings';
        $stmt = $pdo->prepare("INSERT INTO $tableName (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $stmt->execute([
            ':name' => 'mailer_enabled',
            ':value' => '1',
        ]);
    }
};
