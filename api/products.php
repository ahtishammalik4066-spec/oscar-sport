<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * GET /api/products.php
 *   ?slug=xxx          -> single product (full detail incl. images, colors, sizes)
 *   ?category=slug      -> filter by category slug
 *   ?collection=slug    -> filter by collection slug
 *   ?featured=1          -> only featured products
 *   ?new=1                -> most recently added products
 *   ?search=text        -> match name/description
 *   ?limit=N            -> cap number of results
 *
 * Always excludes status = 'inactive' products (mirrors the storefront rule).
 */

function buildBadge(?float $price, ?float $salePrice): ?string
{
    if ($salePrice !== null && $price > 0 && $salePrice < $price) {
        $off = round((($price - $salePrice) / $price) * 100);
        return "-{$off}%";
    }
    return null;
}

/**
 * Turn one products row (already joined) into the shape the
 * Next.js frontend's `Product` type expects.
 */
function mapProduct(PDO $pdo, array $row): array
{
    $price      = (float) $row['price'];
    $salePrice  = $row['sale_price'] !== null ? (float) $row['sale_price'] : null;
    $onSale     = $salePrice !== null && $salePrice > 0 && $salePrice < $price;

    // Images
    $imgStmt = $pdo->prepare("
        SELECT image, is_primary
        FROM product_images
        WHERE product_id = ?
        ORDER BY is_primary DESC, sort_order ASC, id ASC
    ");
    $imgStmt->execute([$row['id']]);
    $imageRows = $imgStmt->fetchAll();

    $images = array_values(array_filter(array_map(
        fn($r) => productImageUrl($r['image']),
        $imageRows
    )));

    if (empty($images)) {
        // No uploaded photos yet -> fall back to a bundled placeholder asset
        // (a relative path resolves against the Next.js app's own host).
        $images = ['/images/products/bat-1.jpg'];
    }

    // Variants -> distinct colors / sizes
    $varStmt = $pdo->prepare("
        SELECT DISTINCT color, size
        FROM product_variants
        WHERE product_id = ? AND status = 'active'
    ");
    $varStmt->execute([$row['id']]);
    $variantRows = $varStmt->fetchAll();

    $colors = array_values(array_unique(array_filter(array_map(fn($r) => $r['color'], $variantRows))));
    $sizes  = array_values(array_unique(array_filter(array_map(fn($r) => $r['size'], $variantRows))));

    // Reviews -> average rating + count (approved only)
    $revStmt = $pdo->prepare("
        SELECT COALESCE(AVG(rating), 0) AS avg_rating, COUNT(*) AS review_count
        FROM reviews
        WHERE product_id = ? AND status = 'Approved'
    ");
    $revStmt->execute([$row['id']]);
    $revRow = $revStmt->fetch();

    return [
        'id'              => (string) $row['id'],
        'slug'            => $row['slug'],
        'name'            => $row['name'],
        'category'        => $row['category_name'] ?? '',
        'brand'           => 'WB Sports',
        'description'     => $row['description'] ?? '',
        'price'           => $onSale ? $salePrice : $price,
        'compareAtPrice'  => $onSale ? $price : null,
        'rating'          => round((float) $revRow['avg_rating'], 1),
        'reviews'         => (int) $revRow['review_count'],
        'badge'           => buildBadge($price, $salePrice),
        'colors'          => $colors,
        'sizes'           => $sizes,
        'images'          => $images,
        'secondaryImage'  => $images[1] ?? null,
        'featured'        => (bool) $row['featured'],
        'newArrival'      => isset($row['is_new']) ? (bool) $row['is_new'] : false,
        'collection'      => $row['collection_slug'] ?? null,
        'stock'           => (int) $row['stock_quantity'],
        'sku'             => $row['sku'],
    ];
}

$slug       = $_GET['slug'] ?? null;
$category   = $_GET['category'] ?? null;
$collection = $_GET['collection'] ?? null;
$featured   = $_GET['featured'] ?? null;
$new        = $_GET['new'] ?? null;
$search     = $_GET['search'] ?? null;
$limit      = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : null;

$where  = ["p.status = 'active'"];
$params = [];

if ($slug !== null) {
    $where[]  = "p.slug = ?";
    $params[] = $slug;
}

if ($category !== null) {
    $where[]  = "c.slug = ?";
    $params[] = $category;
}

if ($collection !== null) {
    $where[]  = "col.slug = ?";
    $params[] = $collection;
}

if ($featured !== null) {
    $where[] = "p.featured = 1";
}

if ($search !== null && $search !== '') {
    $where[]  = "(p.name LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql = "
    SELECT
        p.*,
        c.slug   AS category_slug,
        c.name   AS category_name,
        col.slug AS collection_slug,
        col.name AS collection_name,
        0 AS is_new
    FROM products p
    LEFT JOIN categories c   ON c.id = p.category_id
    LEFT JOIN collections col ON col.id = p.collection_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY p.id DESC
";

if ($limit !== null) {
    $sql .= " LIMIT " . $limit;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($slug !== null) {
    if (!$rows) {
        jsonError('Product not found', 404);
    }
    jsonResponse(mapProduct($pdo, $rows[0]));
}

// "new" isn't a real column in the current schema, so approximate
// "new arrivals" as the most recently added active products.
if ($new !== null) {
    $rows = array_slice($rows, 0, $limit ?? 8);
}

$products = array_map(fn($row) => mapProduct($pdo, $row), $rows);

jsonResponse(['data' => $products, 'count' => count($products)]);
