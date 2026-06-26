<?php
/**
 * PayPal — Retour après approbation : on CAPTURE le paiement côté serveur,
 * on vérifie le statut "COMPLETED", puis on marque la commande payée.
 * On ne se fie jamais au simple retour navigateur : la capture est faite
 * via l'API avec notre access token.
 */
require_once 'config/config.php';
require_once 'config/database.php';

$db       = getDB();
$settings = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_group='paypal'")->fetchAll(PDO::FETCH_KEY_PAIR);

$clientId = $settings['paypal_client_id'] ?? '';
$secret   = $settings['paypal_secret']    ?? '';
$mode     = $settings['paypal_mode']      ?? 'sandbox';

$orderNumber = $_GET['order'] ?? '';
// PayPal renvoie le token (= id de l'ordre PayPal) dans l'URL
$paypalToken = $_GET['token'] ?? '';

$apiBase = $mode === 'live'
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

$paymentOk = false;

if ($clientId && $secret && $orderNumber) {
    // Récupérer la commande
    $stmt = $db->prepare("SELECT o.*, c.email, c.first_name FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.order_number=?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if ($order && $order['payment_status'] !== 'paid') {
        $paypalOrderId = $paypalToken ?: $order['paypal_order_id'];

        // 1. Access token
        $ch = curl_init($apiBase . '/v1/oauth2/token');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $secret);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $tokenResp = json_decode(curl_exec($ch), true);
        curl_close($ch);
        $accessToken = $tokenResp['access_token'] ?? '';

        if ($accessToken && $paypalOrderId) {
            // 2. Capturer le paiement
            $ch = curl_init($apiBase . '/v2/checkout/orders/' . urlencode($paypalOrderId) . '/capture');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
            $capResp = json_decode(curl_exec($ch), true);
            curl_close($ch);

            if (($capResp['status'] ?? '') === 'COMPLETED') {
                $paymentOk = true;
                $db->prepare("UPDATE orders SET payment_status='paid', payment_method='paypal' WHERE order_number=?")
                   ->execute([$orderNumber]);

                // Email de confirmation
                $items = $db->prepare("SELECT product_name, size, quantity, unit_price FROM order_items WHERE order_id=?");
                $items->execute([$order['id']]);
                $orderForEmail = [
                    'order_number'     => $order['order_number'],
                    'total_amount'     => $order['total_amount'],
                    'delivery_fee'     => $order['delivery_fee'],
                    'delivery_address' => $order['delivery_address'],
                    'delivery_city'    => $order['delivery_city'],
                    'payment_method'   => 'paypal',
                    'sender_phone'     => '',
                ];
                @emailOrderConfirmation($order['email'], $order['first_name'], $orderForEmail, $items->fetchAll());
            }
        }
    } elseif ($order && $order['payment_status'] === 'paid') {
        $paymentOk = true; // déjà payé
    }
}

header('Location: ' . SITE_URL . '/confirmation.php?order=' . urlencode($orderNumber) . '&payment=' . ($paymentOk ? 'success' : 'cancelled'));
exit;
