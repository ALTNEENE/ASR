<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'غير مسجل دخول'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';

$userId = (int)$_SESSION['user_id'];

function nz($value, $fallback = null)
{
    if ($value === null) return $fallback;
    if (is_string($value) && trim($value) === '') return $fallback;
    return $value;
}

try {
    $patient = [
        'name' => null,
        'age' => null,
        'gender' => null,
        'weight' => null,
        'height' => null,
        'date_of_diagnosis' => null,
        'therapy_type' => null,
        'diabetes_type' => null,
        'email' => null,
        'medications' => [],
    ];

    // Profile (primary source)
    $stmt = $conn->prepare('SELECT full_name, age, gender, weight, height, diagnosis_date, treatment_type, diabetes_type FROM user_profile WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (is_array($row)) {
            $patient['name'] = nz($row['full_name'] ?? null);
            $patient['age'] = isset($row['age']) && is_numeric($row['age']) ? (int)$row['age'] : null;
            $patient['gender'] = nz($row['gender'] ?? null);
            $patient['weight'] = isset($row['weight']) && is_numeric($row['weight']) ? (float)$row['weight'] : null;
            $patient['height'] = isset($row['height']) && is_numeric($row['height']) ? (float)$row['height'] : null;
            $patient['date_of_diagnosis'] = nz($row['diagnosis_date'] ?? null);
            $patient['therapy_type'] = nz($row['treatment_type'] ?? null);
            $patient['diabetes_type'] = nz($row['diabetes_type'] ?? null);
        }
    }

    // patient_data fallback/fill
    $stmt = $conn->prepare('SELECT name, age, gender, weight, height, date_of_diagnosis, therapy_type, drug_dose_map FROM patient_data WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (is_array($row)) {
            if ($patient['name'] === null) $patient['name'] = nz($row['name'] ?? null);
            if ($patient['age'] === null && isset($row['age']) && is_numeric($row['age'])) $patient['age'] = (int)$row['age'];
            if ($patient['gender'] === null) $patient['gender'] = nz($row['gender'] ?? null);
            if ($patient['weight'] === null && isset($row['weight']) && is_numeric($row['weight'])) $patient['weight'] = (float)$row['weight'];
            if ($patient['height'] === null && isset($row['height']) && is_numeric($row['height'])) $patient['height'] = (float)$row['height'];
            if ($patient['date_of_diagnosis'] === null) $patient['date_of_diagnosis'] = nz($row['date_of_diagnosis'] ?? null);
            if ($patient['therapy_type'] === null) $patient['therapy_type'] = nz($row['therapy_type'] ?? null);

            if (!empty($row['drug_dose_map'])) {
                $legacyMeds = json_decode((string)$row['drug_dose_map'], true);
                if (is_array($legacyMeds)) {
                    foreach ($legacyMeds as $drug => $dose) {
                        $drugText = trim((string)$drug);
                        $doseText = trim((string)$dose);
                        if ($drugText !== '') {
                            $patient['medications'][] = ['drug' => $drugText, 'dose' => $doseText];
                        }
                    }
                }
            }
        }
    }

    // Current medications (preferred)
    $stmt = $conn->prepare("
        SELECT COALESCE(NULLIF(TRIM(m.name_ar), ''), m.name_en) AS drug_name, um.dose
        FROM user_medications um
        JOIN medications m ON m.id = um.medication_id
        WHERE um.user_id = ?
        ORDER BY um.created_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!empty($rows)) {
            $patient['medications'] = [];
            foreach ($rows as $r) {
                $drug = trim((string)($r['drug_name'] ?? ''));
                if ($drug === '') continue;
                $patient['medications'][] = [
                    'drug' => $drug,
                    'dose' => trim((string)($r['dose'] ?? '')),
                ];
            }
        }
    }

    // Email (optional)
    $stmt = $conn->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (is_array($row)) {
            $patient['email'] = nz($row['email'] ?? null);
        }
    }

    // Session fallback if still empty
    $hasCoreData = $patient['name'] !== null || $patient['age'] !== null || $patient['gender'] !== null;
    if (!$hasCoreData && isset($_SESSION['temp_patient_data']) && is_array($_SESSION['temp_patient_data'])) {
        $temp = $_SESSION['temp_patient_data'];
        $patient['name'] = nz($temp['name'] ?? $temp['full_name'] ?? null);
        $patient['age'] = isset($temp['age']) && is_numeric($temp['age']) ? (int)$temp['age'] : null;
        $patient['gender'] = nz($temp['gender'] ?? null);
        $patient['weight'] = isset($temp['weight']) && is_numeric($temp['weight']) ? (float)$temp['weight'] : null;
        $patient['height'] = isset($temp['height']) && is_numeric($temp['height']) ? (float)$temp['height'] : null;
        $patient['date_of_diagnosis'] = nz($temp['date_of_diagnosis'] ?? null);
        $patient['therapy_type'] = nz($temp['therapy_type'] ?? null);
        $patient['diabetes_type'] = nz($temp['diabetes_type'] ?? null);
    }

    $hasAnyData = false;
    foreach (['name', 'age', 'gender', 'weight', 'height', 'date_of_diagnosis', 'therapy_type', 'diabetes_type', 'email'] as $key) {
        if ($patient[$key] !== null && $patient[$key] !== '') {
            $hasAnyData = true;
            break;
        }
    }
    if (!$hasAnyData && !empty($patient['medications'])) {
        $hasAnyData = true;
    }

    echo json_encode([
        'success' => true,
        'patient' => $hasAnyData ? $patient : null,
        'user_name' => (string)($_SESSION['full_name'] ?? 'المستخدم'),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'تعذر تحميل بيانات المريض',
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

