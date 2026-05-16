<?php

declare(strict_types=1);

/**
 * POST /main/api/delete_readings.php
 * 
 * Securely delete glucose readings for the authenticated user.
 * 
 * Body (JSON or FormData):
 *   action: "by_ids"   => ids: [1, 2, 3]
 *   action: "by_days"  => days: 90 (deletes readings older than N days)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'غير مسجل دخول'], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../google_sign_in/config/db.php';

$userId = (int)$_SESSION['user_id'];

// Parse JSON or FormData
$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = trim((string)($input['action'] ?? ''));

try {
    // ── Action A: Delete by specific IDs ─────────────────────────────
    if ($action === 'by_ids') {
        $ids = $input['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['ok' => false, 'error' => 'لم يتم تحديد قراءات'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Validate and sanitize IDs
        $cleanIds = [];
        foreach ($ids as $id) {
            $int = (int)$id;
            if ($int > 0) $cleanIds[] = $int;
        }

        if (empty($cleanIds)) {
            echo json_encode(['ok' => false, 'error' => 'معرّفات غير صالحة'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $params = array_merge([$userId], $cleanIds);

        $stmt = $conn->prepare(
            "DELETE FROM glucose_readings WHERE user_id = ? AND id IN ($placeholders)"
        );
        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        echo json_encode([
            'ok'      => true,
            'deleted' => $deleted,
            'message' => "تم حذف {$deleted} قراءة بنجاح",
        ], JSON_UNESCAPED_UNICODE);

    // ── Action B: Delete by age (older than N days) ───────────────────
    } elseif ($action === 'by_days') {
        $days = (int)($input['days'] ?? 90);
        if ($days < 1 || $days > 3650) {
            echo json_encode(['ok' => false, 'error' => 'عدد الأيام يجب أن يكون بين 1 و 3650'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $conn->prepare(
            "DELETE FROM glucose_readings
             WHERE user_id = ?
               AND reading_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)"
        );
        $stmt->bind_param('ii', $userId, $days);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();

        echo json_encode([
            'ok'      => true,
            'deleted' => $deleted,
            'message' => "تم حذف {$deleted} قراءة أقدم من {$days} يوم",
        ], JSON_UNESCAPED_UNICODE);

    } else {
        echo json_encode(['ok' => false, 'error' => 'إجراء غير معروف. استخدم by_ids أو by_days'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'خطأ داخلي: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} finally {
    if (isset($conn)) $conn->close();
}
