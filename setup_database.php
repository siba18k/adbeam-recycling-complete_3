<?php
require_once 'includes/db_connect.php';

// Database setup script for XAMPP
echo "Setting up Adbeam Recycling Database...\n";

try {
    // First, let's check what tables and columns already exist
    $existingTables = [];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }
    
    // Check users table structure
    $userTableExists = in_array('users', $existingTables);
    $userColumns = [];
    
    if ($userTableExists) {
        $stmt = $pdo->query("DESCRIBE users");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $userColumns[] = $row['Field'];
        }
        echo "✓ Users table exists with columns: " . implode(', ', $userColumns) . "\n";
    }
    
    // Create or update users table based on existing structure
    if (!$userTableExists) {
        $pdo->exec("
            CREATE TABLE users (
                user_id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) UNIQUE NOT NULL,
                college_email VARCHAR(255),
                password_hash VARCHAR(255) NOT NULL,
                points_balance INT DEFAULT 0,
                total_points_earned INT DEFAULT 0,
                account_status ENUM('active', 'suspended', 'deleted') DEFAULT 'active',
                email_verified BOOLEAN DEFAULT FALSE,
                registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                login_count INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Users table created\n";
    } else {
        // Add missing columns if they don't exist
        if (!in_array('college_email', $userColumns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN college_email VARCHAR(255) AFTER email");
            echo "✓ Added college_email column to users table\n";
        }
        if (!in_array('login_count', $userColumns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN login_count INT DEFAULT 0");
            echo "✓ Added login_count column to users table\n";
        }
        if (!in_array('total_points_earned', $userColumns)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN total_points_earned INT DEFAULT 0");
            echo "✓ Added total_points_earned column to users table\n";
        }
        echo "✓ Users table verified and updated\n";
    }

    // Create user_profiles table
    if (!in_array('user_profiles', $existingTables)) {
        $pdo->exec("
            CREATE TABLE user_profiles (
                profile_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                first_name VARCHAR(100),
                last_name VARCHAR(100),
                display_name VARCHAR(200),
                student_number VARCHAR(50),
                student_id VARCHAR(50),
                phone VARCHAR(20),
                date_of_birth DATE,
                profile_picture_url VARCHAR(500),
                bio TEXT,
                university VARCHAR(255),
                university_code VARCHAR(50),
                full_name VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ User profiles table created\n";
    } else {
        echo "✓ User profiles table exists\n";
    }

    // Create admin_users table
    if (!in_array('admin_users', $existingTables)) {
        $pdo->exec("
            CREATE TABLE admin_users (
                admin_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                granted_by INT,
                granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                is_active BOOLEAN DEFAULT TRUE,
                permissions JSON,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                FOREIGN KEY (granted_by) REFERENCES users(user_id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Admin users table created\n";
    } else {
        echo "✓ Admin users table exists\n";
    }

    // Create rewards table
    if (!in_array('rewards', $existingTables)) {
        $pdo->exec("
            CREATE TABLE rewards (
                reward_id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                points_cost INT NOT NULL,
                category VARCHAR(100),
                inventory INT NULL,
                is_active BOOLEAN DEFAULT TRUE,
                image_url VARCHAR(500),
                terms_conditions TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Rewards table created\n";
    } else {
        echo "✓ Rewards table exists\n";
    }

    // Create user_rewards table
    if (!in_array('user_rewards', $existingTables)) {
        $pdo->exec("
            CREATE TABLE user_rewards (
                redemption_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                reward_id INT NOT NULL,
                points_spent INT NOT NULL,
                status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
                redemption_code VARCHAR(100),
                redeemed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL,
                used_at TIMESTAMP NULL,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
                FOREIGN KEY (reward_id) REFERENCES rewards(reward_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ User rewards table created\n";
    } else {
        echo "✓ User rewards table exists\n";
    }

    // Create scanned_items table
    if (!in_array('scanned_items', $existingTables)) {
        $pdo->exec("
            CREATE TABLE scanned_items (
                scan_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                barcode VARCHAR(255),
                item_name VARCHAR(255),
                category VARCHAR(100),
                points_awarded INT DEFAULT 0,
                scan_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                location VARCHAR(255),
                verified BOOLEAN DEFAULT FALSE,
                co2_saved DECIMAL(10,2) DEFAULT 0,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Scanned items table created\n";
    } else {
        echo "✓ Scanned items table exists\n";
    }

    // Create leaderboard_cache table
    if (!in_array('leaderboard_cache', $existingTables)) {
        $pdo->exec("
            CREATE TABLE leaderboard_cache (
                user_id INT PRIMARY KEY,
                total_points INT DEFAULT 0,
                total_scans INT DEFAULT 0,
                rank_position INT,
                last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ Leaderboard cache table created\n";
    } else {
        echo "✓ Leaderboard cache table exists\n";
    }

    // Check for existing admin users
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE is_active = 1");
    $stmt->execute();
    $adminCount = $stmt->fetchColumn();
    
    if ($adminCount == 0) {
        // Check if there are any users at all
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        $userCount = $stmt->fetchColumn();
        
        if ($userCount == 0) {
            // Create default admin user only if no users exist
            $pdo->beginTransaction();
            
            $adminEmail = 'admin@recycling.local';
            $adminPassword = 'admin' . date('Y'); // Dynamic password based on year
            
            $stmt = $pdo->prepare("
                INSERT INTO users (email, college_email, password_hash, points_balance, total_points_earned, account_status, email_verified) 
                VALUES (?, ?, ?, 0, 0, 'active', TRUE)
            ");
            $stmt->execute([$adminEmail, $adminEmail, password_hash($adminPassword, PASSWORD_DEFAULT)]);
            
            $adminUserId = $pdo->lastInsertId();
            
            // Create admin profile
            $stmt = $pdo->prepare("
                INSERT INTO user_profiles (user_id, first_name, last_name, display_name, full_name) 
                VALUES (?, 'System', 'Administrator', 'System Administrator', 'System Administrator')
            ");
            $stmt->execute([$adminUserId]);
            
            // Grant admin privileges
            $stmt = $pdo->prepare("
                INSERT INTO admin_users (user_id, granted_by, is_active) 
                VALUES (?, ?, TRUE)
            ");
            $stmt->execute([$adminUserId, $adminUserId]);
            
            // Initialize leaderboard cache
            $stmt = $pdo->prepare("
                INSERT INTO leaderboard_cache (user_id, total_points, total_scans) 
                VALUES (?, 0, 0)
            ");
            $stmt->execute([$adminUserId]);
            
            $pdo->commit();
            echo "✓ Default admin user created ($adminEmail / $adminPassword)\n";
        } else {
            echo "✓ Users exist but no admin found. Please manually grant admin privileges.\n";
        }
    } else {
        echo "✓ Admin users already exist ($adminCount active)\n";
    }

    // Update any existing users to have proper email fields
    $stmt = $pdo->prepare("UPDATE users SET college_email = email WHERE college_email IS NULL OR college_email = ''");
    $stmt->execute();
    $updated = $stmt->rowCount();
    if ($updated > 0) {
        echo "✓ Updated $updated users with college_email field\n";
    }

    echo "\n🎉 Database setup completed successfully!\n";
    echo "You can now access the admin dashboard.\n";

} catch (Exception $e) {
    echo "❌ Error setting up database: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
?>
