<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'vendor/autoload.php';

$db          = getDB();
$settings    = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_group='stripe'")->fetchAll(PDO::FETCH_KEY_PAIR);
$secretKey   = $settings['stripe_secret_key'] ?? '';
$orderNumber = $_GET['order'] ?? '';
$sessionId   = $_GET['session_id'] ?? '';

if ($secretKey && $sessionId) {
    \Stripe\Stripe::setApiKey($secretKey);
    try {
        $session = \Stripe\Checkout\Session::retrieve($sessionId);
        if ($session->payment_status === 'paid') {
            $db->prepare("UPDATE orders SET payment_status='paid', payment_method='carte' WHERE order_number=?")
               ->execute([$orderNumber]);
            // Paiement encaisse : le panier peut etre vide.
            $_SESSION['cart'] = [];
        }
    } catch (Exception $e) {}
}

header('Location: ' . SITE_URL . '/confirmation.php?order=' . urlencode($orderNumber) . '&payment=success');
exit;
