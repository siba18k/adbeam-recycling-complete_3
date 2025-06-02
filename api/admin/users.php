<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/admin_auth.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication and admin status
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Verify admin status
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetchColumn() == 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $search = $_GET['search'] ?? '';
            $users = getUsers($pdo, $search);
            echo json_encode(['success' => true, 'data' => $users]);
            break;
            
        case 'get':
            $userId = $_GET['user_id'] ?? 0;
            if (!$userId) {
                echo json_encode(['success' => false, 'message' => 'User ID required']);
                break;
            }
            
            $user = getUserById($pdo, $userId);
            if ($user) {
                echo json_encode(['success' => true, 'data' => $user]);
            } else {
                echo json_encode(['success' => false, 'message' => 'User not found']);
            }
            break;
            
        case 'add':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                echo json_encode(['success' => false, 'message' => 'Invalid input data']);
                break;
            }
            
            $result = addUser($input, $pdo);
            echo json_encode($result);
            break;
            
        case 'update':
            $userId = $_GET['user_id'] ?? 0;
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$userId || !$input) {
                echo json_encode(['success' => false, 'message' => 'User ID and data required']);
                break;
            }
            
            $input['user_id'] = $userId;
            $result = updateUser($input, $pdo);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Users API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

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
        
        // Build dynamic query
        $emailColumn = in_array('college_email', $userColumns) ? 'u.college_email' : 'u.email';
        
        $sql = "
            SELECT 
                u.user_id, 
                $emailColumn as email, 
                COALESCE(u.points_balance, 0) as points_balance, 
                u.account_status,
                u.registration_date
        ";
        
        if (in_array('last_login', $userColumns)) {
            $sql .= ", u.last_login";
        } else {
            $sql .= ", NULL as last_login";
        }
        
        if ($profileTableExists) {
            $sql .= ",
                COALESCE(up.first_name, '') as first_name,
                COALESCE(up.last_name, '') as last_name,
                COALESCE(
                    up.display_name, 
                    up.full_name,
                    CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))
                ) as full_name,
                COALESCE(up.student_number, up.student_id) as student_id
            FROM users u
            LEFT JOIN user_profiles up ON u.user_id = up.user_id";
        } else {
            $sql .= ",
                '' as first_name,
                '' as last_name,
                'N/A' as full_name,
                '' as student_id
            FROM users u";
        }
        
        $sql .= " WHERE u.account_status != 'deleted'";
        
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
        
        $sql .= " ORDER BY u.registration_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("API getUsers found " . count($result) . " users");
        return $result;
    } catch (Exception $e) {
        error_log("Error getting users in API: " . $e->getMessage());
        return [];
    }
}

function getUserById($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("DESCRIBE users");
        $stmt->execute();
        $userColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $emailColumn = in_array('college_email', $userColumns) ? 'u.college_email' : 'u.email';
        
        $stmt = $pdo->prepare("
            SELECT u.*, 
                   $emailColumn as email,
                   up.first_name, 
                   up.last_name, 
                   up.student_number, 
                   up.student_id
            FROM users u 
            LEFT JOIN user_profiles up ON u.user_id = up.user_id 
            WHERE u.user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting user by ID: " . $e->getMessage());
        return null;
    }
}

function addUser($data, $pdo) {
    try {
        if (empty($data['email']) || empty($data['password'])) {
            return ['success' => false, 'message' => 'Email and password are required'];
        }
        
        $pdo->beginTransaction();
        
        // Check available columns
        $stmt = $pdo->prepare("DESCRIBE users");
        $stmt->execute();
        $userColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $emailColumn = in_array('college_email', $userColumns) ? 'college_email' : 'email';
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE $emailColumn = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        // Build insert query dynamically
        $insertData = [
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'points_balance' => (int)($data['points_balance'] ?? 0),
            'account_status' => 'active',
            'email_verified' => true
        ];
        
        if (in_array('college_email', $userColumns)) {
            $insertData['college_email'] = $data['email'];
        }
        
        if (in_array('total_points_earned', $userColumns)) {
            $insertData['total_points_earned'] = (int)($data['points_balance'] ?? 0);
        }
        
        $columns = array_keys($insertData);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO users (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_values($insertData));
        
        $newUserId = $pdo->lastInsertId();
        
        // Create profile
        $profileData = [
            'user_id' => $newUserId,
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'student_id' => $data['student_id'] ?? null
        ];
        
        // Add display_name and full_name if columns exist
        $stmt = $pdo->prepare("DESCRIBE user_profiles");
        $stmt->execute();
        $profileColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $displayName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        if (in_array('display_name', $profileColumns)) {
            $profileData['display_name'] = $displayName ?: null;
        }
        if (in_array('full_name', $profileColumns)) {
            $profileData['full_name'] = $displayName ?: null;
        }
        if (in_array('student_number', $profileColumns)) {
            $profileData['student_number'] = $data['student_id'] ?? null;
        }
        
        $profileColumns = array_keys($profileData);
        $profilePlaceholders = array_fill(0, count($profileColumns), '?');
        
        $profileSql = "INSERT INTO user_profiles (" . implode(', ', $profileColumns) . ") VALUES (" . implode(', ', $profilePlaceholders) . ")";
        $stmt = $pdo->prepare($profileSql);
        $stmt->execute(array_values($profileData));
        
        $pdo->commit();
        return ['success' => true, 'message' => 'User created successfully'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error adding user: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create user: ' . $e->getMessage()];
    }
}

function updateUser($data, $pdo) {
    try {
        $userId = $data['user_id'];
        
        $pdo->beginTransaction();
        
        // Get current user data first
        $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$currentUser) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Check available columns
        $stmt = $pdo->prepare("DESCRIBE users");
        $stmt->execute();
        $userColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Build update data
        $updateData = [];
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (isset($data['points_balance'])) $updateData['points_balance'] = (int)$data['points_balance'];
        if (isset($data['account_status'])) $updateData['account_status'] = $data['account_status'];
        
        if (in_array('college_email', $userColumns) && isset($data['email'])) {
            $updateData['college_email'] = $data['email'];
        }
        
        if (in_array('total_points_earned', $userColumns) && isset($data['points_balance'])) {
            $updateData['total_points_earned'] = (int)$data['points_balance'];
        }
        
        if (!empty($updateData)) {
            $setParts = array_map(function($col) { return "$col = ?"; }, array_keys($updateData));
            $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE user_id = ?";
            $params = array_values($updateData);
            $params[] = $userId;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        // Update profile if data provided
        $profileData = [];
        if (isset($data['first_name'])) $profileData['first_name'] = $data['first_name'] ?: null;
        if (isset($data['last_name'])) $profileData['last_name'] = $data['last_name'] ?: null;
        if (isset($data['student_id'])) $profileData['student_id'] = $data['student_id'] ?: null;
        
        if (!empty($profileData)) {
            // Check if profile exists
            $stmt = $pdo->prepare("SELECT profile_id FROM user_profiles WHERE user_id = ?");
            $stmt->execute([$userId]);
            $profileExists = $stmt->fetch();
            
            $stmt = $pdo->prepare("DESCRIBE user_profiles");
            $stmt->execute();
            $profileColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $displayName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
            if (in_array('display_name', $profileColumns)) {
                $profileData['display_name'] = $displayName ?: null;
            }
            if (in_array('full_name', $profileColumns)) {
                $profileData['full_name'] = $displayName ?: null;
            }
            if (in_array('student_number', $profileColumns) && isset($data['student_id'])) {
                $profileData['student_number'] = $data['student_id'] ?: null;
            }
            
            if ($profileExists) {
                $setParts = array_map(function($col) { return "$col = ?"; }, array_keys($profileData));
                $sql = "UPDATE user_profiles SET " . implode(', ', $setParts) . " WHERE user_id = ?";
                $params = array_values($profileData);
                $params[] = $userId;
            } else {
                $profileData['user_id'] = $userId;
                $columns = array_keys($profileData);
                $placeholders = array_fill(0, count($columns), '?');
                $sql = "INSERT INTO user_profiles (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $profilePlaceholders) . ")";
                $params = array_values($profileData);
            }
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }
        
        $pdo->commit();
        return ['success' => true, 'message' => 'User updated successfully'];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error updating user: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update user: ' . $e->getMessage()];
    }
}
?>
