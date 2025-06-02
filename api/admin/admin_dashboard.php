<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/admin_auth.php';

// Enable error reporting for debugging
if (isset($_GET['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /assets/index.html');
    exit;
}

// Check if user is admin
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    // Double-check admin status from database
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $isAdmin = $stmt->fetchColumn() > 0;
        
        if (!$isAdmin) {
            header('Location: /assets/dashboard.html');
            exit;
        }
        
        // Update session
        $_SESSION['is_admin'] = true;
    } catch (Exception $e) {
        error_log("Admin check error: " . $e->getMessage());
        header('Location: /assets/dashboard.html');
        exit;
    }
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_user':
                    $result = addUser($_POST, $pdo);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'danger';
                    break;
                    
                case 'edit_user':
                    $result = editUser($_POST, $pdo);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'danger';
                    break;
                    
                case 'delete_user':
                    $result = deleteUser($_POST['user_id'], $pdo);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'danger';
                    break;
                    
                case 'toggle_user_status':
                    $result = toggleUserStatus($_POST['user_id'], $_POST['new_status'], $pdo);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'danger';
                    break;
                    
                case 'add_reward':
                    $result = addReward($_POST, $pdo);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'danger';
                    break;
                    
                case 'edit_reward':
                    $result = editReward($_POST, $pdo);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'danger';
                    break;
                    
                case 'delete_reward':
                    $result = deleteReward($_POST['reward_id'], $pdo);
                    $message = $result['message'];
                    $messageType = $result['success'] ? 'success' : 'danger';
                    break;
            }
        }
    } catch (Exception $e) {
        $message = 'Error: ' . $e->getMessage();
        $messageType = 'danger';
        error_log("Admin dashboard error: " . $e->getMessage());
    }
}

// Get dashboard statistics with better error handling
function getDashboardStats($pdo) {
    try {
        $stats = [
            'total_users' => 0, 'active_users' => 0, 'new_users' => 0,
            'total_scans' => 0, 'total_points_awarded' => 0,
            'total_rewards' => 0, 'active_rewards' => 0, 'total_redemptions' => 0,
            'activities_today' => 0, 'activities_this_week' => 0,
            'total_points_balance' => 0, 'total_points_earned' => 0
        ];

        // User statistics
        try {
            // Check what columns exist in users table
            $stmt = $pdo->prepare("DESCRIBE users");
            $stmt->execute();
            $userColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $dateColumn = 'user_id'; // fallback
            if (in_array('registration_date', $userColumns)) {
                $dateColumn = 'registration_date';
            } elseif (in_array('created_at', $userColumns)) {
                $dateColumn = 'created_at';
            }
            
            $sql = "
                SELECT 
                    COUNT(DISTINCT u.user_id) as total_users,
                    COALESCE(SUM(u.points_balance), 0) as total_points_balance
            ";
            
            if (in_array('account_status', $userColumns)) {
                $sql .= ", COUNT(DISTINCT CASE WHEN u.account_status = 'active' THEN u.user_id END) as active_users";
            } else {
                $sql .= ", COUNT(DISTINCT u.user_id) as active_users";
            }
            
            if ($dateColumn != 'user_id') {
                $sql .= ", COUNT(DISTINCT CASE WHEN u.$dateColumn >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.user_id END) as new_users";
            } else {
                $sql .= ", 0 as new_users";
            }
            
            if (in_array('total_points_earned', $userColumns)) {
                $sql .= ", COALESCE(SUM(u.total_points_earned), 0) as total_points_earned";
            } else {
                $sql .= ", COALESCE(SUM(u.points_balance), 0) as total_points_earned";
            }
            
            $sql .= " FROM users u";
            
            if (in_array('account_status', $userColumns)) {
                $sql .= " WHERE u.account_status != 'deleted'";
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($userStats) {
                $stats = array_merge($stats, $userStats);
            }
        } catch (PDOException $e) {
            error_log("Error getting user stats: " . $e->getMessage());
        }
        
        // Activity statistics with fallback for missing tables
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'scanned_items'");
            $stmt->execute();
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(*) as total_scans,
                        COALESCE(SUM(si.points_awarded), 0) as total_points_awarded,
                        COUNT(CASE WHEN DATE(si.scan_timestamp) = CURDATE() THEN 1 END) as activities_today,
                        COUNT(CASE WHEN si.scan_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as activities_this_week
                    FROM scanned_items si
                ");
                $stmt->execute();
                $activityStats = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($activityStats) {
                    $stats = array_merge($stats, $activityStats);
                }
            }
        } catch (PDOException $e) {
            error_log("Error getting activity stats: " . $e->getMessage());
        }
        
        // Reward statistics with fallback
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'rewards'");
            $stmt->execute();
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("
                    SELECT 
                        COUNT(DISTINCT r.reward_id) as total_rewards,
                        COUNT(DISTINCT CASE WHEN r.is_active = TRUE THEN r.reward_id END) as active_rewards
                    FROM rewards r
                ");
                $stmt->execute();
                $rewardStats = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($rewardStats) {
                    $stats = array_merge($stats, $rewardStats);
                }

                // Get redemption stats
                $stmt = $pdo->prepare("SHOW TABLES LIKE 'user_rewards'");
                $stmt->execute();
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) as total_redemptions FROM user_rewards");
                    $stmt->execute();
                    $redemptionStats = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($redemptionStats) {
                        $stats['total_redemptions'] = $redemptionStats['total_redemptions'];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Error getting reward stats: " . $e->getMessage());
        }
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return [
            'total_users' => 0, 'active_users' => 0, 'new_users' => 0,
            'total_scans' => 0, 'total_points_awarded' => 0,
            'total_rewards' => 0, 'active_rewards' => 0, 'total_redemptions' => 0,
            'activities_today' => 0, 'activities_this_week' => 0,
            'total_points_balance' => 0, 'total_points_earned' => 0
        ];
    }
}

// Get all users with improved error handling and flexible column handling
function getUsers($pdo, $search = '') {
    try {
        // Check available columns dynamically
        $stmt = $pdo->prepare("DESCRIBE users");
        $stmt->execute();
        $userColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Check if user_profiles table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'user_profiles'");
        $stmt->execute();
        $profileTableExists = $stmt->fetch();
        
        // Build dynamic query based on available columns
        $emailColumn = in_array('college_email', $userColumns) ? 'u.college_email' : 'u.email';
        
        // Check for date column
        $dateColumn = 'u.user_id'; // fallback
        if (in_array('registration_date', $userColumns)) {
            $dateColumn = 'u.registration_date';
        } elseif (in_array('created_at', $userColumns)) {
            $dateColumn = 'u.created_at';
        }
        
        // Base query
        $sql = "
            SELECT 
                u.user_id, 
                $emailColumn as email, 
                COALESCE(u.points_balance, 0) as points_balance, 
                u.account_status
        ";
        
        // Add date column if available
        if (in_array('registration_date', $userColumns)) {
            $sql .= ", u.registration_date";
        } elseif (in_array('created_at', $userColumns)) {
            $sql .= ", u.created_at as registration_date";
        } else {
            $sql .= ", NOW() as registration_date";
        }
        
        // Add optional columns
        if (in_array('last_login', $userColumns)) {
            $sql .= ", u.last_login";
        } else {
            $sql .= ", NULL as last_login";
        }
        
        // Add profile fields if table exists
        if ($profileTableExists) {
            $sql .= ",
                COALESCE(up.first_name, '') as first_name,
                COALESCE(up.last_name, '') as last_name,
                COALESCE(up.display_name, up.full_name, CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))) as full_name,
                COALESCE(up.student_number, up.student_id) as student_id";
        } else {
            $sql .= ",
                '' as first_name,
                '' as last_name,
                'N/A' as full_name,
                '' as student_id";
        }
        
        // Add activity count
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'scanned_items'");
        $stmt->execute();
        if ($stmt->fetch()) {
            $sql .= ", (SELECT COUNT(*) FROM scanned_items si WHERE si.user_id = u.user_id) as total_activities";
        } else {
            $sql .= ", 0 as total_activities";
        }
        
        // Add admin check
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'admin_users'");
        $stmt->execute();
        if ($stmt->fetch()) {
            $sql .= ", (SELECT COUNT(*) FROM admin_users au WHERE au.user_id = u.user_id AND au.is_active = 1) > 0 as is_admin";
        } else {
            $sql .= ", 0 as is_admin";
        }
        
        // FROM clause
        $sql .= " FROM users u";
        if ($profileTableExists) {
            $sql .= " LEFT JOIN user_profiles up ON u.user_id = up.user_id";
        }
        
        $sql .= " WHERE 1=1";
        
        // Only filter deleted if account_status column exists
        if (in_array('account_status', $userColumns)) {
            $sql .= " AND u.account_status != 'deleted'";
        }
        
        $params = [];
        if (!empty($search)) {
            if ($profileTableExists) {
                $sql .= " AND ($emailColumn LIKE ? OR up.first_name LIKE ? OR up.last_name LIKE ? OR COALESCE(up.student_number, up.student_id) LIKE ?)";
                $searchTerm = "%$search%";
                $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
            } else {
                $sql .= " AND $emailColumn LIKE ?";
                $params = ["%$search%"];
            }
        }
        
        $sql .= " ORDER BY $dateColumn DESC";
        
        error_log("Users SQL Query: " . $sql);
        error_log("Users SQL Params: " . json_encode($params));
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug logging
        error_log("Found " . count($users) . " users in database");
        if (count($users) > 0) {
            error_log("First user: " . json_encode($users[0]));
        }
        
        return $users;
    } catch (Exception $e) {
        error_log("Error getting users: " . $e->getMessage());
        error_log("SQL Error: " . $e->getTraceAsString());
        return [];
    }
}

// Get all rewards with improved error handling
function getRewards($pdo) {
    try {
        // Check if rewards table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'rewards'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            error_log("Rewards table does not exist");
            return []; // Return empty array if table doesn't exist
        }
        
        // Check if user_rewards table exists for redemption count
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'user_rewards'");
        $stmt->execute();
        $hasUserRewards = $stmt->fetch();
        
        if ($hasUserRewards) {
            $sql = "
                SELECT 
                    r.reward_id,
                    r.name,
                    r.description,
                    r.points_cost,
                    r.category,
                    r.inventory,
                    r.is_active,
                    COALESCE(redemption_count.total, 0) as total_redemptions
                FROM rewards r
                LEFT JOIN (
                    SELECT reward_id, COUNT(*) as total 
                    FROM user_rewards 
                    GROUP BY reward_id
                ) redemption_count ON r.reward_id = redemption_count.reward_id
                ORDER BY r.reward_id DESC
            ";
        } else {
            $sql = "
                SELECT 
                    r.reward_id,
                    r.name,
                    r.description,
                    r.points_cost,
                    r.category,
                    r.inventory,
                    r.is_active,
                    0 as total_redemptions
                FROM rewards r
                ORDER BY r.reward_id DESC
            ";
        }
        
        error_log("Rewards SQL Query: " . $sql);
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("Found " . count($rewards) . " rewards in database");
        if (count($rewards) > 0) {
            error_log("First reward: " . json_encode($rewards[0]));
        }
        
        return $rewards;
    } catch (Exception $e) {
        error_log("Error getting rewards: " . $e->getMessage());
        return [];
    }
}

// User management functions (keeping existing implementations but with better error handling)
function addUser($data, $pdo) {
    try {
        if (empty($data['email']) || empty($data['password'])) {
            return ['success' => false, 'message' => 'Email and password are required'];
        }
        
        $pdo->beginTransaction();
        
        // Check what columns exist
        $stmt = $pdo->prepare("DESCRIBE users");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $emailColumn = in_array('college_email', $columns) ? 'college_email' : 'email';
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE $emailColumn = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        // Build insert query based on available columns
        $insertData = [
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'points_balance' => (int)($data['points_balance'] ?? 0),
            'account_status' => 'active',
            'email_verified' => 1
        ];

        if (in_array('college_email', $columns)) {
            $insertData['college_email'] = $data['email'];
        }

        if (in_array('total_points_earned', $columns)) {
            $insertData['total_points_earned'] = (int)($data['points_balance'] ?? 0);
        }

        // Only add registration_date if the column exists
        if (in_array('registration_date', $columns)) {
            $insertData['registration_date'] = date('Y-m-d H:i:s');
        }

        // Only add created_at if the column exists
        if (in_array('created_at', $columns)) {
            $insertData['created_at'] = date('Y-m-d H:i:s');
        }

        $insertColumns = array_keys($insertData);
        $placeholders = array_fill(0, count($insertColumns), '?');

        $sql = "INSERT INTO users (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($insertData));
        
        $newUserId = $pdo->lastInsertId();
        
        // Create profile - check what columns exist in user_profiles
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'user_profiles'");
        $stmt->execute();
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("DESCRIBE user_profiles");
            $stmt->execute();
            $profileColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $profileData = [
                'user_id' => $newUserId
            ];
            
            $firstName = $data['first_name'] ?? '';
            $lastName = $data['last_name'] ?? '';
            $displayName = trim($firstName . ' ' . $lastName);
            $studentId = $data['student_id'] ?? null;
            
            if (in_array('first_name', $profileColumns)) {
                $profileData['first_name'] = $firstName ?: null;
            }
            
            if (in_array('last_name', $profileColumns)) {
                $profileData['last_name'] = $lastName ?: null;
            }
            
            if (in_array('display_name', $profileColumns)) {
                $profileData['display_name'] = $displayName ?: null;
            }
            
            if (in_array('full_name', $profileColumns)) {
                $profileData['full_name'] = $displayName ?: null;
            }
            
            if (in_array('student_number', $profileColumns)) {
                $profileData['student_number'] = $studentId;
            }
            
            if (in_array('student_id', $profileColumns)) {
                $profileData['student_id'] = $studentId;
            }
            
            // Only add created_at if the column exists
            if (in_array('created_at', $profileColumns)) {
                $profileData['created_at'] = date('Y-m-d H:i:s');
            }
            
            $profileInsertColumns = array_keys($profileData);
            $profilePlaceholders = array_fill(0, count($profileInsertColumns), '?');
            
            $profileSql = "INSERT INTO user_profiles (" . implode(', ', $profileInsertColumns) . ") VALUES (" . implode(', ', $profilePlaceholders) . ")";
            $stmt = $pdo->prepare($profileSql);
            $stmt->execute(array_values($profileData));
        }
        
        // Initialize leaderboard cache if table exists
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'leaderboard_cache'");
            $stmt->execute();
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("
                    INSERT INTO leaderboard_cache (user_id, total_points, total_scans) 
                    VALUES (?, ?, 0)
                ");
                $stmt->execute([$newUserId, (int)($data['points_balance'] ?? 0)]);
            }
        } catch (PDOException $e) {
            // Ignore if leaderboard_cache table doesn't exist
            error_log("Leaderboard cache update failed: " . $e->getMessage());
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'User created successfully'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error adding user: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create user: ' . $e->getMessage()];
    }
}

function editUser($data, $pdo) {
    try {
        $userId = $data['user_id'];
        
        $pdo->beginTransaction();
        
        // Check what columns exist in users table
        $stmt = $pdo->prepare("DESCRIBE users");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Build update query based on available columns
        $updateParts = ['email = ?', 'points_balance = ?', 'account_status = ?'];
        $updateParams = [
            $data['email'],
            (int)$data['points_balance'],
            $data['account_status']
        ];
        
        // Add password update if provided
        if (!empty($data['password'])) {
            $updateParts[] = 'password_hash = ?';
            $updateParams[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (in_array('college_email', $columns)) {
            $updateParts[] = 'college_email = ?';
            $updateParams[] = $data['email'];
        }
        
        if (in_array('total_points_earned', $columns)) {
            $updateParts[] = 'total_points_earned = ?';
            $updateParams[] = (int)$data['points_balance'];
        }
        
        $updateParams[] = $userId;
        
        $sql = "UPDATE users SET " . implode(', ', $updateParts) . " WHERE user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateParams);
        
        // Update profile - check what columns exist
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'user_profiles'");
        $stmt->execute();
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("DESCRIBE user_profiles");
            $stmt->execute();
            $profileColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $profileUpdateParts = [];
            $profileUpdateParams = [];
            
            $firstName = $data['first_name'] ?? '';
            $lastName = $data['last_name'] ?? '';
            $displayName = trim($firstName . ' ' . $lastName);
            $studentId = $data['student_id'] ?? null;
            
            if (in_array('first_name', $profileColumns)) {
                $profileUpdateParts[] = 'first_name = ?';
                $profileUpdateParams[] = $firstName ?: null;
            }
            
            if (in_array('last_name', $profileColumns)) {
                $profileUpdateParts[] = 'last_name = ?';
                $profileUpdateParams[] = $lastName ?: null;
            }
            
            if (in_array('display_name', $profileColumns)) {
                $profileUpdateParts[] = 'display_name = ?';
                $profileUpdateParams[] = $displayName ?: null;
            }
            
            if (in_array('full_name', $profileColumns)) {
                $profileUpdateParts[] = 'full_name = ?';
                $profileUpdateParams[] = $displayName ?: null;
            }
            
            if (in_array('student_number', $profileColumns)) {
                $profileUpdateParts[] = 'student_number = ?';
                $profileUpdateParams[] = $studentId;
            }
            
            if (in_array('student_id', $profileColumns)) {
                $profileUpdateParts[] = 'student_id = ?';
                $profileUpdateParams[] = $studentId;
            }
            
            if (!empty($profileUpdateParts)) {
                $profileUpdateParams[] = $userId;
                $profileSql = "UPDATE user_profiles SET " . implode(', ', $profileUpdateParts) . " WHERE user_id = ?";
                $stmt = $pdo->prepare($profileSql);
                $stmt->execute($profileUpdateParams);
            }
        }
        
        // Update leaderboard cache if table exists
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'leaderboard_cache'");
            $stmt->execute();
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE leaderboard_cache SET total_points = ? WHERE user_id = ?");
                $stmt->execute([(int)$data['points_balance'], $userId]);
            }
        } catch (PDOException $e) {
            error_log("Leaderboard cache update failed: " . $e->getMessage());
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'User updated successfully'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error editing user: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update user: ' . $e->getMessage()];
    }
}

function deleteUser($userId, $pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET account_status = 'deleted' WHERE user_id = ?");
        $stmt->execute([$userId]);
        return ['success' => true, 'message' => 'User deleted successfully'];
    } catch (Exception $e) {
        error_log("Error deleting user: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete user'];
    }
}

function toggleUserStatus($userId, $newStatus, $pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET account_status = ? WHERE user_id = ?");
        $stmt->execute([$newStatus, $userId]);
        return ['success' => true, 'message' => 'User status updated successfully'];
    } catch (Exception $e) {
        error_log("Error toggling user status: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update user status'];
    }
}

// Reward management functions
function addReward($data, $pdo) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO rewards (name, description, points_cost, category, inventory, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, TRUE, NOW())
        ");
        $stmt->execute([
            $data['name'],
            $data['description'],
            (int)$data['points_cost'],
            $data['category'] ?: null,
            $data['inventory'] ? (int)$data['inventory'] : null
        ]);
        return ['success' => true, 'message' => 'Reward created successfully'];
    } catch (Exception $e) {
        error_log("Error adding reward: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create reward'];
    }
}

function editReward($data, $pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE rewards SET 
                name = ?, 
                description = ?, 
                points_cost = ?, 
                category = ?, 
                inventory = ?
            WHERE reward_id = ?
        ");
        $stmt->execute([
            $data['name'],
            $data['description'],
            (int)$data['points_cost'],
            $data['category'] ?: null,
            $data['inventory'] ? (int)$data['inventory'] : null,
            $data['reward_id']
        ]);
        return ['success' => true, 'message' => 'Reward updated successfully'];
    } catch (Exception $e) {
        error_log("Error editing reward: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update reward'];
    }
}

function deleteReward($rewardId, $pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM rewards WHERE reward_id = ?");
        $stmt->execute([$rewardId]);
        return ['success' => true, 'message' => 'Reward deleted successfully'];
    } catch (Exception $e) {
        error_log("Error deleting reward: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete reward'];
    }
}

// Get data for display
$stats = getDashboardStats($pdo);
$users = getUsers($pdo, $_GET['search'] ?? '');
$rewards = getRewards($pdo);

// Debug information - always show for now
echo "<pre style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
echo "Debug Information:\n";
echo "Database: " . $dbname . "\n";
echo "Total users found: " . count($users) . "\n";
echo "Total rewards found: " . count($rewards) . "\n";
echo "Stats: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n";
if (count($users) > 0) {
    echo "First user: " . json_encode($users[0], JSON_PRETTY_PRINT) . "\n";
}
if (count($rewards) > 0) {
    echo "First reward: " . json_encode($rewards[0], JSON_PRETTY_PRINT) . "\n";
}
echo "</pre>";

// Debug information
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Debug Information:\n";
    echo "Total users found: " . count($users) . "\n";
    echo "Total rewards found: " . count($rewards) . "\n";
    echo "Stats: " . json_encode($stats, JSON_PRETTY_PRINT) . "\n";
    if (count($users) > 0) {
        echo "First user: " . json_encode($users[0], JSON_PRETTY_PRINT) . "\n";
    }
    echo "</pre>";
}

// Get user for editing if requested
$editUser = null;
if (isset($_GET['edit_user'])) {
    $stmt = $pdo->prepare("
        SELECT u.*, up.first_name, up.last_name, up.student_number, up.student_id
        FROM users u 
        LEFT JOIN user_profiles up ON u.user_id = up.user_id 
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_GET['edit_user']]);
    $editUser = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get reward for editing if requested
$editReward = null;
if (isset($_GET['edit_reward'])) {
    $stmt = $pdo->prepare("SELECT * FROM rewards WHERE reward_id = ?");
    $stmt->execute([$_GET['edit_reward']]);
    $editReward = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Adbeam Recycling</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-2px);
        }
        .sidebar {
            min-height: 100vh;
            background: #f8f9fa;
        }
        .main-content {
            min-height: 100vh;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-3">
                <h4 class="text-primary">Adbeam Admin</h4>
                <hr>
                <ul class="nav nav-pills flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="#dashboard" data-bs-toggle="pill">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#users" data-bs-toggle="pill">
                            <i class="bi bi-people"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#rewards" data-bs-toggle="pill">
                            <i class="bi bi-gift"></i> Rewards
                        </a>
                    </li>
                </ul>
                <hr>
                <a href="../../assets/dashboard.html" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i> Back to User Dashboard
                </a>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 main-content p-4">
                <?php if ($message): ?>
                    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show">
                        <?= htmlspecialchars($message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Dashboard Tab -->
                    <div class="tab-pane fade show active" id="dashboard">
                        <h2>Dashboard Overview</h2>
                        
                        <!-- Statistics Cards -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="bi bi-people fs-1 text-primary"></i>
                                        <h3><?= number_format($stats['total_users']) ?></h3>
                                        <p class="text-muted">Total Users</p>
                                        <small class="text-success"><?= $stats['active_users'] ?> active</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="bi bi-recycle fs-1 text-success"></i>
                                        <h3><?= number_format($stats['total_scans']) ?></h3>
                                        <p class="text-muted">Items Recycled</p>
                                        <small class="text-info"><?= $stats['activities_today'] ?> today</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="bi bi-coin fs-1 text-warning"></i>
                                        <h3><?= number_format($stats['total_points_awarded']) ?></h3>
                                        <p class="text-muted">Points Awarded</p>
                                        <small class="text-primary"><?= $stats['activities_this_week'] ?> this week</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card stat-card">
                                    <div class="card-body text-center">
                                        <i class="bi bi-gift fs-1 text-danger"></i>
                                        <h3><?= number_format($stats['total_redemptions']) ?></h3>
                                        <p class="text-muted">Rewards Redeemed</p>
                                        <small class="text-success"><?= $stats['active_rewards'] ?> active rewards</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="card">
                            <div class="card-header">
                                <h5>Recent Users</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Points</th>
                                                <th>Status</th>
                                                <th>Registered</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($users, 0, 5) as $user): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($user['full_name'] ?: 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                                    <td><?= $user['points_balance'] ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $user['account_status'] === 'active' ? 'success' : 'secondary' ?>">
                                                            <?= $user['account_status'] ?>
                                                        </span>
                                                    </td>
                                                    <td><?= date('M j, Y', strtotime($user['registration_date'])) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Users Tab -->
                    <div class="tab-pane fade" id="users">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2>User Management</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                                <i class="bi bi-plus"></i> Add User
                            </button>
                        </div>

                        <!-- Search -->
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <form method="GET" class="d-flex">
                                    <input type="text" name="search" class="form-control" placeholder="Search users..." 
                                           value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                                    <button type="submit" class="btn btn-outline-secondary ms-2">Search</button>
                                </form>
                            </div>
                        </div>

                        <!-- Users Table -->
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>Student ID</th>
                                                <th>Points</th>
                                                <th>Activities</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td><?= $user['user_id'] ?></td>
                                                    <td><?= htmlspecialchars($user['full_name'] ?: 'N/A') ?></td>
                                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                                    <td><?= htmlspecialchars($user['student_id'] ?: 'N/A') ?></td>
                                                    <td><?= $user['points_balance'] ?></td>
                                                    <td><?= $user['total_activities'] ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $user['account_status'] === 'active' ? 'success' : 'secondary' ?>">
                                                            <?= $user['account_status'] ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="?edit_user=<?= $user['user_id'] ?>#users" class="btn btn-outline-primary" title="Edit User">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                                                <input type="hidden" name="action" value="toggle_user_status">
                                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                                <input type="hidden" name="new_status" value="<?= $user['account_status'] === 'active' ? 'suspended' : 'active' ?>">
                                                                <button type="submit" class="btn btn-outline-warning">
                                                                    <i class="bi bi-<?= $user['account_status'] === 'active' ? 'ban' : 'check' ?>"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                                <input type="hidden" name="action" value="delete_user">
                                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                                <button type="submit" class="btn btn-outline-danger">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Rewards Tab -->
                    <div class="tab-pane fade" id="rewards">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h2>Reward Management</h2>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#rewardModal">
                                <i class="bi bi-plus"></i> Add Reward
                            </button>
                        </div>

                        <!-- Rewards Table -->
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Category</th>
                                                <th>Points Cost</th>
                                                <th>Inventory</th>
                                                <th>Redemptions</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($rewards as $reward): ?>
                                                <tr>
                                                    <td><?= $reward['reward_id'] ?></td>
                                                    <td><?= htmlspecialchars($reward['name']) ?></td>
                                                    <td><?= htmlspecialchars($reward['category'] ?: 'General') ?></td>
                                                    <td><?= $reward['points_cost'] ?></td>
                                                    <td><?= $reward['inventory'] ?: '∞' ?></td>
                                                    <td><?= $reward['total_redemptions'] ?></td>
                                                    <td>
                                                        <span class="badge bg-<?= $reward['is_active'] ? 'success' : 'secondary' ?>">
                                                            <?= $reward['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <a href="?edit_reward=<?= $reward['reward_id'] ?>" class="btn btn-outline-primary"
                                                               data-bs-toggle="modal" data-bs-target="#rewardModal">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this reward?')">
                                                                <input type="hidden" name="action" value="delete_reward">
                                                                <input type="hidden" name="reward_id" value="<?= $reward['reward_id'] ?>">
                                                                <button type="submit" class="btn btn-outline-danger">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="userModalTitle"><?= $editUser ? 'Edit User' : 'Add User' ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?= $editUser ? 'edit_user' : 'add_user' ?>">
                        <?php if ($editUser): ?>
                            <input type="hidden" name="user_id" value="<?= $editUser['user_id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required 
                                   value="<?= htmlspecialchars($editUser['email'] ?? $editUser['college_email'] ?? '') ?>">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">First Name</label>
                                    <input type="text" name="first_name" class="form-control" 
                                           value="<?= htmlspecialchars($editUser['first_name'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" name="last_name" class="form-control" 
                                           value="<?= htmlspecialchars($editUser['last_name'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Student ID</label>
                            <input type="text" name="student_id" class="form-control" 
                                   value="<?= htmlspecialchars($editUser['student_number'] ?? $editUser['student_id'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Points Balance</label>
                            <input type="number" name="points_balance" class="form-control" min="0" 
                                   value="<?= $editUser['points_balance'] ?? 0 ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Account Status</label>
                            <select name="account_status" class="form-control">
                                <option value="active" <?= ($editUser['account_status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="suspended" <?= ($editUser['account_status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="pending" <?= ($editUser['account_status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                            </select>
                        </div>
                        
                        <?php if (!$editUser): ?>
                            <div class="mb-3" id="passwordField">
                                <label class="form-label">Password *</label>
                                <input type="password" name="password" class="form-control" required minlength="8">
                                <small class="form-text text-muted">Minimum 8 characters required</small>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Leave password field empty to keep current password
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password (optional)</label>
                                <input type="password" name="password" class="form-control" minlength="8">
                                <small class="form-text text-muted">Only fill if you want to change the password</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <?= $editUser ? 'Update User' : 'Create User' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reward Modal -->
    <div class="modal fade" id="rewardModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><?= $editReward ? 'Edit Reward' : 'Add Reward' ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="<?= $editReward ? 'edit_reward' : 'add_reward' ?>">
                        <?php if ($editReward): ?>
                            <input type="hidden" name="reward_id" value="<?= $editReward['reward_id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Reward Name</label>
                            <input type="text" name="name" class="form-control" required 
                                   value="<?= htmlspecialchars($editReward['name'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" required><?= htmlspecialchars($editReward['description'] ?? '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Points Cost</label>
                            <input type="number" name="points_cost" class="form-control" min="1" required 
                                   value="<?= $editReward['points_cost'] ?? '' ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-control">
                                <option value="">Select Category</option>
                                <option value="food" <?= ($editReward['category'] ?? '') === 'food' ? 'selected' : '' ?>>Food & Beverage</option>
                                <option value="discount" <?= ($editReward['category'] ?? '') === 'discount' ? 'selected' : '' ?>>Discounts</option>
                                <option value="service" <?= ($editReward['category'] ?? '') === 'service' ? 'selected' : '' ?>>Services</option>
                                <option value="transportation" <?= ($editReward['category'] ?? '') === 'transportation' ? 'selected' : '' ?>>Transportation</option>
                                <option value="experience" <?= ($editReward['category'] ?? '') === 'experience' ? 'selected' : '' ?>>Experiences</option>
                                <option value="general" <?= ($editReward['category'] ?? '') === 'general' ? 'selected' : '' ?>>General</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Inventory (leave blank for unlimited)</label>
                            <input type="number" name="inventory" class="form-control" min="0" 
                                   value="<?= $editReward['inventory'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary"><?= $editReward ? 'Update' : 'Create' ?> Reward</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-show modals if editing
        <?php if ($editUser): ?>
            document.addEventListener('DOMContentLoaded', function() {
                // Show the users tab
                const usersTab = document.querySelector('a[href="#users"]');
                const usersTabPane = document.getElementById('users');
                const dashboardTab = document.querySelector('a[href="#dashboard"]');
                const dashboardTabPane = document.getElementById('dashboard');
                
                // Switch to users tab
                dashboardTab.classList.remove('active');
                dashboardTabPane.classList.remove('show', 'active');
                usersTab.classList.add('active');
                usersTabPane.classList.add('show', 'active');
                
                // Show the modal
                const userModal = new bootstrap.Modal(document.getElementById('userModal'));
                userModal.show();
            });
        <?php endif; ?>
        
        <?php if ($editReward): ?>
            document.addEventListener('DOMContentLoaded', function() {
                // Show the rewards tab
                const rewardsTab = document.querySelector('a[href="#rewards"]');
                const rewardsTabPane = document.getElementById('rewards');
                const dashboardTab = document.querySelector('a[href="#dashboard"]');
                const dashboardTabPane = document.getElementById('dashboard');
                
                // Switch to rewards tab
                dashboardTab.classList.remove('active');
                dashboardTabPane.classList.remove('show', 'active');
                rewardsTab.classList.add('active');
                rewardsTabPane.classList.add('show', 'active');
                
                // Show the modal
                const rewardModal = new bootstrap.Modal(document.getElementById('rewardModal'));
                rewardModal.show();
            });
        <?php endif; ?>
        
        // Reset form when adding new user
        document.addEventListener('DOMContentLoaded', function() {
            const addUserBtn = document.querySelector('button[data-bs-target="#userModal"]');
            if (addUserBtn) {
                addUserBtn.addEventListener('click', function() {
                    // Reset the form
                    const form = document.querySelector('#userModal form');
                    if (form) {
                        form.reset();
                        // Update modal title and action
                        document.getElementById('userModalTitle').textContent = 'Add User';
                        document.querySelector('input[name="action"]').value = 'add_user';
                        // Remove user_id hidden field if it exists
                        const userIdField = document.querySelector('input[name="user_id"]');
                        if (userIdField) {
                            userIdField.remove();
                        }
                        // Show password field
                        const passwordField = document.getElementById('passwordField');
                        if (passwordField) {
                            passwordField.style.display = 'block';
                            passwordField.querySelector('input').required = true;
                        }
                    }
                });
            }
        });
        
        // Auto-dismiss alerts
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('show')) {
                    bootstrap.Alert.getOrCreateInstance(alert).close();
                }
            });
        }, 5000);
    </script>
</body>
</html>
