<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'غير مسجل دخول']);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';

$user_id = (int)$_SESSION['user_id'];
$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['error'] = 'Invalid Request Method';
    echo json_encode($response);
    $conn->close();
    exit;
}

$name = trim($_POST['full_name'] ?? '');
$age = (int)($_POST['age'] ?? 0);
$weight = (float)($_POST['weight'] ?? 0);
$height = (float)($_POST['height'] ?? 0);
$gender = $_POST['gender'] ?? '';
$therapy_type = $_POST['therapy_type'] ?? 'none';
$diagnosis_date = $_POST['date_of_diagnosis'] ?? null;
$drugs = $_POST['drug'] ?? [];
$doses = $_POST['dose'] ?? [];
$phone = trim($_POST['phone'] ?? '');

if (empty($name) || $age <= 0 || $weight <= 0 || $height <= 0 || empty($gender) || empty($diagnosis_date)) {
    echo json_encode(['success' => false, 'error' => 'الرجاء ملء جميع البيانات الأساسية بشكل صحيح']);
    $conn->close();
    exit;
}

$drugDoseMap = [];
if (is_array($drugs) && is_array($doses)) {
    for ($i = 0; $i < count($drugs); $i++) {
        if (!empty($drugs[$i])) {
            $drugDoseMap[] = [
                'drug' => strip_tags($drugs[$i]),
                'dose' => strip_tags($doses[$i] ?? '')
            ];
        }
    }
}
$json_drugs = json_encode($drugDoseMap, JSON_UNESCAPED_UNICODE);

$check = $conn->prepare('SELECT id FROM patient_data WHERE user_id = ?');
$check->bind_param('i', $user_id);
$check->execute();
$exists = $check->get_result()->num_rows > 0;
$check->close();

if ($exists) {
    $stmt = $conn->prepare('UPDATE patient_data SET name=?, age=?, weight=?, height=?, gender=?, therapy_type=?, date_of_diagnosis=?, drug_dose_map=?, phone=?, updated_at=NOW() WHERE user_id=?');
    $stmt->bind_param('siddsssssi', $name, $age, $weight, $height, $gender, $therapy_type, $diagnosis_date, $json_drugs, $phone, $user_id);
} else {
    $stmt = $conn->prepare('INSERT INTO patient_data (name, age, weight, height, gender, therapy_type, date_of_diagnosis, drug_dose_map, phone, user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->bind_param('siddsssssi', $name, $age, $weight, $height, $gender, $therapy_type, $diagnosis_date, $json_drugs, $phone, $user_id);
}

if (!$stmt->execute()) {
    $response['error'] = 'فشل التحديث: ' . $conn->error;
    $stmt->close();
    $conn->close();
    echo json_encode($response);
    exit;
}
$stmt->close();

$diabetes_type = 'none';
$dtype_stmt = $conn->prepare('SELECT diabetes_type FROM user_profile WHERE user_id = ? LIMIT 1');
if ($dtype_stmt) {
    $dtype_stmt->bind_param('i', $user_id);
    $dtype_stmt->execute();
    $dtype_row = $dtype_stmt->get_result()->fetch_assoc();
    if (!empty($dtype_row['diabetes_type'])) {
        $diabetes_type = $dtype_row['diabetes_type'];
    }
    $dtype_stmt->close();
}

$profile_check = $conn->prepare('SELECT id FROM user_profile WHERE user_id = ?');
if (!$profile_check) {
    $response['error'] = 'فشل فحص ملف المستخدم: ' . $conn->error;
    $conn->close();
    echo json_encode($response);
    exit;
}
$profile_check->bind_param('i', $user_id);
$profile_check->execute();
$profile_exists = $profile_check->get_result()->num_rows > 0;
$profile_check->close();

if ($profile_exists) {
    // user_profile.gender is ENUM('ذكر','أنثى') — translate English values
    $genderForProfile = (strtolower($gender) === 'female') ? 'أنثى'
                      : ((strtolower($gender) === 'male')  ? 'ذكر' : $gender);
    $profile_stmt = $conn->prepare('UPDATE user_profile SET full_name=?, age=?, gender=?, weight=?, height=?, diagnosis_date=?, treatment_type=?, diabetes_type=?, phone=?, updated_at=NOW() WHERE user_id=?');
    if ($profile_stmt) {
        $profile_stmt->bind_param('sisddssssi', $name, $age, $genderForProfile, $weight, $height, $diagnosis_date, $therapy_type, $diabetes_type, $phone, $user_id);
    }
} else {
    $genderForProfile = (strtolower($gender) === 'female') ? 'أنثى'
                      : ((strtolower($gender) === 'male')  ? 'ذكر' : $gender);
    $profile_stmt = $conn->prepare('INSERT INTO user_profile (user_id, full_name, age, gender, weight, height, diagnosis_date, treatment_type, diabetes_type, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    if ($profile_stmt) {
        $profile_stmt->bind_param('isisddssss', $user_id, $name, $age, $genderForProfile, $weight, $height, $diagnosis_date, $therapy_type, $diabetes_type, $phone);
    }
}

if (empty($profile_stmt) || !$profile_stmt->execute()) {
    $response['error'] = 'فشل مزامنة ملف المستخدم: ' . $conn->error;
    if (!empty($profile_stmt)) {
        $profile_stmt->close();
    }
    $conn->close();
    echo json_encode($response);
    exit;
}
$profile_stmt->close();

$flag_stmt = $conn->prepare('UPDATE users SET survey_completed=1, profile_completed_at=NOW() WHERE id=?');
$flag_stmt->bind_param('i', $user_id);
if (!$flag_stmt->execute()) {
    $response['error'] = 'فشل تحديث حالة اكتمال الملف: ' . $conn->error;
    $flag_stmt->close();
    $conn->close();
    echo json_encode($response);
    exit;
}
$flag_stmt->close();

$_SESSION['full_name'] = $name;
unset($_SESSION['temp_patient_data']);
$response['success'] = true;

$conn->close();
echo json_encode($response);
?>
