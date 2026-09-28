<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * GET /api/collections.php
 *   ?slug=xxx   -> single collection
 */

$slug = $_GET['slug'] ?? null;

$where  = ["status = 1"];
$params = [];

if ($slug !== null) {
    $where[]  = "slug = ?";
    $params[] = $slug;
}

$sql = "
    SELECT id, name, slug, description
    FROM collections
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
];

if ($slug !== null) {
    if (!$rows) {
        jsonError('Collection not found', 404);
    }
    jsonResponse($map($rows[0]));
}

jsonResponse(['data' => array_map($map, $rows), 'count' => count($rows)]);
