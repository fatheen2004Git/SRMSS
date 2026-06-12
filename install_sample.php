<?php
// install_sample.php
header('Content-Type: application/json');

define('ALLOW_NO_DB', true);
require_once __DIR__ . '/config/db.php';

try {
    if (!isset($pdo) || !$pdo) {
        echo json_encode(['status' => 'error', 'message' => 'Database not connected. Please install the schema first.']);
        exit;
    }

    $sqlFile = __DIR__ . '/database/sample_schema.sql';
    
    if (!file_exists($sqlFile)) {
        echo json_encode(['status' => 'error', 'message' => 'Sample data file not found at database/sample_schema.sql']);
        exit;
    }

    $sql = file_get_contents($sqlFile);
    
    // Drop all existing tables to ensure a clean import without primary key conflicts
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `$table`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    // Execute the SQL file
    $pdo->exec($sql);

    echo json_encode(['status' => 'success', 'message' => 'Sample data successfully installed!']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
}
