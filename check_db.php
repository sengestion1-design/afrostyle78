<?php
require_once '/var/www/html/config/database.php';
$db = getDB();

// Vérifier les dernières order_items
$items = $db->query("SELECT oi.id, oi.order_id, oi.product_name, oi.size, oi.is_custom_measure FROM order_items oi ORDER BY oi.id DESC LIMIT 5")->fetchAll();
echo "=== ORDER_ITEMS ===\n";
foreach($items as $i) print_r($i);

// Vérifier measurements
$m = $db->query("SELECT * FROM measurements ORDER BY id DESC LIMIT 5")->fetchAll();
echo "=== MEASUREMENTS ===\n";
print_r($m);
