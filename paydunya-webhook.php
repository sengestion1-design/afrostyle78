<?php
// IPN PayDunya — notifié automatiquement après confirmation de paiement
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/mailer.php';

$payload = file_get_contents('php://input');
$data    = json_decode($payload, true);

if (!$data) {
    http_response_code(400);
    exit;
}

$masterKey  = 'lvXZhkmQ-6reb-ZImt-DuuL-iYRpJuqa7r2z';
$privateKey = 'live_private_66iDiEIdYje03GqVl0vLOZJPBX6';
$token      = 'WI5qQNHHhW5k9psP6rdl';

// Récupérer le token de la facture depuis le payload IPN
$invoiceToken = $data['data']['invoice']['token'] ?? '';

if (!$invoiceToken) {
    http_response_code(400);
    exit;
}

// Vérifier le statut de la facture auprès de PayDunya
$ch = curl_init('https://app.paydunya.com/api/v1/checkout-invoice/confirm/' . $invoiceToken);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'PAYDUNYA-MASTER-KEY: '  . $masterKey,
        'PAYDUNYA-PRIVATE-KEY: ' . $privateKey,
        'PAYDUNYA-TOKEN: '       . $token,
    ],
]);
$response = curl_exec($ch);
curl_close($ch);

$invoice = json_decode($response, true);

$status      = $invoice['status'] ?? '';
$orderNumber = $invoice['custom_data']['order_number'] ?? '';

if ($status === 'completed' && $orderNumber) {
    $db = getDB();

    $db->prepare("UPDATE orders SET payment_status='paid', payment_method='paydunya' WHERE order_number=?")
       ->execute([$orderNumber]);

    $stmt = $db->prepare("SELECT o.*, c.email, c.first_name, c.last_name FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.order_number=?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if ($order) {
        emailOrderConfirmed($order['email'], $order['first_name'], $order['last_name'], $orderNumber, $order['total_amount']);
    }
}

http_response_code(200);
echo 'OK';
