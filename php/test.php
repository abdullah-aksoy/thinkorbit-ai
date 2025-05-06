<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get PHP information
echo "<h1>PHP Test File</h1>";

// Check database connection
require_once 'config.php';

try {
    $pdo = getDbConnection();
    echo "<p>Database connection successful!</p>";
    
    // Test query
    $stmt = $pdo->query("SELECT 1");
    $result = $stmt->fetch();
    echo "<p>Database query successful: " . print_r($result, true) . "</p>";
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// Show loaded extensions
echo "<h2>Loaded Extensions</h2>";
echo "<pre>" . print_r(get_loaded_extensions(), true) . "</pre>";

// Show error log path
echo "<h2>Error Log Path</h2>";
echo "<p>" . ini_get('error_log') . "</p>";

// Show configuration details
echo "<h2>PHP Configuration</h2>";
phpinfo();
?> 