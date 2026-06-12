<?php
// install_schema.php
header('Content-Type: application/json');

define('ALLOW_NO_DB', true);
require_once __DIR__ . '/config/db.php';

try {
    // If the DB doesn't exist at all, $pdo will be null from config/db.php.
    // Let's create a temporary connection without dbname to create it if needed.
    if (!isset($pdo) || !$pdo) {
        $tempPdo = new PDO("mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "`");
        $tempPdo = null;
        
        // Re-establish connection
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }

    $sqlFile = __DIR__ . '/database/current_schema.sql';
    
    if (!file_exists($sqlFile)) {
        echo json_encode(['status' => 'error', 'message' => 'Schema file not found at database/current_schema.sql']);
        exit;
    }

    $sql = file_get_contents($sqlFile);
    
    // Execute the SQL file. We use exec() to run multiple statements.
    $pdo->exec($sql);

    echo json_encode(['status' => 'success', 'message' => 'Database schema successfully installed!']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
