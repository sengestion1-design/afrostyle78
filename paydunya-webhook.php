<?php
// IPN PayDunya — notifié automatiquement après confirmation de paiement
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/mailer.php';
require_once 'config/invoice.php';

$payload = file_get_contents('php://input');
$data    = json_decode($payload, true);

if (!$data) {
    http_response_code(400);
    exit;
}

// Vérification signature IPN : PayDunya envoie le master key dans le header
$receivedKey = $_SERVER['HTTP_PAYDUNYA_MASTER_KEY'] ?? $_SERVER['HTTP_X_PAYDUNYA_MASTER_KEY'] ?? '';

$db2        = getDB();
$s          = $db2->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('paydunya_master_key','paydunya_private_key','paydunya_token')")->fetchAll(PDO::FETCH_KEY_PAIR);
$masterKey  = $s['paydunya_master_key']  ?? getenv('PAYDUNYA_MASTER_KEY');
$privateKey = $s['paydunya_private_key'] ?? getenv('PAYDUNYA_PRIVATE_KEY');
$token      = $s['paydunya_token']       ?? getenv('PAYDUNYA_TOKEN');

if (!$masterKey || !$privateKey || !$token) {
    http_response_code(500);
    exit;
}

// Bloquer les requêtes IPN non authentifiées — header obligatoire
if (!$receivedKey || !hash_equals($masterKey, $receivedKey)) {
    http_response_code(403);
    exit;
}

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

$status           = $invoice['status'] ?? '';
$confirmedToken   = $invoice['token'] ?? $invoiceToken;
$confirmedAmount  = (int)($invoice['invoice']['total_amount'] ?? 0);

// Chercher la commande par paydunya_token stocké (plus fiable que custom_data)
$db = getDB();
$stmt = $db->prepare("SELECT * FROM orders WHERE paydunya_token = ? AND payment_status = 'unpaid'");
$stmt->execute([$confirmedToken]);
$order = $stmt->fetch();

if (!$order) {
    http_response_code(200);
    echo 'OK';
    exit;
}

$orderNumber     = $order['order_number'];
$expectedAmountXof = (int)round((float)$order['total_amount'] * 655.957);

if ($status === 'completed' && abs($confirmedAmount - $expectedAmountXof) <= 1) {
    $db->prepare("UPDATE orders SET payment_status='paid', payment_method='paydunya' WHERE order_number=?")
       ->execute([$orderNumber]);

    $stmt = $db->prepare("SELECT o.*, c.email, c.first_name, c.last_name, c.customer_address, c.customer_city FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.order_number=?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if ($order) {
        // Récupérer les articles de la commande
        $stmtItems = $db->prepare("SELECT oi.*, p.name AS product_name, p.images AS product_images FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?");
        $stmtItems->execute([$order['id']]);
        $items = $stmtItems->fetchAll();

        $customer = [
            'first_name'       => $order['first_name'],
            'last_name'        => $order['last_name'],
            'email'            => $order['email'],
            'customer_address' => $order['customer_address'] ?? $order['delivery_address'],
            'customer_city'    => $order['customer_city']    ?? $order['delivery_city'],
        ];

        try {
            $pdfString = generateInvoicePDF($order, $items, $customer);
            emailPaymentConfirmedWithInvoice($order['email'], $order['first_name'], $order, $items, $pdfString);
        } catch (Exception $e) {
            error_log('Invoice PDF error: ' . $e->getMessage());
        }
    }
}

http_response_code(200);
echo 'OK';
