<?php
// Reset OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ OPcache resettato<br>";
} else {
    echo "✗ OPcache non disponibile";
}

// Visualizza info
echo "<br><br><pre>";
echo "PHP Version: " . phpversion() . "\n";
echo "OPcache Status: " . (extension_loaded('Zend OPcache') ? 'ENABLED' : 'DISABLED') . "\n";
if (function_exists('opcache_get_status')) {
    $status = @opcache_get_status();
    echo "OPcache Memory Used: " . number_format($status['memory_usage']['used_memory']) . " bytes\n";
}
echo "</pre>";

// Ridirige al dashboard
echo "<br><a href='/dashboard.php'>← Torna al Dashboard</a>";
?>
