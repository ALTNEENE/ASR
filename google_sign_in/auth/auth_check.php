<?php
// auth_check.php - Check if user is logged in
session_start();
require_once __DIR__ . '/../../config/app.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Not logged in, redirect to login page
    app_redirect('google_sign_in/auth/login.php');
}

// User is logged in, continue
?>
