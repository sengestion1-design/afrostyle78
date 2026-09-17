<?php
/**
 * Retour du client apres un paiement MoneyFusion.
 *
 * La confirmation officielle passe par le webhook. Cette page verifie tout de
 * meme le statut aupres de MoneyFusion : le client peut revenir avant que la
 * notification ne soit arrivee, et verrait sinon sa commande encore impayee.
 */
require_once 'config/config.php';
require_once 'config/database.php';

$orderNumber = trim($_GET['order'] ?? '');

if ($orderNumber !== '') {
    $db   = getDB();
    $stmt = $db->prepare("SELECT id, payment_status, payment_token, total_amount FROM orders WHERE order_number = ?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();

    if ($order && $order['payment_status'] !== 'paid' && !empty($order['payment_token'])) {
        $ch = curl_init('https://www.pay.moneyfusion.net/paiementNotif/' . rawurlencode($order['payment_token']));
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $reponse = curl_exec($ch);
        curl_close($ch);

        $infos = json_decode((string)$reponse, true)['data'] ?? [];
        $montantRecu = (float)($infos['Montant'] ?? 0);

        // Meme exigence que le webhook : statut payé ET montant conforme.
        if (strtolower((string)($infos['statut'] ?? '')) === 'paid'
            && abs($montantRecu - (float)$order['total_amount']) <= 0.01) {
            $db->prepare("UPDATE orders SET payment_status='paid', status='confirmed', payment_method='moneyfusion' WHERE id=?")
               ->execute([$order['id']]);
            // Le panier n'est vide qu'au paiement effectif.
            $_SESSION['cart'] = [];
        }
    }
}

header('Location: ' . SITE_URL . '/confirmation.php?order=' . urlencode($orderNumber) . '&payment=success');
exit;
