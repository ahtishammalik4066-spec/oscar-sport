<?php
/**
 * API bootstrap: CORS + JSON headers + DB connection + helpers.
 * Included at the top of every file in /api.
 */

// ---- CORS ----
// Allow the Next.js dev server (and same-origin) to call this API.
// In production, replace "*" with your real frontend domain for tighter security.
$allowedOrigins = [
    "http://localhost:3000",
    "http://127.0.0.1:3000",
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    // Fallback for quick local testing from any origin.
    header("Access-Control-Allow-Origin: *");
}

header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=utf-8");

// Preflight requests end here.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/database.php';
// $pdo is now available (created in config/database.php)

/**
 * Base URL used to build absolute links to uploaded images.
 * Auto-detected from the request, but you can hardcode it if needed,
 * e.g. define('APP_BASE_URL', 'http://localhost/Sports CMS');
 */
function apiBaseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // /api/products.php -> strip "/api/xxx.php" to get the CMS root
    $scriptDir = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
    return $scheme . '://' . $host . $scriptDir;
}

function productImageUrl(?string $filename): ?string
{
    if (!$filename) {
        return null;
    }
    return apiBaseUrl() . '/uploads/products/' . rawurlencode($filename);
}

function jsonResponse($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $status = 400): void
{
    jsonResponse(['error' => $message], $status);
}
