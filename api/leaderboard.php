<?php
header("Content-Type: application/json");
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';

try {
    $period = $_GET['period'] ?? 'week';
    $university = $_GET['university'] ?? 'all';
    $type = $_GET['type'] ?? 'individual';

    // Date range based on period
    $dateCondition = '';
    switch ($period) {
        case 'week':
            $dateCondition = "AND DATE(scan_timestamp) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            break;
        case 'month':
            $dateCondition = "AND DATE(scan_timestamp) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'semester':
            $dateCondition = "AND DATE(scan_timestamp) >= DATE_SUB(CURDATE(), INTERVAL 120 DAY)";
            break;
        case 'all':
        default:
            $dateCondition = '';
            break;
    }

    // University filter
    $universityCondition = '';
    if ($university !== 'all') {
        $universityCondition = "AND up.university_code = :university";
    }

    if ($type === 'individual') {
        // Individual leaderboard
        $sql = "
            SELECT 
                u.user_id,
                up.full_name,
                up.university,
                up.university_code,
                COALESCE(SUM(sb.points_earned), 0) as total_points,
                COUNT(sb.scan_id) as total_items,
                COALESCE(SUM(sb.environmental_impact), 0) as co2_saved
            FROM users u
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            LEFT JOIN scanned_barcodes sb ON u.user_id = sb.user_id $dateCondition
            WHERE 1=1 $universityCondition
            GROUP BY u.user_id, up.full_name, up.university, up.university_code
            ORDER BY total_points DESC
            LIMIT 50
        ";
    } else {
        // University leaderboard
        $sql = "
            SELECT 
                up.university,
                up.university_code,
                COUNT(DISTINCT u.user_id) as student_count,
                COALESCE(SUM(sb.points_earned), 0) as total_points,
                COUNT(sb.scan_id) as total_items,
                COALESCE(SUM(sb.environmental_impact), 0) as co2_saved,
                COALESCE(AVG(sb.points_earned), 0) as avg_points
            FROM users u
            LEFT JOIN user_profiles up ON u.user_id = up.user_id
            LEFT JOIN scanned_barcodes sb ON u.user_id = sb.user_id $dateCondition
            WHERE up.university IS NOT NULL $universityCondition
            GROUP BY up.university, up.university_code
            ORDER BY total_points DESC
            LIMIT 20
        ";
    }

    $stmt = $pdo->prepare($sql);
    
    if ($university !== 'all') {
        $stmt->bindParam(':university', $university);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $results,
        'period' => $period,
        'university' => $university,
        'type' => $type
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
