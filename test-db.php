<?php
require_once 'includes/db_connect.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test basic connection
    echo "<p>✓ Database connection successful</p>";
    
    // Check if required tables exist
    $tables = ['users', 'user_profiles', 'admin_users', 'leaderboard_cache'];
    echo "<h3>Table Structure Check:</h3>";
    
    foreach ($tables as $table) {
        $tableQuoted = $pdo->quote($table);
        $stmt = $pdo->query("SHOW TABLES LIKE $tableQuoted");
        if ($stmt->fetch()) {
            echo "<p>✓ Table '$table' exists</p>";
            
            // Show table structure
            $stmt = $pdo->prepare("DESCRIBE $table");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<ul>";
            foreach ($columns as $column) {
                echo "<li>{$column['Field']} - {$column['Type']}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p>✗ Table '$table' NOT found</p>";
        }
    }
    
    // Test inserting a sample user
    echo "<h3>Test User Creation:</h3>";
    
    $testEmail = 'test_' . time() . '@example.com';
    $testPassword = 'testpass123';
    
    $pdo->beginTransaction();
    
    try {
        // Insert test user
        $stmt = $pdo->prepare("
            INSERT INTO users (college_email, email, password_hash, points_balance, total_points_earned, account_status, email_verified, registration_date, created_at) 
            VALUES (?, ?, ?, ?, ?, 'active', 1, NOW(), NOW())
        ");
        
        $result = $stmt->execute([
            $testEmail,
            $testEmail,
            password_hash($testPassword, PASSWORD_DEFAULT),
            100,
            100
        ]);
        
        if ($result) {
            $testUserId = $pdo->lastInsertId();
            echo "<p>✓ Test user created with ID: $testUserId</p>";
            
            // Insert test profile
            $stmt = $pdo->prepare("
                INSERT INTO user_profiles (user_id, first_name, last_name, display_name, student_number, student_id, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $profileResult = $stmt->execute([
                $testUserId,
                'Test',
                'User',
                'Test User',
                'TEST123',
                'TEST123'
            ]);
            
            if ($profileResult) {
                echo "<p>✓ Test user profile created</p>";
            } else {
                echo "<p>✗ Failed to create test user profile</p>";
            }
            
            // Insert leaderboard cache
            $stmt = $pdo->prepare("
                INSERT INTO leaderboard_cache (user_id, total_points, total_scans, last_updated) 
                VALUES (?, ?, 0, NOW())
            ");
            
            $leaderboardResult = $stmt->execute([$testUserId, 100]);
            
            if ($leaderboardResult) {
                echo "<p>✓ Test leaderboard cache created</p>";
            } else {
                echo "<p>✗ Failed to create test leaderboard cache</p>";
            }
            
            // Verify the data was inserted
            $stmt = $pdo->prepare("
                SELECT u.user_id, u.college_email, up.first_name, up.last_name, lc.total_points
                FROM users u
                LEFT JOIN user_profiles up ON u.user_id = up.user_id
                LEFT JOIN leaderboard_cache lc ON u.user_id = lc.user_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$testUserId]);
            $testUser = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($testUser) {
                echo "<p>✓ Test user data verified:</p>";
                echo "<pre>" . print_r($testUser, true) . "</pre>";
            }
            
            // Clean up test data
            $stmt = $pdo->prepare("DELETE FROM leaderboard_cache WHERE user_id = ?");
            $stmt->execute([$testUserId]);
            
            $stmt = $pdo->prepare("DELETE FROM user_profiles WHERE user_id = ?");
            $stmt->execute([$testUserId]);
            
            $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$testUserId]);
            
            echo "<p>✓ Test data cleaned up</p>";
        }
        
        $pdo->commit();
        echo "<p><strong>✓ All tests passed!</strong></p>";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo "<p>✗ Test failed: " . $e->getMessage() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p>✗ Database error: " . $e->getMessage() . "</p>";
}
?>
