<?php
// includes/auth_guard.php
// STRICT ACCESS CONTROL

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Try to restore session from cookie (Remember Me)
require_once __DIR__ . '/auth_restore.php';

$base_level = isset($base_level) ? $base_level : 2; // Default to 2 levels deep if not set
$prefix = str_repeat('../', $base_level);

// 1. Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Not logged in -> Redirect to Welcome
    header("Location: " . $prefix . "welcome.php");
    exit;
}

// 2. Check Role
$role = $_SESSION['role'] ?? 'awareness';

// If accessing Patient/Diabetic Area
// (We assume this guard is included in those pages)
if ($role !== 'diabetic' && $role !== 'admin') {
    // Unauthorized Role -> Redirect to Awareness
    header("Location: " . $prefix . "Awareness/index.php"); // or awareness.php
    exit;
}

// If passed, allow access
?>
