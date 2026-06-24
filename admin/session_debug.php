<?php
require_once 'config/config.php';
echo '<pre>';
echo "SESSION cart:\n";
$cart = $_SESSION['cart'] ?? [];
foreach($cart as $key => $item) {
    echo "KEY: $key\n";
    foreach($item as $k => $v) {
        if(is_array($v)) echo "  $k => [array]\n";
        else echo "  $k => " . var_export($v, true) . "\n";
    }
    echo "\n";
}
echo '</pre>';
