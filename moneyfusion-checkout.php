<?php
/**
 * Initialise un paiement MoneyFusion et renvoie l'URL de redirection.
 *
 * Protocole (docs.moneyfusion.net) : un POST JSON sur l'URL d'API propre au
 * marchand, qui renvoie un token et une URL de paiement. La confirmation
 * arrive ensuite par webhook, verifiee aupres de MoneyFusion.
 */
require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: application/json');

$db          = getDB();
$allSettings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);

// URL d'API propre au marchand, generee depuis le tableau de bord MoneyFusion.
$apiUrl = trim($allSettings['moneyfusion_api_url'] ?? getenv('MONEYFUSION_API_URL') ?: '');

if ($apiUrl === '') {
    echo json_encode(['error' => 'MoneyFusion non configuré. Ajoutez votre URL d\'API dans les paramètres.']);
    exit;
}
// L'URL vient des reglages : on refuse tout ce qui n'est pas une adresse
// MoneyFusion en HTTPS, pour qu'un reglage errone n'envoie pas la commande
// vers un serveur tiers.
//
// Le domaine est compare sur ses composants, jamais par simple recherche de
// texte : un motif du type «...moneyfusion.net» aurait accepte
// «evilmoneyfusion.net», un domaine qu'un tiers peut enregistrer pour
// intercepter les commandes. Seul moneyfusion.net et ses sous-domaines passent.
$hote = parse_url($apiUrl, PHP_URL_HOST);
$schema = parse_url($apiUrl, PHP_URL_SCHEME);
$hoteOk = is_string($hote)
    && ($hote === 'moneyfusion.net' || str_ends_with(strtolower($hote), '.moneyfusion.net'));
if (strtolower((string)$schema) !== 'https' || !$hoteOk) {
    error_log('[MONEYFUSION] URL d\'API refusee : ' . $apiUrl);
    echo json_encode(['error' => 'URL d\'API MoneyFusion invalide. Vérifiez les paramètres.']);
    exit;
}

$orderNumber  = trim($_POST['order_number'] ?? '');
$confirmToken = trim($_POST['confirm_token'] ?? $_POST['t'] ?? '');

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
    echo json_encode(['error' => 'Cette commande est déjà payée.']);
    exit;
}

// Le montant vient de la base, jamais du POST : un client ne doit pas pouvoir
// choisir ce qu'il paie.
$montant = (float)$order['total_amount'];
if ($montant <= 0) {
    echo json_encode(['error' => 'Montant invalide.']);
    exit;
}

// MoneyFusion raisonne en FCFA : un montant en euros y etait lu comme des
// francs (145 EUR devenaient 145 FCFA, refuses car sous leur minimum de 200).
// Taux fixe de la zone franc, deja utilise par PayDunya et Wave dans ce projet.
const MONEYFUSION_TAUX_EUR_XOF = 655.957;
$montantXof = (int)round($montant * MONEYFUSION_TAUX_EUR_XOF);
if ($montantXof < 200) {
    echo json_encode(['error' => 'Le montant est trop faible pour ce moyen de paiement. Choisissez un autre moyen ci-dessous.']);
    exit;
}

// Articles de la commande, pour que le client les retrouve chez MoneyFusion.
$stmtItems = $db->prepare("SELECT product_name, quantity, unit_price FROM order_items WHERE order_id=?");
$stmtItems->execute([$order['id']]);
$articles = [];
foreach ($stmtItems->fetchAll() as $it) {
    $articles[] = [
        'name'     => (string)$it['product_name'],
        // Prix converti comme le total : MoneyFusion attend des FCFA.
        'price'    => (string)(int)round((float)$it['unit_price'] * MONEYFUSION_TAUX_EUR_XOF),
        'quantity' => (int)$it['quantity'],
    ];
}
if (!$articles) {
    $articles[] = ['name' => 'Commande ' . $orderNumber, 'price' => (string)$montantXof, 'quantity' => 1];
}

// Le numero sert a pre-remplir le paiement mobile : on ne garde que les chiffres.
$numeroSend = preg_replace('/\D+/', '', (string)($order['phone'] ?? ''));

$payload = [
    'totalPrice'    => (string)$montantXof,
    'article'       => $articles,
    'numeroSend'    => $numeroSend,
    'nomclient'     => trim($order['first_name'] . ' ' . $order['last_name']),
    'personal_Info' => [[
        'userId'  => (int)$order['customer_id'],
        'orderId' => (int)$order['id'],
    ]],
    'return_url'    => SITE_URL . '/moneyfusion-success.php?order=' . urlencode($orderNumber),
    'webhook_url'   => SITE_URL . '/moneyfusion-webhook.php',
];

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);
$reponse = curl_exec($ch);
$codeHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$erreurCurl = curl_error($ch);
curl_close($ch);

if ($reponse === false) {
    error_log('[MONEYFUSION] Erreur reseau : ' . $erreurCurl);
    echo json_encode(['error' => 'Le service de paiement est injoignable. Réessayez dans un instant.']);
    exit;
}

$data = json_decode($reponse, true);
if (empty($data['url']) || empty($data['token'])) {
    // Le detail technique reste dans les logs, pas devant le client.
    error_log('[MONEYFUSION] Creation echouee HTTP ' . $codeHttp . ' reponse=' . $reponse);
    echo json_encode(['error' => 'Le paiement n\'a pas pu être initialisé. Choisissez un autre moyen de paiement ci-dessous.']);
    exit;
}

// Le token permettra de verifier le paiement au retour du client et a la
// reception du webhook.
$db->prepare("UPDATE orders SET payment_method='moneyfusion', payment_token=? WHERE id=?")
   ->execute([$data['token'], $order['id']]);

echo json_encode(['url' => $data['url']]);
