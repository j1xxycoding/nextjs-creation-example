<?php
require_once 'includes/config.php';

$result = $connection->query("SELECT * FROM admin");
$admin = $result->fetchArray(SQLITE3_ASSOC);

if ($admin) {
    echo "Admin user found:\n";
    echo "Username: " . $admin['username'] . "\n";
    echo "Password hash: " . $admin['password_hash'] . "\n";
} else {
    echo "No admin user found in database.\n";
}
