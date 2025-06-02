<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/db_connect.php');


header('Content-Type: application/json');

try {
    $debug = [];
    
    // Test database connection
    $debug['connection'] = 'OK';
    
    // Check tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $debug['tables'] = $tables;
    
    // Check users table structure
    $stmt = $pdo->query("DESCRIBE users");
    $userColumns = $stmt->fetchAll();
    $debug['user_columns'] = $userColumns;
    
    // Check user_profiles table structure
    $stmt = $pdo->query("DESCRIBE user_profiles");
    $profileColumns = $stmt->fetchAll();
    $debug['profile_columns'] = $profileColumns;
    
    // Count records
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch();
    $debug['user_count'] = $userCount['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_profiles");
    $profileCount = $stmt->fetch();
    $debug['profile_count'] = $profileCount['count'];
    
    // Test user creation (dry run)
    $stmt = $pdo->prepare("SELECT * FROM users WHERE college_email = ?");
    $stmt->execute(['test@example.com']);
    $testUser = $stmt->fetch();
    $debug['test_user_exists'] = $testUser ? true : false;
    
    // Check admin users
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM admin_users");
    $adminCount = $stmt->fetch();
    $debug['admin_count'] = $adminCount['count'];
    
    echo json_encode([
        'success' => true,
        'debug' => $debug,
        'message' => 'Database verification complete'
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => 'Database verification failed'
    ]);
}
?>
