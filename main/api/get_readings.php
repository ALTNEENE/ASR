<?php
declare(strict_types=1);

ob_start();
session_start();
header('Content-Type: application/json; charset=utf-8');

/**
 * @return never
 */
function respond(array $payload): void
{
    ob_clean();
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * @return never
 */
function fail(string $message): void
{
    respond([
        'success' => false,
        'error' => $message,
    ]);
}

function mapStatusKeyToAr(string $statusKey): string
{
    switch (strtolower(trim($statusKey))) {
        case 'low':
            return 'منخفض';
        case 'normal':
            return 'طبيعي';
        case 'high':
            return 'مرتفع';
        default:
            return '';
    }
}

function mapStatusArToKey(string $statusAr): string
{
    switch ($statusAr) {
        case 'منخفض':
            return 'low';
        case 'طبيعي':
            return 'normal';
        case 'مرتفع':
            return 'high';
        default:
            return '';
    }
}

function containsText(string $haystack, string $needle): bool
{
    return strpos($haystack, $needle) !== false;
}

function normalizeStatusText(?string $raw): string
{
    $text = trim((string)$raw);
    if ($text === '') {
        return '';
    }

    if (
        containsText($text, 'منخفض') ||
        containsText($text, 'ظ…ظ†ط®ظپط¶') ||
        containsText($text, 'Ù…Ù†Ø®ÙØ¶')
    ) {
        return 'منخفض';
    }

    if (
        containsText($text, 'مرتفع') ||
        containsText($text, 'ظ…ط±طھظپط¹') ||
        containsText($text, 'Ù…Ø±ØªÙØ¹')
    ) {
        return 'مرتفع';
    }

    if (
        containsText($text, 'طبيعي') ||
        containsText($text, 'ط·ط¨ظٹط¹ظٹ') ||
        containsText($text, 'Ø·Ø¨ÙŠØ¹ÙŠ') ||
        containsText($text, 'ما قبل السكري') ||
        containsText($text, 'ظ…ط§ ظ‚ط¨ظ„ ط§ظ„ط³ظƒط±ظٹ') ||
        stripos($text, 'prediab') !== false
    ) {
        return 'طبيعي';
    }

    return '';
}

function readingToMgdl(float $value, string $unit): float
{
    return strtolower(trim($unit)) === 'mmol' ? $value * 18.0 : $value;
}

function resolveUserAge(mysqli $conn, int $userId): int
{
    $age = 30;

    $stmt = $conn->prepare('SELECT age FROM user_profile WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($row['age']) && is_numeric($row['age'])) {
            return (int)$row['age'];
        }
    }

    $stmt = $conn->prepare('SELECT age FROM patient_data WHERE user_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!empty($row['age']) && is_numeric($row['age'])) {
            return (int)$row['age'];
        }
    }

    return $age;
}

try {
    if (!isset($_SESSION['user_id'])) {
        fail('غير مسجل دخول');
    }

    require_once __DIR__ . '/../../google_sign_in/config/db.php';
    require_once __DIR__ . '/../../config/bootstrap.php';

    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        fail('فشل الاتصال بقاعدة البيانات');
    }

    $userId = (int)$_SESSION['user_id'];
    $limit = max(1, (int)($_GET['limit'] ?? 10));
    $offset = max(0, (int)($_GET['offset'] ?? 0));
    $age = resolveUserAge($conn, $userId);
    $evaluator = new \App\GlucoseEvaluator();

    $availableCols = [];
    $schemaRes = $conn->query('SHOW COLUMNS FROM glucose_readings');
    if ($schemaRes) {
        while ($schemaRow = $schemaRes->fetch_assoc()) {
            $availableCols[(string)$schemaRow['Field']] = true;
        }
        $schemaRes->close();
    }

    $baseCols = [
        'id',
        'reading_value',
        'reading_unit',
        'reading_context',
        'reading_date',
        'reading_time',
        'note',
        'medications',
        'psychological_status',
        'classification',
    ];
    $optionalCols = ['status_key', 'status_ar', 'reason_ar', 'created_at'];
    $selectCols = [];

    foreach (array_merge($baseCols, $optionalCols) as $col) {
        if (isset($availableCols[$col])) {
            $selectCols[] = $col;
        }
    }

    if (empty($selectCols)) {
        fail('هيكل جدول القراءات غير صالح');
    }

    $orderByCreated = isset($availableCols['created_at']) ? ', created_at DESC' : '';
    $query = 'SELECT ' . implode(', ', $selectCols) .
        " FROM glucose_readings WHERE user_id = ? ORDER BY reading_date DESC, COALESCE(reading_time, '00:00:00') DESC{$orderByCreated} LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        fail('فشل تجهيز جلب القراءات: ' . $conn->error);
    }

    $stmt->bind_param('iii', $userId, $limit, $offset);
    if (!$stmt->execute()) {
        $stmt->close();
        fail('فشل تنفيذ جلب القراءات: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $readings = [];

    $statsTotal = 0;
    $statsHigh = 0;
    $statsNormal = 0;
    $statsLow = 0;
    $statsFasting = 0;
    $statsPost = 0;
    $sum = 0.0;
    $min = null;
    $max = null;

    while ($row = $result->fetch_assoc()) {
        $value = (float)($row['reading_value'] ?? 0);
        $unit = (string)($row['reading_unit'] ?? 'mg');
        $context = (string)($row['reading_context'] ?? 'fasting');
        $valueMg = readingToMgdl($value, $unit);

        $statusKey = strtolower(trim((string)($row['status_key'] ?? '')));
        $statusAr = mapStatusKeyToAr($statusKey);

        if ($statusAr === '') {
            $statusAr = normalizeStatusText((string)($row['status_ar'] ?? ''));
        }
        if ($statusAr === '') {
            $statusAr = normalizeStatusText((string)($row['classification'] ?? ''));
        }

        if ($statusAr === '' && $value > 0) {
            $eval = $evaluator->evaluate($age, $valueMg, $context === 'post' ? 'after_meal' : $context);
            $statusKey = (string)$eval['status_key'];
            $statusAr = (string)$eval['status_ar'];
            if (empty($row['reason_ar'])) {
                $row['reason_ar'] = (string)$eval['reason_ar'];
            }
        }

        if ($statusAr === '') {
            $statusAr = 'طبيعي';
        }
        if ($statusKey === '') {
            $statusKey = mapStatusArToKey($statusAr);
        }
        if ($statusKey === '') {
            $statusKey = 'normal';
        }

        $row['status_key'] = $statusKey;
        $row['status_ar'] = $statusAr;
        $row['classification'] = $statusAr;
        $readings[] = $row;

        if ($value > 0) {
            $statsTotal++;
            $sum += $valueMg;
            $min = $min === null ? $valueMg : min($min, $valueMg);
            $max = $max === null ? $valueMg : max($max, $valueMg);

            if ($context === 'fasting') {
                $statsFasting++;
            } elseif ($context === 'post') {
                $statsPost++;
            }

            if ($statusKey === 'high') {
                $statsHigh++;
            } elseif ($statusKey === 'low') {
                $statsLow++;
            } else {
                $statsNormal++;
            }
        }
    }
    $stmt->close();
    $conn->close();

    $statistics = [
        'total_readings' => $statsTotal,
        'high_count' => $statsHigh,
        'normal_count' => $statsNormal,
        'low_count' => $statsLow,
        'fasting_count' => $statsFasting,
        'post_count' => $statsPost,
        'avg_reading' => $statsTotal > 0 ? round($sum / $statsTotal, 2) : null,
        'max_reading' => $max !== null ? round($max, 2) : null,
        'min_reading' => $min !== null ? round($min, 2) : null,
    ];

    respond([
        'success' => true,
        'readings' => $readings,
        'statistics' => $statistics,
    ]);
} catch (Throwable $e) {
    fail($e->getMessage());
}
