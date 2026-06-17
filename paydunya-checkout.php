<?php
require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['customer_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Vous devez être connecté pour payer.']);
    exit;
}

$db          = getDB();
$allSettings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

$masterKey   = $allSettings['paydunya_master_key']   ?? 'lvXZhkmQ-6reb-ZImt-DuuL-iYRpJuqa7r2z';
$privateKey  = $allSettings['paydunya_private_key']  ?? 'live_private_66iDiEIdYje03GqVl0vLOZJPBX6';
$token       = $allSettings['paydunya_token']        ?? 'WI5qQNHHhW5k9psP6rdl';
$publicKey   = $allSettings['paydunya_public_key']   ?? 'live_public_7FHzlF6BkenRSjZj4W6AWpNB35A';

$orderNumber = trim($_POST['order_number'] ?? '');
if (!$orderNumber) {
    echo json_encode(['error' => 'Numéro de commande manquant.']);
    exit;
}

$stmt = $db->prepare("SELECT o.*, c.email, c.first_name, c.last_name, c.phone FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.order_number=?");
$stmt->execute([$orderNumber]);
$order = $stmt->fetch();

if (!$order) {
    echo json_encode(['error' => 'Commande introuvable.']);
    exit;
}

if ((int)$order['customer_id'] !== (int)$_SESSION['customer_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé.']);
    exit;
}

if ($order['payment_status'] === 'paid') {
    echo json_encode(['error' => 'Commande déjà payée.']);
    exit;
}

// Montant en XOF (conversion EUR → XOF si nécessaire)
// 1 EUR ≈ 655.957 XOF (taux fixe FCFA)
$amountEur = (float)$order['total_amount'];
$amountXof = (int)round($amountEur * 655.957);

$successUrl = SITE_URL . '/paydunya-success.php?order=' . urlencode($orderNumber);
$cancelUrl  = SITE_URL . '/confirmation.php?order=' . urlencode($orderNumber) . '&payment=cancelled';
$ipnUrl     = SITE_URL . '/paydunya-webhook.php';

$payload = [
    'invoice' => [
        'items' => [
            'item_0' => [
                'name'        => 'Commande AfroStyle78 #' . $orderNumber,
                'quantity'    => 1,
                'unit_price'  => $amountXof,
                'total_price' => $amountXof,
                'description' => 'Mode africaine sur-mesure',
            ],
        ],
        'total_amount'  => $amountXof,
        'description'   => 'Commande #' . $orderNumber . ' - AfroStyle78',
    ],
    'store' => [
        'name'          => 'AfroStyle78',
        'tagline'       => "L'Afrique réinventée",
        'postal_address'=> 'Guyancourt, Yvelines (78), France',
        'phone'         => '+33644728730',
        'website_url'   => SITE_URL,
        'logo_url'      => SITE_URL . '/logo.jpg',
    ],
    'actions' => [
        'cancel_url'    => $cancelUrl,
        'return_url'    => $successUrl,
        'callback_url'  => $ipnUrl,
    ],
    'custom_data' => [
        'order_number'  => $orderNumber,
        'customer_email'=> $order['email'],
    ],
];

$ch = curl_init('https://app.paydunya.com/api/v1/checkout-invoice/create');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'PAYDUNYA-MASTER-KEY: '  . $masterKey,
        'PAYDUNYA-PRIVATE-KEY: ' . $privateKey,
        'PAYDUNYA-TOKEN: '       . $token,
        'Content-Type: application/json',
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 200 && !empty($data['response_code']) && $data['response_code'] === '00') {
    $invoiceToken = $data['token'] ?? '';
    $payUrl       = 'https://app.paydunya.com/sandbox-checkout/invoice/' . $invoiceToken;
    // En production :
    $payUrl       = 'https://app.paydunya.com/checkout/invoice/' . $invoiceToken;

    $db->prepare("UPDATE orders SET paydunya_token=? WHERE order_number=?")
       ->execute([$invoiceToken, $orderNumber]);

    echo json_encode(['url' => $payUrl]);
} else {
    $msg = $data['response_text'] ?? ($data['message'] ?? 'Erreur PayDunya. Réessayez.');
    echo json_encode(['error' => $msg]);
}
