<?php
// google_sign_in/auth/logout.php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../config/app.php';

// Remove remember-me token from database if present.
if (!empty($_COOKIE['auth_token'])) {
    $token = $_COOKIE['auth_token'];
    $stmt = $conn->prepare('UPDATE users SET remember_token = NULL WHERE remember_token = ?');
    if ($stmt) {
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $stmt->close();
    }
}

// 1. Destroy Session
$_SESSION = array();
session_destroy();

// 2. Clear Cookies
setcookie("logged_in", "", time() - 3600, "/");
setcookie("user_id", "", time() - 3600, "/");
setcookie("user_email", "", time() - 3600, "/");
setcookie("auth_token", "", time() - 3600, "/", "", false, true);

// 3. Redirect to welcome page after the session is fully cleared.
app_redirect('welcome.php');
?>
