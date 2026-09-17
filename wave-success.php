<?php
require_once 'config/config.php';
require_once 'config/database.php';

$orderNumber = $_GET['order'] ?? '';

if ($orderNumber) {
    $db = getDB();
    // Marquer comme payé
    $db->prepare("UPDATE orders SET payment_status='paid', payment_method='wave' WHERE order_number=?")
       ->execute([$orderNumber]);
    // Paiement encaisse : le panier peut etre vide. Il ne l'est plus a la
    // creation de la commande, pour qu'un retour en arriere ne le perde pas.
    $_SESSION['cart'] = [];
}

header('Location: ' . SITE_URL . '/confirmation.php?order=' . urlencode($orderNumber) . '&payment=success');
exit;
