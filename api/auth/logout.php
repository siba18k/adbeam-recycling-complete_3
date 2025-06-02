<?php
session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Log logout if user was logged in
        if (isset($_SESSION['user_id'])) {
            require_once('../../includes/db_connect.php');
            
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, created_at) 
                    VALUES (?, 'logout', ?, ?, NOW())
                ");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    $_SERVER['REMOTE_ADDR'] ?? '', 
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Exception $e) {
                error_log("Failed to log logout: " . $e->getMessage());
            }
        }

        // Destroy session
        session_destroy();
        
        echo json_encode([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    } catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Logout failed'
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
