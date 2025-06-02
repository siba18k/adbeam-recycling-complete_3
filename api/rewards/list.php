<?php
header("Content-Type: application/json");
require_once '../../includes/db_connect.php';

try {
    $categoryId = $_GET['category_id'] ?? null;
    $institutionId = $_GET['institution_id'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    $sql = "
        SELECT 
            r.reward_id,
            r.name,
            r.description,
            r.long_description,
            r.points_cost,
            r.monetary_value,
            r.currency,
            r.inventory_total,
            r.inventory_available,
            r.image_url,
            r.terms_conditions,
            r.redemption_instructions,
            r.valid_from,
            r.valid_until,
            r.max_redemptions_per_user,
            r.is_featured,
            r.is_active,
            rc.name as category_name,
            rv.name as vendor_name,
            rv.logo_url as vendor_logo,
            CASE 
                WHEN r.inventory_available IS NULL THEN 'unlimited'
                WHEN r.inventory_available > 10 THEN 'high'
                WHEN r.inventory_available > 3 THEN 'medium'
                WHEN r.inventory_available > 0 THEN 'low'
                ELSE 'out_of_stock'
            END as inventory_status
    ";

    if ($userId) {
        $sql .= ",
            (SELECT COUNT(*) FROM user_rewards ur WHERE ur.user_id = ? AND ur.reward_id = r.reward_id) as user_redemption_count
        ";
    }

    $sql .= "
        FROM rewards r
        LEFT JOIN reward_categories rc ON r.category_id = rc.category_id
        LEFT JOIN reward_vendors rv ON r.vendor_id = rv.vendor_id
        WHERE r.is_active = TRUE
        AND (r.valid_until IS NULL OR r.valid_until > NOW())
        AND (r.inventory_available IS NULL OR r.inventory_available > 0)
    ";

    $params = [];
    if ($userId) {
        $params[] = $userId;
    }

    if ($categoryId) {
        $sql .= " AND r.category_id = ?";
        $params[] = $categoryId;
    }

    if ($institutionId) {
        $sql .= " AND (r.institution_id IS NULL OR r.institution_id = ?)";
        $params[] = $institutionId;
    }

    $sql .= " ORDER BY r.is_featured DESC, r.sort_order ASC, r.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rewards = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process rewards data
    foreach ($rewards as &$reward) {
        $reward['points_cost'] = (int)$reward['points_cost'];
        $reward['monetary_value'] = (float)$reward['monetary_value'];
        $reward['inventory_total'] = $reward['inventory_total'] ? (int)$reward['inventory_total'] : null;
        $reward['inventory_available'] = $reward['inventory_available'] ? (int)$reward['inventory_available'] : null;
        $reward['is_featured'] = (bool)$reward['is_featured'];
        $reward['is_active'] = (bool)$reward['is_active'];
        $reward['user_redemption_count'] = isset($reward['user_redemption_count']) ? (int)$reward['user_redemption_count'] : 0;
        
        // Check if user can redeem this reward
        $reward['can_redeem'] = true;
        if ($reward['max_redemptions_per_user'] && $reward['user_redemption_count'] >= $reward['max_redemptions_per_user']) {
            $reward['can_redeem'] = false;
        }
        if ($reward['inventory_status'] === 'out_of_stock') {
            $reward['can_redeem'] = false;
        }
    }

    echo json_encode(['success' => true, 'data' => $rewards]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to load rewards: ' . $e->getMessage()
    ]);
}
?>
