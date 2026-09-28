<?php
// GET /api/health.php  ->  quick "is the backend + database alive?" check
require_once __DIR__ . '/bootstrap.php';

try {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE status = 'active'")->fetchColumn();
    jsonResponse(['status' => 'ok', 'database' => 'connected', 'active_products' => $count, 'time' => date('c')]);
} catch (Throwable $e) {
    jsonError('Database query failed: ' . $e->getMessage(), 500);
}
