<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/app.php';

$welcome_url = app_path('welcome.php');
$awareness_url = app_path('Awareness/index.php');

if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    header("Location: " . $welcome_url);
    exit;
}

if (empty($_SESSION['user_path']) || $_SESSION['user_path'] !== 'diabetic') {
    header("Location: " . $welcome_url);
    exit;
}

if (($_SESSION['role'] ?? '') !== 'diabetic') {
    header("Location: " . $awareness_url);
    exit;
}
?>
