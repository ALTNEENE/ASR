<?php
/**
 * POST /main/api/save_user_medication.php
 * Saves a medication to user_medications pivot table.
 * Input: POST { medication_id, dose? (optional), note? (optional) }
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "غير مسجل دخول"], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Method not allowed"], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';

// Accept JSON or form data
$input = json_decode(file_get_contents("php://input"), true) ?: [];
$medication_id = (int) ($input['medication_id'] ?? $_POST['medication_id'] ?? 0);
$dose = trim($input['dose'] ?? $_POST['dose'] ?? '');
$note = trim($input['note'] ?? $_POST['note'] ?? '');

$user_id = (int) $_SESSION['user_id'];

// Validate medication_id > 0
if ($medication_id <= 0) {
    echo json_encode(["ok" => false, "error" => "medication_id مطلوب"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Validate medication exists in dictionary
$check = $conn->prepare("SELECT id, name_ar, name_en FROM medications WHERE id = ?");
$check->bind_param("i", $medication_id);
$check->execute();
$med = $check->get_result()->fetch_assoc();
$check->close();

if (!$med) {
    echo json_encode(["ok" => false, "error" => "الدواء غير موجود في القاموس"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check for duplicate: same user + same medication
$dup = $conn->prepare("SELECT id FROM user_medications WHERE user_id = ? AND medication_id = ?");
$dup->bind_param("ii", $user_id, $medication_id);
$dup->execute();
if ($dup->get_result()->num_rows > 0) {
    $dup->close();
    echo json_encode([
        "ok" => true,
        "duplicate" => true,
        "message" => "هذا الدواء مضاف مسبقاً",
        "name_ar" => $med['name_ar'],
        "name_en" => $med['name_en']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$dup->close();

// Insert
$stmt = $conn->prepare("INSERT INTO user_medications (user_id, medication_id, dose, note) VALUES (?, ?, ?, ?)");
$doseVal = $dose ?: null;
$noteVal = $note ?: null;
$stmt->bind_param("iiss", $user_id, $medication_id, $doseVal, $noteVal);

if ($stmt->execute()) {
    echo json_encode([
        "ok" => true,
        "um_id" => $stmt->insert_id,
        "name_ar" => $med['name_ar'],
        "name_en" => $med['name_en'],
        "message" => "تم حفظ الدواء بنجاح"
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["ok" => false, "error" => "فشل الحفظ: " . $conn->error], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
