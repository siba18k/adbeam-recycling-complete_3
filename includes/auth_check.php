<?php
require_once 'auth.php';

function requireAuth() {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        // Store the requested URL for redirect after login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        
        // Redirect to login page
        header('Location: /login.html');
        exit();
    }
}
?>
