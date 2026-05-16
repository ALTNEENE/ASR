<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../google_sign_in/config/db.php';

$query = $_GET['q'] ?? '';

// Search in both Arabic and English names
$stmt = $conn->prepare("SELECT id, name_ar, name_en, type, suitability FROM medications WHERE name_ar LIKE ? OR name_en LIKE ? LIMIT 100");

if (empty($query)) {
    // Return all (limit 100) if no query provided
    $searchTerm = "%"; 
} else {
    $searchTerm = "%" . $query . "%";
}

$stmt->bind_param("ss", $searchTerm, $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$drugs = [];
while ($row = $result->fetch_assoc()) {
    $drugs[] = $row;
}

echo json_encode($drugs);

$stmt->close();
$conn->close();
?>
