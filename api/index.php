<?php
require_once __DIR__ . '/bootstrap.php';

jsonResponse([
    'name' => 'Sports CMS API',
    'endpoints' => [
        'GET /api/products.php'               => 'List active products (filters: category, collection, featured, new, search, limit)',
        'GET /api/products.php?slug=xxx'      => 'Single product by slug (full detail)',
        'GET /api/categories.php'             => 'List active categories',
        'GET /api/categories.php?slug=xxx'    => 'Single category by slug',
        'GET /api/collections.php'            => 'List active collections',
        'GET /api/collections.php?slug=xxx'   => 'Single collection by slug',
        'GET /api/health.php'                 => 'Backend + database health check',
        'POST /api/orders.php'                => 'Create an order from the storefront checkout (JSON body)',
    ],
]);
