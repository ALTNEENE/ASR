<?php
// includes/auth_restore.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../google_sign_in/config/db.php';

// Only attempt restore if not logged in and cookie exists
if (!isset($_SESSION['user_id']) && isset($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
    
    // Find user with this token
    $stmt = $conn->prepare("SELECT id, role, full_name, email FROM users WHERE remember_token = ?");
    if ($stmt) {
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Restore Session
            $_SESSION['logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'] ?? 'مستخدم';
            
            // Optional: Rotate token for extra security? (Skipping for simplicity now)
        } else {
            // Invalid token (maybe expired or rotated elsewhere), clear cookie
            setcookie("auth_token", "", time() - 3600, "/");
        }
        $stmt->close();
    }
}
?>
