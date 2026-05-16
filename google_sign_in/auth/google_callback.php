<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/google_config.php';
require_once __DIR__ . '/../../config/app.php';

if (!isset($_GET['code'])) {
    die("No auth code provided. <a href='login.php'>Try again</a>");
}

try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        throw new Exception('Auth Error: ' . json_encode($token));
    }

    $client->setAccessToken($token['access_token']);
    $google_oauth = new Google\Service\Oauth2($client);
    $google_user = $google_oauth->userinfo->get();

    $google_id  = $google_user->id;
    $email      = $google_user->email;
    $full_name  = $google_user->name ?: 'مستخدم';

    // First try to find by google_id, fallback to email stored in phone column
    $stmt = $conn->prepare('SELECT id, role, survey_completed FROM users WHERE phone = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $user_id = (int)$user['id'];
        $role = $user['role'];
        $survey_completed = (int)$user['survey_completed'];
    } else {
        $role = 'diabetic';
        $random_password = bin2hex(random_bytes(12));
        $hashed_password = password_hash($random_password, PASSWORD_DEFAULT);

        // Store Google email in the 'phone' column (used as unique identifier)
        $insert = $conn->prepare('INSERT INTO users (phone, password, role, survey_completed, created_at) VALUES (?, ?, ?, 0, NOW())');
        $insert->bind_param('sss', $email, $hashed_password, $role);
        if (!$insert->execute()) {
            throw new Exception('Registration failed: ' . $insert->error);
        }
        $user_id = (int)$insert->insert_id;
        $survey_completed = 0;
        $insert->close();
    }

    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $user_id;
    $_SESSION['role'] = $role;
    $_SESSION['full_name'] = $google_user->name ?: 'مستخدم';

    $_SESSION['user_path'] = 'diabetic';
    unset($_SESSION['guest_mode']);

    require_once __DIR__ . '/../../includes/profile_sync.php';
    $syncedFromTemp = dmproject_sync_temp_patient_data($conn, $user_id);
    if ($syncedFromTemp) {
        $survey_completed = 1;
    }

    $profileStmt = $conn->prepare('SELECT full_name FROM user_profile WHERE user_id = ? LIMIT 1');
    if ($profileStmt) {
        $profileStmt->bind_param('i', $user_id);
        $profileStmt->execute();
        $profileRow = $profileStmt->get_result()->fetch_assoc();
        if (!empty($profileRow['full_name'])) {
            $_SESSION['full_name'] = $profileRow['full_name'];
        }
        $profileStmt->close();
    }

    $stmt->close();

    // Enforce 'diabetic' path for all logged in users
    if ($_SESSION['role'] !== 'diabetic') {
        $_SESSION['role'] = 'diabetic';
        $update_role = $conn->prepare("UPDATE users SET role = 'diabetic' WHERE id = ?");
        if ($update_role) {
            $update_role->bind_param('i', $user_id);
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

} catch (Exception $e) {
    echo 'Error: ' . htmlspecialchars($e->getMessage());
}
?>
