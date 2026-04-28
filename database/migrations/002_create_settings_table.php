<?php

return function() {
    $prefix = getenv('DB_PREFIX') ?: '';
    return "CREATE TABLE IF NOT EXISTS {$prefix}settings (
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
};
