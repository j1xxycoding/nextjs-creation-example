<?php
require_once 'includes/config.php';

try {
    // Read SQL file
    $sql = file_get_contents('db_setup.sql');
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    // Execute each statement
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $result = $connection->exec($statement);
            if ($result === false) {
                throw new Exception($connection->lastErrorMsg());
            }
        }
    }
    
    // Insert admin with properly hashed password
    $username = 'shada';
    $password = 'sofianhamza25';
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $connection->prepare("INSERT OR REPLACE INTO admin (username, password_hash) VALUES (:username, :password_hash)");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $stmt->bindValue(':password_hash', $password_hash, SQLITE3_TEXT);
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to create admin user.");
    }
    
    echo "Database initialized successfully!\n";
    echo "Admin user created with username: $username\n";
    
} catch (Exception $e) {
    die("Error initializing database: " . $e->getMessage() . "\n");
}
