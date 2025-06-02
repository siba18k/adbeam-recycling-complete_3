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

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get user basic info and stats
    $stmt = $pdo->prepare("
        SELECT 
            u.user_id,
            u.college_email as email,
            u.points_balance,
            COALESCE(u.total_points_earned, u.points_balance) as total_points_earned,
            u.account_status,
            u.registration_date,
            u.last_login,
            up.first_name,
            up.last_name,
            COALESCE(up.display_name, CONCAT(COALESCE(up.first_name, ''), ' ', COALESCE(up.last_name, ''))) as display_name,
            COALESCE(up.student_number, up.student_id) as student_number
        FROM users u
        LEFT JOIN user_profiles up ON u.user_id = up.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$userId]);
    $userStats = $stmt->fetch();

    if (!$userStats) {
        throw new Exception("User not found");
    }

    // Get activity stats from multiple tables
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_activities,
            COALESCE(SUM(points_awarded), 0) as points_from_activities,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as activities_today,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as activities_this_week,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as activities_this_month,
            COALESCE(SUM(co2_saved), 0) as total_co2_saved
        FROM (
            SELECT points_awarded, created_at, co2_saved FROM recycling_activities WHERE user_id = ? AND status = 'verified'
            UNION ALL
            SELECT points_awarded, scan_time as created_at, 0 as co2_saved FROM scanned_items WHERE user_id = ? AND status = 'verified'
            UNION ALL
            SELECT points_awarded, scan_time as created_at, 0 as co2_saved FROM scanned_barcodes WHERE user_id = ?
        ) combined_activities
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $activityStats = $stmt->fetch();
    
    // Get redemption stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_redemptions,
            COALESCE(SUM(points_spent), 0) as points_spent
        FROM user_reward_redemptions
        WHERE user_id = ? AND status != 'cancelled'
    ");
    $stmt->execute([$userId]);
    $redemptionStats = $stmt->fetch();
    
    // Calculate level information
    $totalPoints = (int)$userStats['total_points_earned'];
    $currentLevel = max(1, floor($totalPoints / 100) + 1);
    $pointsInLevel = $totalPoints % 100;
    $pointsToNext = 100 - $pointsInLevel;
    $progressPercentage = ($pointsInLevel / 100) * 100;
    
    $levelNames = [
        1 => 'Eco Newbie', 2 => 'Green Starter', 3 => 'Recycling Rookie',
        4 => 'Eco Warrior', 5 => 'Green Champion', 6 => 'Sustainability Star',
        7 => 'Environmental Hero', 8 => 'Planet Protector', 9 => 'Eco Legend', 10 => 'Green Master'
    ];
    $levelName = $levelNames[$currentLevel] ?? 'Eco Master Level ' . ($currentLevel - 9);
    
    // Get user ranking
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as position
        FROM users 
        WHERE COALESCE(total_points_earned, points_balance) > ? AND account_status = 'active'
    ");
    $stmt->execute([$totalPoints]);
    $rankingData = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE account_status = 'active'");
    $stmt->execute();
    $totalUsers = $stmt->fetch();
    
    // Get material breakdown
    $stmt = $pdo->prepare("
        SELECT 
            material_type,
            COUNT(*) as count,
            COALESCE(SUM(points_awarded), 0) as points,
            COALESCE(SUM(co2_saved), 0) as co2_saved
        FROM (
            SELECT material_type, points_awarded, co2_saved FROM recycling_activities WHERE user_id = ? AND status = 'verified'
            UNION ALL
            SELECT material_type, points_awarded, 0 as co2_saved FROM scanned_items WHERE user_id = ? AND status = 'verified'
            UNION ALL
            SELECT material_type, points_awarded, 0 as co2_saved FROM scanned_barcodes WHERE user_id = ?
        ) combined_materials
        WHERE material_type IS NOT NULL
        GROUP BY material_type
        ORDER BY count DESC
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $materialBreakdown = $stmt->fetchAll();
    
    // Get achievements (enhanced logic)
    $achievements = [];
    if ($totalPoints >= 100) $achievements[] = ['name' => 'First Century', 'description' => 'Earned your first 100 points'];
    if ($activityStats['total_activities'] >= 10) $achievements[] = ['name' => 'Recycling Enthusiast', 'description' => 'Completed 10 recycling activities'];
    if ($activityStats['total_co2_saved'] >= 1) $achievements[] = ['name' => 'Planet Saver', 'description' => 'Saved 1kg of CO2'];
    if ($activityStats['activities_this_week'] >= 7) $achievements[] = ['name' => 'Weekly Warrior', 'description' => 'Recycled every day this week'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'user_info' => [
                'name' => $userStats['display_name'] ?: 'User',
                'email' => $userStats['email'],
                'points' => (int)$userStats['points_balance']
            ],
            'points' => [
                'balance' => (int)$userStats['points_balance'],
                'total_earned' => $totalPoints
            ],
            'activities' => [
                'total' => (int)$activityStats['total_activities'],
                'today' => (int)$activityStats['activities_today'],
                'this_week' => (int)$activityStats['activities_this_week'],
                'this_month' => (int)$activityStats['activities_this_month']
            ],
            'redemptions' => [
                'total' => (int)$redemptionStats['total_redemptions'],
                'points_spent' => (int)$redemptionStats['points_spent']
            ],
            'environmental_impact' => [
                'total_co2_saved' => (float)$activityStats['total_co2_saved']
            ],
            'level' => [
                'current_level' => $currentLevel,
                'level_name' => $levelName,
                'points_in_level' => $pointsInLevel,
                'points_to_next' => $pointsToNext,
                'progress_percentage' => $progressPercentage
            ],
            'ranking' => [
                'position' => (int)$rankingData['position'],
                'total_users' => (int)$totalUsers['total']
            ],
            'material_breakdown' => $materialBreakdown,
            'achievements' => $achievements
        ]
    ]);
    
} catch (Exception $e) {
    error_log("User stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load user stats: ' . $e->getMessage()]);
}
?>
