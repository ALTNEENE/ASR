<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'غير مسجل دخول']);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';

$user_id = $_SESSION['user_id'];
$old_password = $_POST['old_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';

// Strong Password Validation
$passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/';

if (!preg_match($passwordRegex, $new_password)) {
    echo json_encode(['success' => false, 'error' => 'كلمة المرور ضعيفة. يجب أن تحتوي على 8 أحرف على الأقل، حرف كبير، حرف صغير، رقم، ورمز خاص.']);
    exit;
}


// 1. Fetch current user data
$stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    echo json_encode(['success' => false, 'error' => 'مستخدم غير موجود']);
    exit;
}

// 2. Verify Old Password (ONLY if provided)
if (!empty($old_password)) {
    if (!password_verify($old_password, $user['password'])) {
        echo json_encode(['success' => false, 'error' => 'كلمة المرور الحالية غير صحيحة']);
        exit;
    }
} else {
    // If old password is empty, we allow update implicitly (Use case: Google User setting password)
    // In a stricter system, checking a 'provider' flag would be better.
}

// 3. Update Password
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
$update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
$update->bind_param("si", $new_hash, $user_id);

if ($update->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'خطأ في قاعدة البيانات']);
}

$conn->close();
?>
