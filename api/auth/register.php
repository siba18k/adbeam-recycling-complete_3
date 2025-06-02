<?php
session_start();
require_once('../../includes/db_connect.php');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $firstName = trim($input['firstName'] ?? '');
    $lastName = trim($input['lastName'] ?? '');
    $studentId = trim($input['studentId'] ?? '');

    // Validation
    if (empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'All required fields must be filled'
        ]);
        exit;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Password must be at least 6 characters long'
        ]);
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid email format'
        ]);
        exit;
    }

    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'message' => 'Email already registered'
            ]);
            exit;
        }

        // Start transaction
        $pdo->beginTransaction();

        // Create user
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password_hash, account_status, email_verified, created_at) 
            VALUES (?, ?, 'active', 1, NOW())
        ");
        $stmt->execute([$email, $passwordHash]);
        $userId = $pdo->lastInsertId();

        // Create user profile
        $displayName = $firstName . ' ' . $lastName;
        $stmt = $pdo->prepare("
            INSERT INTO user_profiles (user_id, first_name, last_name, display_name, student_id, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$userId, $firstName, $lastName, $displayName, $studentId ?: null]);

        // Commit transaction
        $pdo->commit();

        // Log successful registration
        try {
            $stmt = $pdo->prepare("
                INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, created_at) 
                VALUES (?, 'registration', ?, ?, NOW())
            ");
            $stmt->execute([
                $userId, 
                $_SERVER['REMOTE_ADDR'] ?? '', 
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Failed to log registration: " . $e->getMessage());
        }

        // Auto-login the user
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $email;
        $_SESSION['logged_in'] = true;
        $_SESSION['is_admin'] = false;

        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'redirect' => '/assets/dashboard.html',
            'user' => [
                'id' => $userId,
                'email' => $email,
                'name' => $displayName,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'student_id' => $studentId,
                'points' => 0,
                'total_points' => 0,
                'is_admin' => false
            ]
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Registration error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Registration failed. Please try again.'
        ]);
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
?>
