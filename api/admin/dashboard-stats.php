<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

session_start();
require_once '../../includes/db_connect.php';

// Check if user is authenticated and is an admin
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

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

try {
    $timeframe = $_GET['timeframe'] ?? '30'; // days
    $institutionId = $_GET['institution_id'] ?? null;
    
    // Get overall user statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT u.user_id) as total_users,
            COUNT(DISTINCT CASE WHEN u.account_status = 'active' THEN u.user_id END) as active_users,
            COUNT(DISTINCT CASE WHEN u.registration_date >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN u.user_id END) as new_users,
            COALESCE(SUM(u.points_balance), 0) as total_points_balance,
            COALESCE(SUM(u.total_points_earned), 0) as total_points_earned
        FROM users u
        WHERE u.account_status != 'deleted'
    ");
    $stmt->execute([$timeframe]);
    $userStats = $stmt->fetch();
    
    // Get scanning statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_scans,
            COUNT(DISTINCT si.user_id) as scanning_users,
            COALESCE(SUM(si.points_awarded), 0) as total_points_awarded,
            COALESCE(SUM(si.co2_saved), 0) as total_co2_saved,
            COUNT(CASE WHEN si.scan_timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 END) as recent_scans,
            COUNT(CASE WHEN DATE(si.scan_timestamp) = CURDATE() THEN 1 END) as activities_today,
            COUNT(CASE WHEN si.scan_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as activities_this_week
        FROM scanned_items si
        JOIN users u ON si.user_id = u.user_id
        WHERE u.account_status != 'deleted'
    ");
    $stmt->execute([$timeframe]);
    $scanStats = $stmt->fetch();
    
    // Get reward statistics
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT r.reward_id) as total_rewards,
            COUNT(DISTINCT CASE WHEN r.is_active = TRUE THEN r.reward_id END) as active_rewards,
            COUNT(DISTINCT ur.redemption_id) as total_redemptions,
            COALESCE(SUM(ur.points_used), 0) as total_points_redeemed,
            COUNT(CASE WHEN ur.redeemed_at >= DATE_SUB(NOW(), INTERVAL ? DAY) THEN 1 END) as recent_redemptions
        FROM rewards r
        LEFT JOIN user_rewards ur ON r.reward_id = ur.reward_id
    ");
    $stmt->execute([$timeframe]);
    $rewardStats = $stmt->fetch();
    
    // Get daily activity for charts (last 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(si.scan_timestamp) as date,
            COUNT(*) as scans,
            COUNT(DISTINCT si.user_id) as active_users,
            COALESCE(SUM(si.points_awarded), 0) as points_awarded,
            COALESCE(SUM(si.co2_saved), 0) as co2_saved
        FROM scanned_items si
        JOIN users u ON si.user_id = u.user_id
        WHERE si.scan_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND u.account_status != 'deleted'
        GROUP BY DATE(si.scan_timestamp)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $dailyActivity = $stmt->fetchAll();
    
    // Get material distribution
    $stmt = $pdo->prepare("
        SELECT 
            mt.name as material_name,
            mt.category,
            COUNT(si.scan_id) as scan_count,
            COALESCE(SUM(si.points_awarded), 0) as total_points,
            COALESCE(SUM(si.co2_saved), 0) as total_co2_saved
        FROM material_types mt
        LEFT JOIN scanned_items si ON mt.material_id = si.material_id
        LEFT JOIN users u ON si.user_id = u.user_id
        WHERE u.account_status != 'deleted' OR u.user_id IS NULL
        GROUP BY mt.material_id
        ORDER BY scan_count DESC
    ");
    $stmt->execute();
    $materialStats = $stmt->fetchAll();
    
    // Get top users
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            up.display_name,
            up.first_name,
            up.last_name,
            u.total_points_earned,
            u.current_level,
            COUNT(DISTINCT si.scan_id) as total_scans,
            COALESCE(SUM(si.co2_saved), 0) as total_co2_saved
        FROM users u
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        LEFT JOIN scanned_items si ON u.user_id = si.user_id
        WHERE u.account_status = 'active'
        GROUP BY u.user_id
        ORDER BY u.total_points_earned DESC
        LIMIT 10
    ");
    $stmt->execute();
    $topUsers = $stmt->fetchAll();
    
    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT 
            'scan' as activity_type,
            u.user_id,
            up.display_name,
            mt.name as item_name,
            si.points_awarded as points,
            si.scan_timestamp as timestamp
        FROM scanned_items si
        JOIN users u ON si.user_id = u.user_id
        JOIN user_profiles up ON u.user_id = up.user_id
        JOIN material_types mt ON si.material_id = mt.material_id
        WHERE u.account_status != 'deleted'
        
        UNION ALL
        
        SELECT 
            'redemption' as activity_type,
            u.user_id,
            up.display_name,
            r.name as item_name,
            -ur.points_used as points,
            ur.redeemed_at as timestamp
        FROM user_rewards ur
        JOIN users u ON ur.user_id = u.user_id
        JOIN user_profiles up ON u.user_id = up.user_id
        JOIN rewards r ON ur.reward_id = r.reward_id
        WHERE u.account_status != 'deleted'
        
        ORDER BY timestamp DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recentActivity = $stmt->fetchAll();
    
    $response = [
        'success' => true,
        'timeframe' => $timeframe,
        'data' => [
            'total_users' => (int)$userStats['total_users'],
            'active_users' => (int)$userStats['active_users'],
            'new_users' => (int)$userStats['new_users'],
            'total_points_balance' => (int)$userStats['total_points_balance'],
            'total_points_earned' => (int)$userStats['total_points_earned'],
            'total_scans' => (int)$scanStats['total_scans'],
            'total_points_awarded' => (int)$scanStats['total_points_awarded'],
            'total_co2_saved' => (float)$scanStats['total_co2_saved'],
            'activities_today' => (int)$scanStats['activities_today'],
            'activities_this_week' => (int)$scanStats['activities_this_week'],
            'total_rewards' => (int)$rewardStats['total_rewards'],
            'active_rewards' => (int)$rewardStats['active_rewards'],
            'total_redemptions' => (int)$rewardStats['total_redemptions'],
            'total_points_redeemed' => (int)$rewardStats['total_points_redeemed']
        ],
        'daily_activity' => $dailyActivity,
        'material_stats' => $materialStats,
        'top_users' => $topUsers,
        'recent_activity' => $recentActivity
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load dashboard statistics: ' . $e->getMessage()
    ]);
}
?>
