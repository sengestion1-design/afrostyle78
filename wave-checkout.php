<?php
require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: application/json');

// L'identite est verifiee plus bas : proprietaire de la session, OU porteur du
// jeton de confirmation. Un visiteur pouvait commander sans compte puis se voir
// refuser le paiement. Meme controle que paydunya-checkout.php.
$confirmToken = trim($_POST['confirm_token'] ?? $_POST['t'] ?? '');

$db          = getDB();
$allSettings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$waveApiKey  = $allSettings['wave_api_key'] ?? '';

if (!$waveApiKey) {
    echo json_encode(['error' => 'Wave API non configurée.']);
    exit;
}

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

// Session du client connecte, ou jeton propre a la commande (64 caracteres
// aleatoires, compares en temps constant). Sans l'un des deux : refus.
$isOwner = !empty($_SESSION['customer_id']) && (int)$order['customer_id'] === (int)$_SESSION['customer_id'];
$isGuest = !empty($confirmToken) && !empty($order['confirm_token'])
           && hash_equals($order['confirm_token'], $confirmToken);
if (!$isOwner && !$isGuest) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé.']);
    exit;
}

if ($order['payment_status'] === 'paid') {
    echo json_encode(['error' => 'Commande déjà payée.']);
    exit;
}

// Créer le lien de paiement Wave
$amount     = (int)round((float)$order['total_amount'] * 100); // centimes
$successUrl = SITE_URL . '/wave-success.php?order=' . urlencode($orderNumber);
$errorUrl   = SITE_URL . '/confirmation.php?order=' . urlencode($orderNumber) . '&payment=cancelled';

$payload = [
    'amount'      => $amount,
    'currency'    => 'XOF',
    'error_url'   => $errorUrl,
    'success_url' => $successUrl,
    'client_reference' => $orderNumber,
];

$ch = curl_init('https://api.wave.com/v1/checkout/sessions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $waveApiKey,
        'Content-Type: application/json',
        'Idempotency-Key: ' . $orderNumber . '-' . time(),
    ],
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);

if ($httpCode === 200 && !empty($data['wave_launch_url'])) {
    // Sauvegarder l'ID de session Wave
    $db->prepare("UPDATE orders SET wave_session_id=? WHERE order_number=?")
       ->execute([$data['id'] ?? '', $orderNumber]);
    echo json_encode(['url' => $data['wave_launch_url']]);
} else {
    $msg = $data['message'] ?? ($data['error'] ?? 'Erreur Wave. Réessayez.');
    echo json_encode(['error' => $msg]);
}
