<?php
/**
 * GET /main/api/get_user_medications.php
 * Returns the current user's saved medications (last 10)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "غير مسجل دخول"], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';

$user_id = (int) $_SESSION['user_id'];

// Check if table exists first
$tableCheck = $conn->query("SHOW TABLES LIKE 'user_medications'");
if (!$tableCheck || $tableCheck->num_rows === 0) {
    echo json_encode(["ok" => true, "medications" => []], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$stmt = $conn->prepare("
    SELECT um.id AS um_id, um.medication_id, um.dose, um.note, um.created_at,
           m.name_ar, m.name_en, m.type, m.suitability
    FROM user_medications um
    JOIN medications m ON m.id = um.medication_id
    WHERE um.user_id = ?
    ORDER BY um.created_at DESC
    LIMIT 10
");

if (!$stmt) {
    echo json_encode(["ok" => false, "error" => "DB error: " . $conn->error], JSON_UNESCAPED_UNICODE);
    $conn->close();
    exit;
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$meds = [];
while ($row = $result->fetch_assoc()) {
    $meds[] = $row;
}

echo json_encode(["ok" => true, "medications" => $meds], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
?>
