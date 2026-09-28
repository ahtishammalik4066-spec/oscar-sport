<?php
require_once __DIR__ . '/bootstrap.php';

/**
 * POST /api/orders.php   (JSON body)
 * {
 *   "customer": {"name","email","phone"},
 *   "shipping": {"address","city","postalCode"},
 *   "paymentMethod": "cod",
 *   "notes": "",
 *   "items": [{"productId": "1", "quantity": 2, "variant": "Red / M"}]
 * }
 * Prices are ALWAYS re-read from the database (never trusted from the browser).
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Use POST', 405);
}

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) {
    jsonError('Invalid JSON body');
}

$name    = trim($in['customer']['name']  ?? '');
$email   = trim($in['customer']['email'] ?? '');
$phone   = trim($in['customer']['phone'] ?? '');
$address = trim($in['shipping']['address']    ?? '');
$city    = trim($in['shipping']['city']       ?? '');
$postal  = trim($in['shipping']['postalCode'] ?? '');
$notes   = trim($in['notes'] ?? '');
$items   = $in['items'] ?? [];

if ($name === '' || $phone === '' || $address === '' || $city === '') {
    jsonError('Name, phone, address and city are required');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonError('A valid email is required');
}
if (!is_array($items) || count($items) === 0) {
    jsonError('Cart is empty');
}

try {
    $pdo->beginTransaction();

    $lines    = [];
    $subtotal = 0.0;

    foreach ($items as $it) {
        $pid = (int) ($it['productId'] ?? 0);
        $qty = (int) ($it['quantity'] ?? 0);
        if ($pid <= 0 || $qty <= 0 || $qty > 100) {
            throw new RuntimeException('Invalid item in cart');
        }

        $st = $pdo->prepare("SELECT id, name, sku, price, sale_price, stock_quantity FROM products WHERE id = ? AND status = 'active' FOR UPDATE");
        $st->execute([$pid]);
        $p = $st->fetch();
        if (!$p) {
            throw new RuntimeException('A product in your cart is no longer available');
        }
        if ((int) $p['stock_quantity'] < $qty) {
            throw new RuntimeException("Only {$p['stock_quantity']} left in stock for {$p['name']}");
        }

        $price = (float) $p['price'];
        $sale  = $p['sale_price'] !== null ? (float) $p['sale_price'] : 0;
        if ($sale > 0 && $sale < $price) {
            $price = $sale;
        }

        $lineTotal = $price * $qty;
        $subtotal += $lineTotal;
        $lines[] = [
            'id' => $pid, 'name' => $p['name'], 'sku' => $p['sku'], 'qty' => $qty,
            'price' => $price, 'subtotal' => $lineTotal,
            'variant' => substr(trim($it['variant'] ?? ''), 0, 255) ?: null,
        ];
    }

    $shipping = $subtotal > 10000 ? 0.0 : 500.0;   // same rule as the storefront
    $total    = $subtotal + $shipping;
    $orderNo  = 'WB-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

    $pdo->prepare("
        INSERT INTO orders
          (order_number, customer_name, customer_email, customer_phone,
           shipping_address, shipping_city, shipping_postal_code,
           subtotal, shipping_fee, discount, total_amount,
           payment_method, payment_status, order_status, notes)
        VALUES (?,?,?,?,?,?,?,?,?,0,?,?, 'Pending', 'Pending', ?)
    ")->execute([
        $orderNo, $name, $email, $phone, $address, $city, $postal ?: null,
        $subtotal, $shipping, $total, 'Cash on delivery', $notes ?: null,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $insItem = $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, product_name, sku, variant_details, quantity, price, subtotal)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    $dec = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
    foreach ($lines as $l) {
        $insItem->execute([$orderId, $l['id'], $l['name'], $l['sku'], $l['variant'], $l['qty'], $l['price'], $l['subtotal']]);
        $dec->execute([$l['qty'], $l['id']]);
    }

    $pdo->prepare("INSERT INTO order_status_history (order_id, old_status, new_status) VALUES (?, NULL, 'Pending')")
        ->execute([$orderId]);

    $pdo->commit();
    jsonResponse(['ok' => true, 'orderNumber' => $orderNo, 'total' => $total], 201);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError($e->getMessage(), 422);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    jsonError('Could not save the order: ' . $e->getMessage(), 500);
}
