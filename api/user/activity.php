<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../includes/db_connect.php';
session_start();

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // Get recent activities from all tables
    $stmt = $pdo->prepare("
        SELECT 
            activity_id as id,
            barcode,
            material_type,
            points_awarded,
            created_at,
            'recycling_activity' as activity_type,
            status
        FROM recycling_activities
        WHERE user_id = ? AND status = 'verified'
        
        UNION ALL
        
        SELECT 
            scan_id as id,
            barcode,
            material_type,
            points_awarded,
            scan_time as created_at,
            'scan' as activity_type,
            status
        FROM scanned_items
        WHERE user_id = ? AND status = 'verified'
        
        UNION ALL
        
        SELECT 
            scan_id as id,
            barcode,
            material_type,
            points_awarded,
            scan_time as created_at,
            'legacy_scan' as activity_type,
            'verified' as status
        FROM scanned_barcodes
        WHERE user_id = ?
        
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId, $userId, $userId]);
    $activities = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'activity' => $activities
    ]);
    
} catch (Exception $e) {
    error_log("User activity error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to load user activity: ' . $e->getMessage()]);
}
?>
