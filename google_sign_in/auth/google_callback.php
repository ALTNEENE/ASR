<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/google_config.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_GET['code'])) {
    die("No auth code provided. <a href='login.php'>Try again</a>");
}

try {
    $state = (string)($_GET['state'] ?? '');
    $expectedState = (string)($_SESSION['google_oauth_state'] ?? '');
    unset($_SESSION['google_oauth_state']);

    if ($expectedState !== '' && !hash_equals($expectedState, $state)) {
        throw new RuntimeException('Invalid Google OAuth state.');
    }

    $token = google_fetch_token((string)$_GET['code']);
    $accessToken = (string)($token['access_token'] ?? '');
    if ($accessToken === '') {
        throw new RuntimeException('Google did not return an access token.');
    }

    $googleUser = google_fetch_userinfo($accessToken);

    $googleId = (string)($googleUser['sub'] ?? '');
    $email = (string)($googleUser['email'] ?? '');
    $fullName = trim((string)($googleUser['name'] ?? ''));
    if ($fullName === '') {
        $fullName = 'مستخدم';
    }

    if ($email === '') {
        throw new RuntimeException('Google account email is unavailable.');
    }

    $stmt = $conn->prepare('SELECT id, role, survey_completed FROM users WHERE google_id = ? OR email = ? OR phone = ? LIMIT 1');
    if (!$stmt) {
        throw new RuntimeException('User lookup failed: ' . $conn->error);
    }

    $stmt->bind_param('sss', $googleId, $email, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userId = (int)$user['id'];
        $role = (string)$user['role'];
        $surveyCompleted = (int)$user['survey_completed'];
        $stmt->close();

        $update = $conn->prepare('UPDATE users SET google_id = NULLIF(?, \'\'), email = ?, full_name = ? WHERE id = ?');
        if ($update) {
            $update->bind_param('sssi', $googleId, $email, $fullName, $userId);
            $update->execute();
            $update->close();
        }
    } else {
        $stmt->close();
        $role = 'diabetic';
        $randomPassword = bin2hex(random_bytes(12));
        $hashedPassword = password_hash($randomPassword, PASSWORD_DEFAULT);

        $insert = $conn->prepare('INSERT INTO users (google_id, email, phone, password, full_name, role, survey_completed, created_at) VALUES (NULLIF(?, \'\'), ?, ?, ?, ?, ?, 0, NOW())');
        if (!$insert) {
            throw new RuntimeException('Registration failed: ' . $conn->error);
        }

        $insert->bind_param('ssssss', $googleId, $email, $email, $hashedPassword, $fullName, $role);
        if (!$insert->execute()) {
            throw new RuntimeException('Registration failed: ' . $insert->error);
        }

        $userId = (int)$insert->insert_id;
        $surveyCompleted = 0;
        $insert->close();
    }

    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
    $_SESSION['full_name'] = $fullName;
    $_SESSION['user_path'] = 'diabetic';
    unset($_SESSION['guest_mode']);

    require_once __DIR__ . '/../../includes/profile_sync.php';
    $syncedFromTemp = dmproject_sync_temp_patient_data($conn, $userId);
    if ($syncedFromTemp) {
        $surveyCompleted = 1;
    }

    $profileStmt = $conn->prepare('SELECT full_name FROM user_profile WHERE user_id = ? LIMIT 1');
    if ($profileStmt) {
        $profileStmt->bind_param('i', $userId);
        $profileStmt->execute();
        $profileRow = $profileStmt->get_result()->fetch_assoc();
        if (!empty($profileRow['full_name'])) {
            $_SESSION['full_name'] = $profileRow['full_name'];
        }
        $profileStmt->close();
    }

    if ($_SESSION['role'] !== 'diabetic') {
        $_SESSION['role'] = 'diabetic';
        $updateRole = $conn->prepare("UPDATE users SET role = 'diabetic' WHERE id = ?");
        if ($updateRole) {
            $updateRole->bind_param('i', $userId);
            $updateRole->execute();
            $updateRole->close();
        }
    }

    app_redirect($surveyCompleted === 1 ? 'main/dashboard.php' : 'questionnaire/questionnaire.php');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}
