<?php
/**
 * Notification de paiement MoneyFusion.
 *
 * SECURITE : le webhook n'est pas signe cryptographiquement. Son contenu ne
 * fait donc foi de rien — n'importe qui connaissant l'URL pourrait annoncer un
 * faux paiement. On n'en retient que le token, puis on interroge MoneyFusion
 * directement pour connaitre le veritable statut. Une commande n'est marquee
 * payee que sur la reponse de leur serveur.
 */
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/mailer.php';

// Reponse immediate : MoneyFusion n'attend qu'un accuse de reception.
http_response_code(200);

$corps = file_get_contents('php://input');
$recu  = json_decode($corps, true);

// Le token peut arriver sous plusieurs formes selon la notification.
$token = $recu['tokenPay']
      ?? $recu['data']['tokenPay']
      ?? $recu['token']
      ?? '';
$token = is_string($token) ? trim($token) : '';

if ($token === '') {
    error_log('[MONEYFUSION] Webhook sans token : ' . substr($corps, 0, 500));
    exit;
}

// Verification aupres de MoneyFusion : c'est cette reponse qui fait foi.
$ch = curl_init('https://www.pay.moneyfusion.net/paiementNotif/' . rawurlencode($token));
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);
$reponse = curl_exec($ch);
$erreur  = curl_error($ch);
curl_close($ch);

if ($reponse === false) {
    error_log('[MONEYFUSION] Verification injoignable pour ' . $token . ' : ' . $erreur);
    exit;
}

$verif = json_decode($reponse, true);
$infos = $verif['data'] ?? [];

if (strtolower((string)($infos['statut'] ?? '')) !== 'paid') {
    // Paiement en cours, echoue ou annule : on ne touche pas a la commande.
    error_log('[MONEYFUSION] Token ' . $token . ' statut=' . ($infos['statut'] ?? 'inconnu'));
    exit;
}

$db = getDB();

// La commande est retrouvee par le token enregistre a la creation du paiement.
// personal_Info sert de repli si le token n'a pas ete stocke.
$stmt = $db->prepare("SELECT o.*, c.email, c.first_name, c.last_name, c.phone FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.payment_token = ?");
$stmt->execute([$token]);
$order = $stmt->fetch();

if (!$order) {
    $orderId = (int)($infos['personal_Info'][0]['orderId'] ?? 0);
    if ($orderId > 0) {
        $stmt = $db->prepare("SELECT o.*, c.email, c.first_name, c.last_name, c.phone FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
    }
}

if (!$order) {
    error_log('[MONEYFUSION] Commande introuvable pour le token ' . $token);
    exit;
}

// Deja traitee : MoneyFusion peut renvoyer la meme notification plusieurs fois.
if ($order['payment_status'] === 'paid') {
    exit;
}

// Le montant annonce doit correspondre a la commande. La comparaison se fait
// en FCFA : c'est la devise de MoneyFusion, et celle dans laquelle le paiement
// a ete cree. Comparer des euros a des francs rejetterait tout paiement valide.
// Taux fixe de la zone franc, identique a celui de moneyfusion-checkout.php.
$montantRecu = (float)($infos['Montant'] ?? 0);
$montantDuXof = round((float)$order['total_amount'] * 655.957);
// Tolerance d'un franc : les arrondis peuvent differer d'une unite.
if (abs($montantRecu - $montantDuXof) > 1) {
    error_log(sprintf('[MONEYFUSION] Montant different pour %s : recu %.2f FCFA, attendu %.0f FCFA',
        $order['order_number'], $montantRecu, $montantDuXof));
    exit;
}

try {
    $db->beginTransaction();
    $db->prepare("UPDATE orders SET payment_status='paid', status='confirmed', payment_method='moneyfusion' WHERE id=?")
       ->execute([$order['id']]);
    $db->prepare("INSERT INTO delivery_tracking (order_id, status, note) VALUES (?,?,?)")
       ->execute([$order['id'], 'confirmed',
                  'Paiement MoneyFusion confirmé (' . ($infos['moyen'] ?? 'mobile') . ').']);
    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    error_log('[MONEYFUSION] Echec enregistrement paiement ' . $order['order_number'] . ' : ' . $e->getMessage());
    exit;
}

// Emails : un echec d'envoi ne doit pas remettre en cause le paiement.
$stmtItems = $db->prepare("SELECT product_name, size, quantity, unit_price FROM order_items WHERE order_id=?");
$stmtItems->execute([$order['id']]);
$lignes = $stmtItems->fetchAll();

$pourEmail = [
    'order_number'     => $order['order_number'],
    'total_amount'     => $order['total_amount'],
    'delivery_fee'     => $order['delivery_fee'],
    'delivery_address' => $order['delivery_address'],
    'delivery_city'    => $order['delivery_city'],
    'payment_method'   => 'moneyfusion',
    'sender_phone'     => (string)($infos['numeroSend'] ?? ''),
];

@emailOrderConfirmation($order['email'], $order['first_name'], $pourEmail, $lignes);
@emailAdminNewOrder($pourEmail, $lignes, [
    'first_name' => $order['first_name'],
    'last_name'  => $order['last_name'],
    'email'      => $order['email'],
    'phone'      => $order['phone'],
], true);
