<?php
// Display all PHP errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Server Environment Check</h1>";

// Check PHP version
echo "<h2>PHP Version</h2>";
echo "<p>" . phpversion() . "</p>";

// Check loaded modules
echo "<h2>Loaded Apache Modules</h2>";
if (function_exists('apache_get_modules')) {
    echo "<pre>";
    print_r(apache_get_modules());
    echo "</pre>";
} else {
    echo "<p>Unable to determine Apache modules (function apache_get_modules not available)</p>";
}

// Check server variables
echo "<h2>Server Variables</h2>";
echo "<pre>";
print_r($_SERVER);
echo "</pre>";

// Check .htaccess is being read
echo "<h2>.htaccess Test</h2>";
echo "<p>If you see this, PHP is working, but it doesn't confirm .htaccess is working.</p>";
echo "<p>Try accessing a non-existent URL in this directory to test if the rewrite rules work.</p>";

// Check file permissions
echo "<h2>File Permissions</h2>";
$currentDir = __DIR__;
echo "<p>Current directory: $currentDir</p>";
echo "<p>Readable: " . (is_readable($currentDir) ? 'Yes' : 'No') . "</p>";
echo "<p>Writable: " . (is_writable($currentDir) ? 'Yes' : 'No') . "</p>";
echo "<p>.htaccess exists: " . (file_exists($currentDir . '/.htaccess') ? 'Yes' : 'No') . "</p>";
echo "<p>.htaccess readable: " . (is_readable($currentDir . '/.htaccess') ? 'Yes' : 'No') . "</p>";
?> 