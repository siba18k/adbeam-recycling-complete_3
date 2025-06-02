<?php
header("Content-Type: application/json");
require_once '../../includes/auth.php';
require_once '../../includes/db_connect.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    $response = [
        'activity' => [
            'labels' => [],
            'data' => []
        ],
        'materials' => [
            'labels' => ['Plastic', 'Glass', 'Aluminum'],
            'data' => [0, 0, 0]
        ]
    ];
    
    // Last 7 days activity
    $stmt = $pdo->prepare("
        SELECT 
            DATE(scan_time) as day,
            SUM(points_awarded) as points
        FROM scanned_barcodes
        WHERE user_id = ? AND scan_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(scan_time)
        ORDER BY day ASC
    ");
    $stmt->execute([$userId]);
    
    $activityData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fill in missing days
    $dates = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $dates[$date] = 0;
    }
    
    foreach ($activityData as $row) {
        $dates[$row['day']] = (int)$row['points'];
    }
    
    $response['activity']['labels'] = array_keys($dates);
    $response['activity']['data'] = array_values($dates);
    
    // Material distribution
    $stmt = $pdo->prepare("
        SELECT 
            material_type,
            COUNT(*) as count
        FROM scanned_barcodes
        WHERE user_id = ?
        GROUP BY material_type
    ");
    $stmt->execute([$userId]);
    
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($materials as $material) {
        switch ($material['material_type']) {
            case 'plastic':
                $response['materials']['data'][0] = $material['count'];
                break;
            case 'glass':
                $response['materials']['data'][1] = $material['count'];
                break;
            case 'aluminum':
                $response['materials']['data'][2] = $material['count'];
                break;
        }
    }
    
    echo json_encode(['success' => true, ...$response]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
