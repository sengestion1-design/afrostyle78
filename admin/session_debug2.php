<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
define('SESSION_NAME', 'afrostyle_session');
session_name(SESSION_NAME);
session_start();
echo '<pre>';
echo "SESSION cart:\n";
$cart = $_SESSION['cart'] ?? [];
if(empty($cart)) { echo "PANIER VIDE\n"; } 
else {
    foreach($cart as $key => $item) {
        echo "KEY: $key\n";
        if(!is_array($item)) { echo "  [NOT AN ARRAY: ".var_export($item,true)."]\n"; continue; }
        foreach($item as $k => $v) {
            if(is_array($v)) echo "  $k => ".json_encode($v)."\n";
            else echo "  $k => ".var_export($v, true)."\n";
        }
        echo "\n";
    }
}
echo '</pre>';
