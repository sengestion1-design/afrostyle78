<?php
/**
 * Affiche l'IP sortante du serveur — celle qu'un service tiers voit quand le
 * site l'appelle. A declarer chez MoneyFusion, qui bloque les IP non listees.
 *
 * Reserve aux administrateurs connectes. A SUPPRIMER APRES USAGE.
 */
require_once 'admin/includes/auth.php';
header('Content-Type: text/plain; charset=utf-8');

echo "=== IP SORTANTE DU SERVEUR ===\n";
echo "C'est cette adresse qu'il faut declarer chez MoneyFusion.\n\n";

foreach (['https://api.ipify.org', 'https://ifconfig.me/ip', 'https://icanhazip.com'] as $service) {
    $ch = curl_init($service);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $ip = trim((string)curl_exec($ch));
    $err = curl_error($ch);
    curl_close($ch);
    printf("  %-28s %s\n", parse_url($service, PHP_URL_HOST), $ip !== '' ? $ip : '(echec : ' . $err . ')');
}

echo "\n=== POUR COMPARAISON ===\n";
echo "  IP publique du site (DNS)  : 217.160.0.239\n";
echo "  SERVER_ADDR                : " . ($_SERVER['SERVER_ADDR'] ?? 'inconnu') . "\n";
echo "\nSi les IP sortantes different de l'IP publique, declarez les DEUX\n";
echo "chez MoneyFusion, ou demandez-leur la marche a suivre.\n";
