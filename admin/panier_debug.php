<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('SESSION_NAME', 'afrostyle_session');
session_name(SESSION_NAME);
session_start();

define('SITE_URL', 'https://afrostyle78.com');
define('UPLOADS_URL', SITE_URL . '/uploads/products/');
define('CURRENCY', '€');

require_once '../config/database.php';

$cart = $_SESSION['cart'] ?? [];
echo '<pre>CART COUNT: '.count($cart).'</pre>';

$productSlugs = [];
if (!empty($cart)) {
    $productIds = array_values(array_filter(array_unique(array_column($cart, 'product_id'))));
    echo '<pre>PRODUCT IDS: '.json_encode($productIds).'</pre>';
    if (!empty($productIds)) {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $db = getDB();
        $slugStmt = $db->prepare("SELECT id, slug FROM products WHERE id IN ($placeholders)");
        $slugStmt->execute($productIds);
        foreach ($slugStmt->fetchAll() as $row) $productSlugs[$row['id']] = $row['slug'];
    }
}

echo '<pre>SLUGS: '.json_encode($productSlugs).'</pre>';

foreach($cart as $key => $item) {
    echo '<pre>';
    echo "KEY: $key | name: ".$item['name']." | price: ".$item['price']." | qty: ".$item['quantity']." | is_custom: ".var_export($item['is_custom'],true)." | measurements type: ".gettype($item['measurements']);
    echo '</pre>';
}

echo '<pre>--- FIN ---</pre>';
