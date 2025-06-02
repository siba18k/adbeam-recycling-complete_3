<?php
require_once 'db_connect.php';

class Auth {
    public static function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function login($email, $password) {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT user_id, college_email, password_hash FROM users WHERE college_email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['user_email'] = $user['college_email'];
            $_SESSION['logged_in'] = true;
            
            // Update last login
            $pdo->prepare("UPDATE users SET last_login = NOW(), login_count = login_count + 1 WHERE user_id = ?")
               ->execute([$user['user_id']]);
            
            return true;
        }
        return false;
    }

    public static function register($email, $password, $fullName = null, $university = null, $universityCode = null, $studentNumber = null) {
        global $pdo;
        
        if (strlen($password) < 8) {
            throw new Exception("Password must be at least 8 characters");
        }

        $pdo->beginTransaction();
        try {
            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (college_email, password_hash) VALUES (?, ?)");
            $stmt->execute([$email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = $pdo->lastInsertId();

            // Create profile with additional fields
            $stmt = $pdo->prepare("INSERT INTO user_profiles (user_id, full_name, university, university_code, student_number) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $fullName, $university, $universityCode, $studentNumber]);

            $pdo->commit();
            return $userId;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->errorInfo[1] == 1062) { // Duplicate entry
                throw new Exception("Email already registered");
            }
            throw new Exception("Registration failed: " . $e->getMessage());
        }
    }

    public static function logout() {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
}
?>
