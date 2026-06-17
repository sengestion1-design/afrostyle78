<?php
require_once 'config/config.php';
require_once 'config/database.php';

$orderNumber = $_GET['order'] ?? '';

if ($orderNumber) {
    $db = getDB();
    $db->prepare("UPDATE orders SET payment_status='paid', payment_method='paydunya' WHERE order_number=?")
       ->execute([$orderNumber]);
}

header('Location: ' . SITE_URL . '/confirmation.php?order=' . urlencode($orderNumber) . '&payment=success');
exit;
