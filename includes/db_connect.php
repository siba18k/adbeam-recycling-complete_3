<?php
// Database configuration for XAMPP - check multiple possible database names
$host = 'localhost';
$possibleDatabases = ['adbeam_enhanced','adbeam_recycling'];
$username = 'root';  // Default XAMPP username
$password = '';      // Default XAMPP password (empty)
$charset = 'utf8mb4';

$pdo = null;
$dbname = null;

// Try to connect to existing databases
foreach ($possibleDatabases as $testDb) {
    try {
        $dsn = "mysql:host=$host;dbname=$testDb;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        $testPdo = new PDO($dsn, $username, $password, $options);
        $testPdo->query("SELECT 1");
        
        // If we get here, the connection worked
        $pdo = $testPdo;
        $dbname = $testDb;
        break;
    } catch (PDOException $e) {
        // Continue to next database
        continue;
    }
}

// If no existing database found, create the default one
if (!$pdo) {
    try {
        $dbname = 'adbeam_enhanced';
        
        // Connect without specifying database to create it
        $tempPdo = new PDO("mysql:host=$host;charset=$charset", $username, $password);
        $tempPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        // Now connect to the created database
        $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        $pdo = new PDO($dsn, $username, $password, $options);
        $pdo->query("SELECT 1");
        
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'message' => 'Database connection failed. Please check your database configuration.',
                'error' => $e->getMessage()
            ]);
        } else {
            echo "Database connection failed. Please check your database configuration and try again.";
        }
        exit;
    }
}

// Set timezone
try {
    $pdo->exec("SET time_zone = '+00:00'");
} catch (PDOException $e) {
    // Ignore timezone errors
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set error reporting for debugging (remove in production)
if (isset($_GET['debug']) || (isset($_SESSION['user_id']) && $_SESSION['user_id'] == 1)) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
?>
