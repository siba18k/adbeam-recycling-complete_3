<?php
ob_start();
error_reporting(0); // Hide warnings from breaking JSON
ini_set('display_errors', 0);

session_start();
require_once('../../includes/db_connect.php');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email and password are required'
    ]);
    exit;
}

try {
    // Fetch user
    $stmt = $pdo->prepare("
        SELECT user_id, password_hash, account_status, failed_login_attempts, 
               account_locked_until, email_verified
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }

    if ($user['account_status'] !== 'active') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Account is not active.'
        ]);
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        $failedAttempts = $user['failed_login_attempts'] + 1;
        $lockUntil = ($failedAttempts >= 5) ? date('Y-m-d H:i:s', strtotime('+30 minutes')) : null;

        $stmt = $pdo->prepare("
            UPDATE users 
            SET failed_login_attempts = ?, last_failed_login = NOW(), account_locked_until = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$failedAttempts, $lockUntil, $user['user_id']]);

        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email or password'
        ]);
        exit;
    }

    // Reset login attempts
    $stmt = $pdo->prepare("
        UPDATE users 
        SET failed_login_attempts = 0, last_login = NOW(), login_count = login_count + 1,
            account_locked_until = NULL
        WHERE user_id = ?
    ");
    $stmt->execute([$user['user_id']]);

    // Check what email column exists
    $stmt = $pdo->prepare("DESCRIBE users");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $emailColumn = in_array('college_email', $columns) ? 'college_email' : 'email';

    // Fetch user details + admin check
    $stmt = $pdo->prepare("
        SELECT u.user_id, u.$emailColumn as email, u.points_balance, 
           COALESCE(u.total_points_earned, u.points_balance) as total_points_earned,
           p.first_name, p.last_name, p.display_name, p.student_id,
           CASE 
               WHEN EXISTS(SELECT 1 FROM admin_users au WHERE au.user_id = u.user_id AND au.is_active = 1) THEN 1
               ELSE 0
           END as is_admin
        FROM users u
        LEFT JOIN user_profiles p ON u.user_id = p.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userDetails) {
        throw new Exception("User details not found.");
    }

    // Log login success
    try {
        $stmt = $pdo->prepare("
            INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, created_at) 
            VALUES (?, 'login_success', ?, ?, NOW())
        ");
        $stmt->execute([
            $user['user_id'],
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Failed to log login event: " . $e->getMessage());
    }

    // Set session
    session_regenerate_id(true);
    // Safely set session variables
    $_SESSION['user_id'] = $userDetails['user_id'] ?? null;
    $_SESSION['user_email'] = $userDetails['email'] ?? null;
    $_SESSION['is_admin'] = isset($userDetails['is_admin']) ? (bool)$userDetails['is_admin'] : false;

    // Determine redirect URL based on admin status
    $isAdmin = isset($userDetails['is_admin']) ? (bool)$userDetails['is_admin'] : false;
    $redirectUrl = $isAdmin ? '/api/admin/admin_dashboard.php' : '/assets/dashboard.html';

    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'redirect_url' => $redirectUrl,
        'user' => [
            'id' => $userDetails['user_id'] ?? null,
            'email' => $userDetails['email'] ?? null,
            'name' => $userDetails['display_name'] ?: trim(($userDetails['first_name'] ?? '') . ' ' . ($userDetails['last_name'] ?? '')),
            'first_name' => $userDetails['first_name'] ?? '',
            'last_name' => $userDetails['last_name'] ?? '',
            'student_id' => $userDetails['student_id'] ?? null,
            'points' => $userDetails['points_balance'] ?? 0,
            'total_points' => $userDetails['total_points_earned'] ?? 0,
            'is_admin' => $isAdmin
        ]
    ]);
    exit;


} catch (Exception $e) {
    error_log("Login failed: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error. Please try again later.'
    ]);
}
ob_end_clean(); // Clean any accidental HTML output
