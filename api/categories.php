<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * GET /api/categories.php
 *   ?slug=xxx   -> single category
 */

$slug = $_GET['slug'] ?? null;

$where  = ["status = 1"];
$params = [];

if ($slug !== null) {
    $where[]  = "slug = ?";
    $params[] = $slug;
}

// Note: the `categories` table has no image column in this schema,
// so the frontend falls back to its own placeholder/local image per slug.
$sql = "
    SELECT id, name, slug, description
    FROM categories
    WHERE " . implode(' AND ', $where) . "
    ORDER BY sort_order ASC, id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$map = fn($row) => [
    'id'          => (string) $row['id'],
    'slug'        => $row['slug'],
    'name'        => $row['name'],
    'description' => $row['description'] ?? '',
    'image'       => '/images/categories/' . $row['slug'] . '.jpg',
];

if ($slug !== null) {
    if (!$rows) {
        jsonError('Category not found', 404);
    }
    jsonResponse($map($rows[0]));
}

jsonResponse(['data' => array_map($map, $rows), 'count' => count($rows)]);
