<?php
require_once 'includes/db_connect.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test database connection
    echo "✓ Database connected successfully<br>";
    
    // Check tables
    $stmt = $pdo->prepare("SHOW TABLES");
    $stmt->execute();
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<h3>Available Tables:</h3>";
    foreach ($tables as $table) {
        echo "- $table<br>";
    }
    
    // Check users table structure
    echo "<h3>Users Table Structure:</h3>";
    $stmt = $pdo->prepare("DESCRIBE users");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $column) {
        echo "- {$column['Field']} ({$column['Type']})<br>";
    }
    
    // Count users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE account_status != 'deleted'");
    $stmt->execute();
    $userCount = $stmt->fetchColumn();
    echo "<h3>Total Active Users: $userCount</h3>";
    
    // Show first few users
    if ($userCount > 0) {
        echo "<h3>Sample Users:</h3>";
        $emailColumn = in_array('college_email', array_column($columns, 'Field')) ? 'college_email' : 'email';
        
        $stmt = $pdo->prepare("SELECT user_id, $emailColumn as email, account_status, points_balance FROM users WHERE account_status != 'deleted' LIMIT 5");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Email</th><th>Status</th><th>Points</th></tr>";
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['user_id']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['account_status']}</td>";
            echo "<td>{$user['points_balance']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Check admin users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE is_active = 1");
    $stmt->execute();
    $adminCount = $stmt->fetchColumn();
    echo "<h3>Total Active Admins: $adminCount</h3>";
    
    if ($adminCount > 0) {
        $stmt = $pdo->prepare("
            SELECT au.user_id, u.$emailColumn as email 
            FROM admin_users au 
            JOIN users u ON au.user_id = u.user_id 
            WHERE au.is_active = 1
        ");
        $stmt->execute();
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Admin Users:</h4>";
        foreach ($admins as $admin) {
            echo "- User ID: {$admin['user_id']}, Email: {$admin['email']}<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
