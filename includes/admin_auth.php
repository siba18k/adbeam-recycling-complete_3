<?php

class AdminAuth {

    public static function checkAdmin() {
        // Check if session is started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Authentication required']);
            exit;
        }
        
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Invalid session']);
            exit;
        }
        
        global $pdo;
        
        try {
            // Check if user exists and is active
            $stmt = $pdo->prepare("SELECT user_id, account_status FROM users WHERE user_id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'User not found']);
                exit;
            }
            
            if ($user['account_status'] !== 'active') {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Account not active']);
                exit;
            }
            
            // Check if user is admin - handle both old and new table structures
            $isAdmin = false;
            
            // Try new admin_users table first
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE user_id = ? AND is_active = 1");
                $stmt->execute([$_SESSION['user_id']]);
                $isAdmin = $stmt->fetchColumn() > 0;
            } catch (PDOException $e) {
                // Fallback to checking if admin column exists in users table
                try {
                    $stmt = $pdo->prepare("SELECT is_admin FROM users WHERE user_id = ?");
                    $stmt->execute([$_SESSION['user_id']]);
                    $result = $stmt->fetch();
                    $isAdmin = $result && $result['is_admin'];
                } catch (PDOException $e2) {
                    error_log("Admin check failed: " . $e2->getMessage());
                }
            }
            
            if (!$isAdmin) {
                // Log unauthorized access attempt
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO security_logs (user_id, event_type, ip_address, user_agent, created_at) 
                        VALUES (?, 'unauthorized_admin_access', ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $_SESSION['user_id'], 
                        $_SERVER['REMOTE_ADDR'] ?? '', 
                        $_SERVER['HTTP_USER_AGENT'] ?? ''
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to log security event: " . $e->getMessage());
                }
                
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Admin auth check failed: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Authentication check failed']);
            exit;
        }
    }
}
