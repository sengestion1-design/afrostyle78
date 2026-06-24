<?php
require_once '../config/database.php';
$db = getDB();

echo '<pre>';
echo "=== ORDER_ITEMS (5 derniers) ===\n";
$rows = $db->query("SELECT id, order_id, product_name, size, is_custom_measure FROM order_items ORDER BY id DESC LIMIT 5")->fetchAll();
foreach($rows as $r) echo implode(' | ', array_map(fn($k,$v)=>"$k=$v", array_keys($r), $r))."\n";

echo "\n=== MEASUREMENTS (5 derniers) ===\n";
$rows2 = $db->query("SELECT * FROM measurements ORDER BY id DESC LIMIT 5")->fetchAll();
if(empty($rows2)) echo "-- TABLE VIDE --\n";
foreach($rows2 as $r) echo implode(' | ', array_map(fn($k,$v)=>"$k=$v", array_keys($r), $r))."\n";
echo '</pre>';
