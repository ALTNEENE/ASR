<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../../config/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$phone = trim($_POST['phone'] ?? '');
$password = $_POST['password'] ?? '';
$remember_me = isset($_POST['remember_me']);

$stmt = $conn->prepare('SELECT id, phone, password, role, survey_completed FROM users WHERE phone = ?');
if (!$stmt) {
    die('خطأ في قاعدة البيانات: ' . $conn->error);
}
$stmt->bind_param('s', $phone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<script>alert('رقم الهاتف غير مسجل'); window.history.back();</script>";
    $stmt->close();
    exit;
}

$user = $result->fetch_assoc();
if (!password_verify($password, $user['password'])) {
    echo "<script>alert('كلمة المرور غير صحيحة'); window.history.back();</script>";
    $stmt->close();
    exit;
}

$_SESSION['logged_in'] = true;
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['role'] = $user['role'];
$_SESSION['full_name'] = 'مستخدم';
$_SESSION['user_path'] = 'diabetic';
unset($_SESSION['guest_mode']);
$survey_completed = (int)$user['survey_completed'];

require_once __DIR__ . '/../../includes/profile_sync.php';

// If they just finished the survey as a guest and then logged into an existing account, sync their new data.
$syncedFromTemp = dmproject_sync_temp_patient_data($conn, (int)$_SESSION['user_id']);
if ($syncedFromTemp) {
    $survey_completed = 1;
}

// Fetch overriding full_name if available
$name_stmt = $conn->prepare('SELECT full_name FROM user_profile WHERE user_id = ? LIMIT 1');
if ($name_stmt) {
    $name_stmt->bind_param('i', $_SESSION['user_id']);
    $name_stmt->execute();
    $name_row = $name_stmt->get_result()->fetch_assoc();
    if (!empty($name_row['full_name'])) {
        $_SESSION['full_name'] = $name_row['full_name'];
    }
    $name_stmt->close();
}

// Remember me
if ($remember_me) {
    $token = bin2hex(random_bytes(32));
    $update_stmt = $conn->prepare('UPDATE users SET remember_token = ? WHERE id = ?');
    if ($update_stmt) {
        $update_stmt->bind_param('si', $token, $_SESSION['user_id']);
        $update_stmt->execute();
        $update_stmt->close();
        setcookie('auth_token', $token, time() + (86400 * 30), '/', '', false, true);
    }
}

$stmt->close();

// Enforce 'diabetic' path for all logged in users
if ($_SESSION['role'] !== 'diabetic') {
    $_SESSION['role'] = 'diabetic';
    $update_role = $conn->prepare("UPDATE users SET role = 'diabetic' WHERE id = ?");
    if ($update_role) {
        $update_role->bind_param('i', $_SESSION['user_id']);
        $update_role->execute();
        $update_role->close();
    }
}

// Smart Routing
if ($survey_completed === 1) {
    // Returning completed user
    app_redirect('main/dashboard.php');
} else {
    // Brand new user who hasn't answered the survey yet
    app_redirect('questionnaire/questionnaire.php');
}
?>
