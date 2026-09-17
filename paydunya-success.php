<?php
// Return URL PayDunya — ne met PAS à jour le paiement (le webhook IPN s'en charge)
require_once 'config/config.php';

$orderNumber = $_GET['order'] ?? '';

// Le client revient de PayDunya : son panier peut etre vide. La confirmation
// du paiement elle-meme reste du ressort du webhook IPN.
// Le panier n'est plus vide a la creation de la commande, pour qu'un retour en
// arriere avant paiement ne fasse pas tout perdre au client.
if ($orderNumber !== '') {
    $_SESSION['cart'] = [];
}

header('Location: ' . SITE_URL . '/confirmation.php?order=' . urlencode($orderNumber) . '&payment=success');
exit;
