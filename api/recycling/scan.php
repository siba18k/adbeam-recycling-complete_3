<?php
header("Content-Type: application/json");
require_once '../../includes/auth_check.php';
require_once '../../includes/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

try {
    // Validate input
    if (empty($input['barcode'])) {
        throw new InvalidArgumentException("Barcode cannot be empty");
    }

    $materialName = $input['material'] ?? '';
    $locationId = $input['location_id'] ?? null;
    $weight = $input['weight'] ?? null;
    $quantity = $input['quantity'] ?? 1;

    // Get material type
    $stmt = $pdo->prepare("SELECT material_id, base_points, co2_impact_per_item FROM material_types WHERE name = ? AND is_active = TRUE");
    $stmt->execute([$materialName]);
    $material = $stmt->fetch();

    if (!$material) {
        throw new InvalidArgumentException("Invalid material type");
    }

    // Check for duplicate scan (within last hour)
    $stmt = $pdo->prepare("
        SELECT scan_id FROM scanned_items 
        WHERE barcode = ? AND user_id = ? AND scan_timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");
    $stmt->execute([$input['barcode'], $_SESSION['user_id']]);
    
    if ($stmt->fetch()) {
        throw new Exception("This item was already scanned recently");
    }

    $pdo->beginTransaction();

    // Calculate points and environmental impact
    $pointsAwarded = $material['base_points'] * $quantity;
    $co2Saved = $material['co2_impact_per_item'] * $quantity;

    // Insert scan record
    $stmt = $pdo->prepare("
        INSERT INTO scanned_items 
        (user_id, location_id, material_id, barcode, weight_grams, quantity, 
         points_awarded, co2_saved, verification_method, scan_timestamp, ip_address, device_info) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'barcode', NOW(), ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'], 
        $locationId, 
        $material['material_id'], 
        $input['barcode'], 
        $weight, 
        $quantity, 
        $pointsAwarded, 
        $co2Saved,
        $_SERVER['REMOTE_ADDR'] ?? '',
        json_encode(['user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''])
    ]);

    // Update user points and totals
    $stmt = $pdo->prepare("
        UPDATE users 
        SET points_balance = points_balance + ?, 
            total_points_earned = total_points_earned + ?
        WHERE user_id = ?
    ");
    $stmt->execute([$pointsAwarded, $pointsAwarded, $_SESSION['user_id']]);

    // Check for level up
    $stmt = $pdo->prepare("
        SELECT u.total_points_earned, u.current_level, ul.level_number, ul.name as level_name
        FROM users u
        LEFT JOIN user_levels ul ON u.total_points_earned >= ul.points_required
        WHERE u.user_id = ?
        ORDER BY ul.points_required DESC
        LIMIT 1
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $levelInfo = $stmt->fetch();

    $leveledUp = false;
    if ($levelInfo && $levelInfo['level_number'] > $levelInfo['current_level']) {
        $stmt = $pdo->prepare("UPDATE users SET current_level = ? WHERE user_id = ?");
        $stmt->execute([$levelInfo['level_number'], $_SESSION['user_id']]);
        $leveledUp = true;

        // Create level up notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, data, created_at) 
            VALUES (?, 'achievement', 'Level Up!', ?, ?, NOW())
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Congratulations! You've reached level {$levelInfo['level_number']}: {$levelInfo['level_name']}",
            json_encode(['level' => $levelInfo['level_number'], 'level_name' => $levelInfo['level_name']])
        ]);
    }

    // Check for achievements
    $achievements = [];
    
    // First scan achievement
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM scanned_items WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $totalScans = $stmt->fetchColumn();
    
    if ($totalScans == 1) {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO user_achievements (user_id, achievement_id, earned_at)
            SELECT ?, achievement_id, NOW() FROM achievements WHERE name = 'First Steps'
        ");
        $stmt->execute([$_SESSION['user_id']]);
        if ($pdo->lastInsertId()) {
            $achievements[] = 'First Steps';
        }
    }

    $pdo->commit();

    // Get updated user stats
    $stmt = $pdo->prepare("
        SELECT points_balance, total_points_earned, current_level,
               (SELECT COUNT(*) FROM scanned_items WHERE user_id = ?) as total_scans,
               (SELECT COALESCE(SUM(co2_saved), 0) FROM scanned_items WHERE user_id = ?) as total_co2_saved
        FROM users WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
    $userStats = $stmt->fetch();

    $result = [
        'success' => true,
        'points_awarded' => $pointsAwarded,
        'co2_saved' => $co2Saved,
        'material' => $materialName,
        'quantity' => $quantity,
        'total_points' => $userStats['points_balance'],
        'total_points_earned' => $userStats['total_points_earned'],
        'current_level' => $userStats['current_level'],
        'total_scans' => $userStats['total_scans'],
        'total_co2_saved' => $userStats['total_co2_saved']
    ];

    if ($leveledUp) {
        $result['level_up'] = [
            'new_level' => $levelInfo['level_number'],
            'level_name' => $levelInfo['level_name']
        ];
    }

    if (!empty($achievements)) {
        $result['achievements'] = $achievements;
    }

    echo json_encode($result);

} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => 'invalid_input'
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Scan processing failed: ' . $e->getMessage(),
        'error_code' => 'server_error'
    ]);
}
?>
