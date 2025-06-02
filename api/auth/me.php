<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        echo json_encode([
            'authenticated' => false,
            'message' => 'Not logged in'
        ]);
        exit;
    }

    require_once('../../includes/db_connect.php');

    // Get user details
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.email, u.points_balance, u.total_points_earned,
               p.first_name, p.last_name, p.display_name, p.student_id,
               (SELECT COUNT(*) FROM admin_users au WHERE au.user_id = u.user_id AND au.is_active = 1) > 0 as is_admin
        FROM users u
        LEFT JOIN user_profiles p ON u.user_id = p.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        echo json_encode([
            'authenticated' => false,
            'message' => 'User not found'
        ]);
        exit;
    }

    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $user['user_id'],
            'email' => $user['email'],
            'name' => $user['display_name'] ?: ($user['first_name'] . ' ' . $user['last_name']),
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'student_id' => $user['student_id'],
            'points' => $user['points_balance'],
            'total_points' => $user['total_points_earned'],
            'is_admin' => (bool)$user['is_admin']
        ]
    ]);

} catch (Exception $e) {
    error_log("Me endpoint error: " . $e->getMessage());
    echo json_encode([
        'authenticated' => false,
        'message' => 'Server error'
    ]);
}
?>
