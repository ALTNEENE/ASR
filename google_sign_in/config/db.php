<?php
// Shared mysqli connection used by the legacy PHP pages.
require_once __DIR__ . '/../../config/database.php';

try {
    $conn = database_mysqli();
} catch (Throwable $e) {
    error_log($e->getMessage());
    http_response_code(500);
    die('Database connection failed. Check the deployment environment variables.');
}
