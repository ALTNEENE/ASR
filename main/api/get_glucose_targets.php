<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../google_sign_in/config/db.php';

// Determine age and type
$age = 30; // default
$diabetes_type = 'any';

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT age, diabetes_type FROM patient_data WHERE user_id = ? LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $age = (int)$row['age'];
        $diabetes_type = $row['diabetes_type'];
    }
    $stmt->close();
} elseif (isset($_SESSION['temp_patient_data'])) {
    // If they just filled the questionnaire but haven't logged in yet
    $age = (int)($_SESSION['temp_patient_data']['age'] ?? 30);
    $diabetes_type = $_SESSION['temp_patient_data']['diabetes_type'] ?? 'any';
} else {
    // Or via GET params
    if (isset($_GET['age'])) $age = (int)$_GET['age'];
    if (isset($_GET['type'])) $diabetes_type = $_GET['type'];
}

// Map the type
$mapped_type = 'any';
$valid_types = ['type1', 'type2', 'gestational', 'prediabetes', 'none', 'any'];
$dt_lower = strtolower($diabetes_type);

if (in_array($dt_lower, $valid_types)) {
    $mapped_type = $dt_lower;
} elseif ($dt_lower === 't1') {
     $mapped_type = 'type1';
} elseif ($dt_lower === 't2') {
     $mapped_type = 'type2';
} elseif ($dt_lower === 'gdm') {
     $mapped_type = 'gestational';
}

$query = "
    SELECT diabetes_type, age_min, age_max, fasting_min, fasting_max, post2h_max, hba1c_max 
    FROM glucose_standards 
    WHERE ? BETWEEN age_min AND age_max 
    AND (
        diabetes_type = ? 
        OR (diabetes_type = 'any' AND ? NOT IN ('type1', 'type2', 'gestational', 'none'))
    )
    ORDER BY 
        CASE WHEN diabetes_type = ? THEN 1 ELSE 2 END
    LIMIT 1
";

$stmt = $conn->prepare($query);
$stmt->bind_param("isss", $age, $mapped_type, $mapped_type, $mapped_type);
$stmt->execute();
$res = $stmt->get_result();

if ($target = $res->fetch_assoc()) {
    echo json_encode([
        'success' => true,
        'user_age' => $age,
        'user_type' => $diabetes_type,
        'targets' => [
            'fasting_min' => (int)$target['fasting_min'],
            'fasting_max' => (int)$target['fasting_max'],
            'post2h_max'  => (int)$target['post2h_max'],
            'hba1c_max'   => (float)$target['hba1c_max']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'لم يتم العثور على معايير مطابقة']);
}

$stmt->close();
$conn->close();
?>
