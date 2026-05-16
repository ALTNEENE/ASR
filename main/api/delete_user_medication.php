<?php
/**
 * POST /main/api/delete_user_medication.php
 * Removes a medication from user_medications.
 * Input: POST { um_id } (user_medications.id)
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

$input = json_decode(file_get_contents("php://input"), true) ?: [];
$um_id = (int) ($input['um_id'] ?? $_POST['um_id'] ?? 0);
$user_id = (int) $_SESSION['user_id'];

if ($um_id <= 0) {
    echo json_encode(["ok" => false, "error" => "um_id مطلوب"], JSON_UNESCAPED_UNICODE);
    exit;
}

// Delete only if belongs to this user
$stmt = $conn->prepare("DELETE FROM user_medications WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $um_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["ok" => true, "message" => "تم حذف الدواء"], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode(["ok" => false, "error" => "لم يتم العثور على السجل"], JSON_UNESCAPED_UNICODE);
}

$stmt->close();
$conn->close();
?>
