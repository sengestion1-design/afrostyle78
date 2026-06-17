<?php
// Return URL PayDunya — ne met PAS à jour le paiement (le webhook IPN s'en charge)
require_once 'config/config.php';

$orderNumber = $_GET['order'] ?? '';

header('Location: ' . SITE_URL . '/confirmation.php?order=' . urlencode($orderNumber) . '&payment=success');
exit;
