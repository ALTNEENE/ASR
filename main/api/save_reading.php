<?php

declare(strict_types=1);

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'غير مسجل دخول']);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';
require_once __DIR__ . '/../../config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'success' => false, 'error' => 'طريقة الطلب غير صحيحة']);
    exit;
}

/**
 * @return never
 */
function failResponse(string $message): void
{
    echo json_encode(['ok' => false, 'success' => false, 'error' => $message]);
    exit;
}

/**
 * Ensure glucose_readings has required columns used by this endpoint.
 */
function ensureStatusColumns(mysqli $conn): void
{
    $required = [
        'status_key' => "ALTER TABLE glucose_readings ADD COLUMN status_key VARCHAR(10) NULL AFTER classification",
        'status_ar' => "ALTER TABLE glucose_readings ADD COLUMN status_ar VARCHAR(20) NULL AFTER status_key",
        'reason_ar' => "ALTER TABLE glucose_readings ADD COLUMN reason_ar VARCHAR(255) NULL AFTER status_ar",
        'weight_kg' => "ALTER TABLE glucose_readings ADD COLUMN weight_kg DECIMAL(5,1) NULL AFTER reason_ar",
        'last_medication_dose' => "ALTER TABLE glucose_readings ADD COLUMN last_medication_dose VARCHAR(50) NULL AFTER weight_kg",
    ];

    $existing = [];
    $res = $conn->query("SHOW COLUMNS FROM glucose_readings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $existing[(string)$row['Field']] = true;
        }
        $res->close();
    }

    foreach ($required as $column => $sql) {
        if (!isset($existing[$column])) {
            if (!$conn->query($sql)) {
                throw new RuntimeException("فشل إضافة العمود {$column}: " . $conn->error);
            }
        }
    }
}

try {
    $userId = (int)$_SESSION['user_id'];

    $readingInput = $_POST['reading'] ?? null;
    if (!is_numeric($readingInput)) {
        failResponse('قيمة القراءة غير صحيحة');
    }
    $readingValue = (float)$readingInput;

    $readingUnit = strtolower(trim((string)($_POST['unit'] ?? 'mg')));
    if (!in_array($readingUnit, ['mg', 'mmol'], true)) {
        failResponse('وحدة القياس غير صحيحة');
    }

    $contextInput = trim((string)($_POST['context'] ?? 'fasting'));
    $readingDate = trim((string)($_POST['reading_date'] ?? date('Y-m-d')));
    $readingTime = trim((string)($_POST['reading_time'] ?? date('H:i:s')));
    $note = trim((string)($_POST['note'] ?? ''));
    $medications = trim((string)($_POST['medications'] ?? ''));
    $psychologicalStatus = trim((string)($_POST['psychological_status'] ?? 'Normal'));
    $lastMedicationDose = trim((string)($_POST['last_medication_dose'] ?? ''));

    $weightInput = isset($_POST['weight_kg']) ? trim((string)$_POST['weight_kg']) : '';
    if ($weightInput !== '' && !is_numeric($weightInput)) {
        failResponse('قيمة الوزن غير صحيحة');
    }
    $weightKgForDb = $weightInput === '' ? '' : (string)round((float)$weightInput, 1);

    if ($readingValue <= 0) {
        failResponse('قيمة القراءة يجب أن تكون أكبر من صفر');
    }

    $readingValueMg = $readingUnit === 'mmol' ? $readingValue * 18.0 : $readingValue;
    if ($readingValueMg < 20.0 || $readingValueMg > 600.0) {
        failResponse('القيمة خارج النطاق الواقعي (20 - 600 mg/dL)');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $readingDate)) {
        failResponse('صيغة التاريخ غير صحيحة');
    }

    if (preg_match('/^\d{2}:\d{2}$/', $readingTime)) {
        $readingTime .= ':00';
    }
    if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $readingTime)) {
        failResponse('صيغة الوقت غير صحيحة');
    }

    $age = 0;

    $ageStmt = $conn->prepare('SELECT age FROM user_profile WHERE user_id = ? LIMIT 1');
    if ($ageStmt) {
        $ageStmt->bind_param('i', $userId);
        $ageStmt->execute();
        $ageRow = $ageStmt->get_result()->fetch_assoc();
        $ageStmt->close();
        if (!empty($ageRow['age']) && is_numeric($ageRow['age'])) {
            $age = (int)$ageRow['age'];
        }
    }

    if ($age <= 0) {
        $ageStmt = $conn->prepare('SELECT age FROM patient_data WHERE user_id = ? LIMIT 1');
        if ($ageStmt) {
            $ageStmt->bind_param('i', $userId);
            $ageStmt->execute();
            $ageRow = $ageStmt->get_result()->fetch_assoc();
            $ageStmt->close();
            if (!empty($ageRow['age']) && is_numeric($ageRow['age'])) {
                $age = (int)$ageRow['age'];
            }
        }
    }

    if ($age <= 0 && isset($_SESSION['temp_patient_data']['age']) && is_numeric($_SESSION['temp_patient_data']['age'])) {
        $age = (int)$_SESSION['temp_patient_data']['age'];
    }
    if ($age <= 0) {
        $age = 30;
    }

    $evaluator = new \App\GlucoseEvaluator();
    $evaluation = $evaluator->evaluate($age, $readingValueMg, $contextInput);

    $statusKey = $evaluation['status_key'];
    $statusAr = $evaluation['status_ar'];
    $reasonAr = $evaluation['reason_ar'];
    $normalizedContext = $evaluation['context'];
    $contextForDb = $normalizedContext === 'after_meal' ? 'post' : 'fasting';

    // Keep legacy field for current UI/report compatibility.
    $classification = $statusAr;

    ensureStatusColumns($conn);

    $insertSql = "INSERT INTO glucose_readings
        (user_id, reading_value, reading_unit, reading_context, reading_date, reading_time,
         note, medications, psychological_status, classification, status_key, status_ar, reason_ar,
         weight_kg, last_medication_dose)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?)";

    $stmt = $conn->prepare($insertSql);
    if (!$stmt) {
        throw new RuntimeException('فشل تجهيز عملية الحفظ: ' . $conn->error);
    }

    $stmt->bind_param(
        'idsssssssssssss',
        $userId,
        $readingValue,
        $readingUnit,
        $contextForDb,
        $readingDate,
        $readingTime,
        $note,
        $medications,
        $psychologicalStatus,
        $classification,
        $statusKey,
        $statusAr,
        $reasonAr,
        $weightKgForDb,
        $lastMedicationDose
    );

    if (!$stmt->execute()) {
        throw new RuntimeException('فشل حفظ القراءة: ' . $stmt->error);
    }

    $newReadingId = (int)$stmt->insert_id;
    $stmt->close();

    // Save medication_ids[] into user_medications table (if provided).
    $medIds = $_POST['medication_ids'] ?? [];
    if (!empty($medIds) && is_array($medIds)) {
        $medStmt = $conn->prepare("INSERT INTO user_medications (user_id, medication_id) VALUES (?, ?)");
        $validCheck = $conn->prepare("SELECT id FROM medications WHERE id = ?");

        if ($medStmt && $validCheck) {
            foreach ($medIds as $medId) {
                $medicationId = (int)$medId;
                if ($medicationId <= 0) {
                    continue;
                }

                $validCheck->bind_param('i', $medicationId);
                $validCheck->execute();
                $validCheck->store_result();
                if ($validCheck->num_rows > 0) {
                    $medStmt->bind_param('ii', $userId, $medicationId);
                    $medStmt->execute();
                }
            }
            $validCheck->close();
            $medStmt->close();
        }
    }

    echo json_encode([
        'ok' => true,
        'success' => true,
        'reading_id' => $newReadingId,
        'value' => $readingValue,
        'value_mg' => round($readingValueMg, 2),
        'context' => $normalizedContext,
        'age' => $age,
        'status_key' => $statusKey,
        'status_ar' => $statusAr,
        'reason_ar' => $reasonAr,
        'classification' => $classification,
        'message' => 'تم حفظ القراءة بنجاح',
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
