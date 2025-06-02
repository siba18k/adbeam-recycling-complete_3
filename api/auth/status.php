<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../includes/db_connect.php';

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get user basic info first
    $stmt = $pdo->prepare("SELECT points_balance, total_points_earned FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userInfo = $stmt->fetch();
    
    if (!$userInfo) {
        throw new Exception("User not found");
    }
    
    // Get activity stats - handle both recycling_activities and scanned_items tables
    $totalActivities = 0;
    $activitiesThisWeek = 0;
    $activitiesThisMonth = 0;
    
    // Try recycling_activities table first
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_activities,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as activities_this_week,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as activities_this_month
            FROM recycling_activities 
            WHERE user_id = ? AND status = 'verified'
        ");
        $stmt->execute([$userId]);
        $activityStats = $stmt->fetch();
        
        if ($activityStats) {
            $totalActivities = $activityStats['total_activities'];
            $activitiesThisWeek = $activityStats['activities_this_week'];
            $activitiesThisMonth = $activityStats['activities_this_month'];
        }
    } catch (PDOException $e) {
        error_log("Recycling_activities table not found, trying scanned_items: " . $e->getMessage());
        
        // Fallback to scanned_items table
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_activities,
                    COUNT(CASE WHEN scan_timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as activities_this_week,
                    COUNT(CASE WHEN scan_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as activities_this_month
                FROM scanned_items 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $activityStats = $stmt->fetch();
            
            if ($activityStats) {
                $totalActivities = $activityStats['total_activities'];
                $activitiesThisWeek = $activityStats['activities_this_week'];
                $activitiesThisMonth = $activityStats['activities_this_month'];
            }
        } catch (PDOException $e2) {
            error_log("Both activity tables failed: " . $e2->getMessage());
        }
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'points_balance' => (int)($userInfo['points_balance'] ?? 0),
            'total_points_earned' => (int)($userInfo['total_points_earned'] ?? 0),
            'total_activities' => (int)$totalActivities,
            'activities_today' => 0, // Placeholder
            'activities_this_week' => (int)$activitiesThisWeek,
            'activities_this_month' => (int)$activitiesThisMonth,
            'current_level' => 1,
            'level_name' => 'Eco Newbie'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("User stats error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load user stats: ' . $e->getMessage()]);
}
?>
