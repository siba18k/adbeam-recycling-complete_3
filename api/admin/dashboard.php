<?php
session_start();
require_once '../../includes/db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Check if user is authenticated and is an admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check admin status from database
try {
    $stmt = $pdo->prepare("SELECT au.is_active FROM admin_users au WHERE au.user_id = ? AND au.is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    $isAdmin = $stmt->fetch();
    
    if (!$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Admin check failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Authentication error']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'stats':
            $stats = getDashboardStats($pdo);
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            throw new InvalidArgumentException("Invalid action");
    }

} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Exception $e) {
    http_response_code(500);
    error_log("Dashboard API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}

function getDashboardStats($pdo) {
    try {
        // User statistics
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT u.user_id) as total_users,
                COUNT(DISTINCT CASE WHEN u.account_status = 'active' THEN u.user_id END) as active_users,
                COUNT(DISTINCT CASE WHEN u.registration_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN u.user_id END) as new_users,
                COALESCE(SUM(u.points_balance), 0) as total_points_balance,
                COALESCE(SUM(u.total_points_earned), 0) as total_points_earned
            FROM users u
            WHERE u.account_status != 'deleted'
        ");
        $stmt->execute();
        $userStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Activity statistics with fallback for missing tables
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_scans,
                    COALESCE(SUM(si.points_awarded), 0) as total_points_awarded,
                    COUNT(CASE WHEN DATE(si.scan_timestamp) = CURDATE() THEN 1 END) as activities_today,
                    COUNT(CASE WHEN si.scan_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as activities_this_week
                FROM scanned_items si
                JOIN users u ON si.user_id = u.user_id
                WHERE u.account_status != 'deleted'
            ");
            $stmt->execute();
            $activityStats = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Fallback if scanned_items table doesn't exist
            $activityStats = [
                'total_scans' => 0,
                'total_points_awarded' => 0,
                'activities_today' => 0,
                'activities_this_week' => 0
            ];
        }
        
        // Reward statistics with fallback
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT r.reward_id) as total_rewards,
                    COUNT(DISTINCT CASE WHEN r.is_active = TRUE THEN r.reward_id END) as active_rewards
                FROM rewards r
            ");
            $stmt->execute();
            $rewardStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Get redemption count
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) as total_redemptions FROM user_rewards");
                $stmt->execute();
                $redemptionStats = $stmt->fetch(PDO::FETCH_ASSOC);
                $rewardStats = array_merge($rewardStats, $redemptionStats);
            } catch (PDOException $e) {
                $rewardStats['total_redemptions'] = 0;
            }
        } catch (PDOException $e) {
            // Fallback if rewards tables don't exist
            $rewardStats = [
                'total_rewards' => 0,
                'active_rewards' => 0,
                'total_redemptions' => 0
            ];
        }
        
        return array_merge($userStats, $activityStats, $rewardStats);
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
?>
