<?php
/**
 * PayPal — Création de l'ordre de paiement (API REST v2).
 * Même pattern sécurisé que stripe-checkout.php :
 *  - montant TOUJOURS depuis la DB (jamais du POST)
 *  - vérif que la commande appartient au client connecté
 *  - anti double-paiement
 */
require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: application/json');

if (empty($_SESSION['customer_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Vous devez être connecté pour payer.']);
    exit;
}

$db       = getDB();
$settings = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_group='paypal'")->fetchAll(PDO::FETCH_KEY_PAIR);

$clientId = $settings['paypal_client_id'] ?? '';
$secret   = $settings['paypal_secret']    ?? '';
$mode     = $settings['paypal_mode']      ?? 'sandbox'; // 'sandbox' ou 'live'
$currency = $settings['paypal_currency']  ?? 'EUR';
// Les prix du site sont DEJA en euros -> aucune conversion (taux = 1 par defaut).
$rate     = (float)($settings['paypal_fcfa_to_eur'] ?? 1);
if ($rate <= 0) $rate = 1;

if (!$clientId || !$secret) {
    echo json_encode(['error' => 'PayPal non configuré. Ajoutez vos clés dans les paramètres admin.']);
    exit;
}

$apiBase = $mode === 'live'
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

$orderNumber = trim($_POST['order_number'] ?? '');
if (!$orderNumber) {
    echo json_encode(['error' => 'Numéro de commande manquant.']);
    exit;
}

// Récupérer la commande depuis la DB — ignorer tout montant POST
$stmt = $db->prepare("SELECT o.*, c.email, c.first_name, c.last_name FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.order_number=?");
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
    echo json_encode(['error' => 'Cette commande est déjà payée.']);
    exit;
}

// Montant depuis la DB uniquement
$totalFcfa = (float)$order['total_amount'];
$amountEur = round($totalFcfa * $rate, 2);

if ($amountEur < 0.01) {
    echo json_encode(['error' => 'Montant invalide.']);
    exit;
}

/** Helper appel API PayPal */
function paypalRequest(string $method, string $url, $auth, $body = null, bool $isJson = true) {
    $ch = curl_init($url);
    $headers = [];
    if (is_array($auth)) {
        // OAuth : Basic auth
        curl_setopt($ch, CURLOPT_USERPWD, $auth[0] . ':' . $auth[1]);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    } else {
        // Appel API avec access token
        $headers[] = 'Authorization: Bearer ' . $auth;
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $isJson ? json_encode($body) : $body);
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, json_decode($resp, true)];
}

// 1. Obtenir un access token OAuth
[$tokenCode, $tokenResp] = paypalRequest('POST', $apiBase . '/v1/oauth2/token', [$clientId, $secret], 'grant_type=client_credentials', false);
if ($tokenCode !== 200 || empty($tokenResp['access_token'])) {
    echo json_encode(['error' => 'Authentification PayPal échouée. Vérifiez vos clés.']);
    exit;
}
$accessToken = $tokenResp['access_token'];

// 2. Créer l'ordre
$orderPayload = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'reference_id' => $orderNumber,
        'description'  => 'Commande AfroStyle ' . $orderNumber,
        'amount' => [
            'currency_code' => $currency,
            'value'         => number_format($amountEur, 2, '.', ''),
        ],
    ]],
    'application_context' => [
        'brand_name'  => 'AfroStyle78',
        'locale'      => 'fr-FR',
        'user_action' => 'PAY_NOW',
        'return_url'  => SITE_URL . '/paypal-success.php?order=' . urlencode($orderNumber),
        'cancel_url'  => SITE_URL . '/confirmation.php?order=' . urlencode($orderNumber) . '&payment=cancelled',
    ],
];

[$createCode, $createResp] = paypalRequest('POST', $apiBase . '/v2/checkout/orders', $accessToken, $orderPayload);
if (($createCode !== 200 && $createCode !== 201) || empty($createResp['id'])) {
    echo json_encode(['error' => 'Création du paiement PayPal échouée.']);
    exit;
}

// Trouver l'URL d'approbation
$approveUrl = '';
foreach ($createResp['links'] ?? [] as $link) {
    if (($link['rel'] ?? '') === 'approve') { $approveUrl = $link['href']; break; }
}
if (!$approveUrl) {
    echo json_encode(['error' => 'Lien de paiement PayPal introuvable.']);
    exit;
}

// Mémoriser l'id d'ordre PayPal pour la capture au retour
$db->prepare("UPDATE orders SET paypal_order_id=? WHERE order_number=?")->execute([$createResp['id'], $orderNumber]);

echo json_encode(['url' => $approveUrl]);
