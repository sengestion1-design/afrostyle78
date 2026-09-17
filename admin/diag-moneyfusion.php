<?php
/**
 * Diagnostic MoneyFusion.
 *
 * Rejoue un appel de creation de paiement et affiche la reponse brute de leur
 * API : c'est elle qui dit pourquoi l'initialisation echoue (IP non declaree,
 * devise refusee, champ manquant...). Le message montre au client reste
 * volontairement generique, le detail vit ici.
 *
 * Reserve aux administrateurs connectes. A SUPPRIMER APRES USAGE.
 */
require_once 'includes/auth.php';
header('Content-Type: text/plain; charset=utf-8');

$db = getDB();
$apiUrl = trim((string)$db->query("SELECT setting_value FROM settings WHERE setting_key='moneyfusion_api_url'")->fetchColumn());

echo "=== CONFIGURATION ===\n";
echo "URL d'API : " . ($apiUrl !== '' ? $apiUrl : '(VIDE — rien a tester)') . "\n";
if ($apiUrl === '') { exit; }

$hote = parse_url($apiUrl, PHP_URL_HOST);
echo "Hote      : " . $hote . "\n";
echo "Schema    : " . parse_url($apiUrl, PHP_URL_SCHEME) . "\n";

// Derniere commande non payee, pour rejouer un appel realiste.
$order = $db->query(
    "SELECT o.*, c.first_name, c.last_name, c.phone
     FROM orders o JOIN customers c ON o.customer_id=c.id
     WHERE o.payment_status <> 'paid' ORDER BY o.id DESC LIMIT 1"
)->fetch();

if (!$order) { echo "\nAucune commande impayee a tester.\n"; exit; }

echo "\n=== COMMANDE DE TEST ===\n";
echo "Numero  : " . $order['order_number'] . "\n";
echo "Montant : " . $order['total_amount'] . " (devise du site : EUR)\n";

// Meme conversion que moneyfusion-checkout.php : leur API raisonne en FCFA.
$taux = 655.957;
$montantXof = (int)round((float)$order['total_amount'] * $taux);
echo "Converti : " . $montantXof . " FCFA (taux " . $taux . ")\n";

$stmtItems = $db->prepare("SELECT product_name, quantity, unit_price FROM order_items WHERE order_id=?");
$stmtItems->execute([$order['id']]);
$articles = [];
foreach ($stmtItems->fetchAll() as $it) {
    $articles[] = [
        'name'     => (string)$it['product_name'],
        'price'    => (string)(int)round((float)$it['unit_price'] * $taux),
        'quantity' => (int)$it['quantity'],
    ];
}
if (!$articles) {
    $articles[] = ['name' => 'Commande', 'price' => (string)$montantXof, 'quantity' => 1];
}

$payload = [
    'totalPrice'    => (string)$montantXof,
    'article'       => $articles,
    'numeroSend'    => preg_replace('/\D+/', '', (string)($order['phone'] ?? '')),
    'nomclient'     => trim($order['first_name'] . ' ' . $order['last_name']),
    'personal_Info' => [['userId' => (int)$order['customer_id'], 'orderId' => (int)$order['id']]],
    'return_url'    => SITE_URL . '/moneyfusion-success.php?order=' . urlencode($order['order_number']),
    'webhook_url'   => SITE_URL . '/moneyfusion-webhook.php',
];

echo "\n=== DONNEES ENVOYEES ===\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";

$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
]);
$reponse   = curl_exec($ch);
$codeHttp  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$erreurNet = curl_error($ch);
curl_close($ch);

echo "\n=== REPONSE DE MONEYFUSION ===\n";
echo "Code HTTP : " . $codeHttp . "\n";
if ($erreurNet !== '') { echo "Erreur reseau : " . $erreurNet . "\n"; }
echo "Corps de la reponse :\n";
echo ($reponse === false ? '(aucune reponse)' : $reponse) . "\n";

$data = json_decode((string)$reponse, true);
echo "\n=== INTERPRETATION ===\n";
if (!empty($data['url']) && !empty($data['token'])) {
    echo "SUCCES : MoneyFusion a bien cree le paiement.\n";
    echo "Si le site affiche malgre tout une erreur, le probleme est ailleurs.\n";
} else {
    echo "ECHEC : aucune URL de paiement renvoyee.\n";
    echo "Le message ci-dessus, envoye par MoneyFusion, donne la raison exacte.\n";
    echo "Causes frequentes : adresse IP du serveur non declaree dans leur\n";
    echo "tableau de bord, devise non acceptee (leur compte est en FCFA alors\n";
    echo "que le site vend en EUR), ou application pas encore validee.\n";
}
