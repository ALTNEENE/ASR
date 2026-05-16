<?php
/**
 * GET /main/api/get_health_tips.php
 * Fetches targeted health tips for the logged-in user based on age, gender, and diabetes_type.
 * Output: { ok, tips: [ {tip_title_ar, tip_content_ar, ...}, ... ] }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "غير مسجل دخول"], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';

$userId = $_SESSION['user_id'];

// Check if patient_data exists for this user to get age, gender, diabetes_type
$checkStmt = $conn->prepare("SELECT age, gender, diabetes_type FROM patient_data WHERE user_id = ?");
if (!$checkStmt) {
    echo json_encode(["ok" => false, "error" => "Database error: " . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$checkStmt->bind_param("i", $userId);
$checkStmt->execute();
$pdResult = $checkStmt->get_result();

if ($pdResult->num_rows === 0) {
    // No profile data yet, maybe return general tips only?
    // Let's return general tips where age=0-150, gender=any, diabetes_target=any
    $sql = "SELECT id, title_ar, body_ar, priority 
            FROM health_tips 
            WHERE is_active = 1 
              AND gender_target = 'any' 
              AND diabetes_target = 'any' 
            ORDER BY priority DESC";
            
    $result = $conn->query($sql);
    $tips = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tips[] = $row;
        }
    }
    
    echo json_encode(["ok" => true, "tips" => $tips], JSON_UNESCAPED_UNICODE);
    $checkStmt->close();
    $conn->close();
    exit;
}

$patientData = $pdResult->fetch_assoc();
$checkStmt->close();

// We have patient data, run the targeted query
// Using mysqli instead of PDO as we have $conn as mysqli object in db.php
$sql = "
SELECT t.id, t.title_ar, t.body_ar, t.priority
FROM health_tips t
JOIN patient_data pd ON pd.user_id = ?
WHERE t.is_active = 1
  AND pd.age BETWEEN t.min_age AND t.max_age
  AND (t.gender_target = 'any' OR CAST(t.gender_target AS CHAR) = CAST(pd.gender AS CHAR))
  AND (t.diabetes_target = 'any' OR CAST(t.diabetes_target AS CHAR) = CAST(pd.diabetes_type AS CHAR))
ORDER BY t.priority DESC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["ok" => false, "error" => "Database prepare error: " . $conn->error], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$tips = [];
while ($row = $result->fetch_assoc()) {
    $tips[] = $row;
}
$stmt->close();

echo json_encode([
    "ok" => true,
    "tips" => $tips
], JSON_UNESCAPED_UNICODE);

$conn->close();
?>
