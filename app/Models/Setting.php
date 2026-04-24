<?php
// app/Models/Setting.php
namespace App\Models;

use App\Core\Database;

class Setting
{
    public static function get($name, $default = null)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT value FROM settings WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $name]);
        $row = $stmt->fetch();
        return $row ? $row['value'] : $default;
    }

    public static function set($name, $value)
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO settings (name, value) VALUES (:name, :value) ON DUPLICATE KEY UPDATE value = VALUES(value)");
        $stmt->execute([':name' => $name, ':value' => $value]);
    }
}
