<?php
define('SITE_NAME', 'AfroStyle');
// Sous-dossier d'installation : vide en production (site a la racine du domaine),
// "/afrostyle" en local sous XAMPP.
//
// Il est deduit de SCRIPT_NAME — l'URL du script courant, cote client — et non
// de DOCUMENT_ROOT : chez IONOS, DOCUMENT_ROOT pointe ailleurs que le dossier du
// projet, et la comparaison des deux chemins disque produisait une URL du type
// afrostyle78.com/homepages/31/.../htdocs/afrostyle (CSS et images introuvables).
//
// On retire de l'URL du script la position du fichier par rapport a la racine du
// projet : ce qui reste est le prefixe web, vide a la racine d'un domaine.
$scriptUrl  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$scriptFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
$projetDir  = str_replace('\\', '/', dirname(__DIR__));
$siteBase   = '';
if ($scriptUrl !== '' && $scriptFile !== '' && str_starts_with($scriptFile, $projetDir)) {
    // Chemin du script relatif a la racine du projet, ex. "/admin/produits.php".
    $relatif = substr($scriptFile, strlen($projetDir));
    if ($relatif !== '' && str_ends_with($scriptUrl, $relatif)) {
        $siteBase = trim(substr($scriptUrl, 0, -strlen($relatif)), '/');
    }
}
define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . ($_SERVER['HTTP_HOST'] ?? 'afrostyle78.com')
    . ($siteBase !== '' ? '/' . $siteBase : ''));
unset($scriptUrl, $scriptFile, $projetDir, $siteBase, $relatif);
define('ADMIN_URL', SITE_URL . '/admin');
define('UPLOADS_DIR', __DIR__ . '/../uploads/products/');
define('UPLOADS_URL', SITE_URL . '/uploads/products/');
define('CURRENCY', '€');
define('SESSION_NAME', 'afrostyle_session');
require_once __DIR__ . '/secrets.php';

session_name(SESSION_NAME);
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// --- Protection CSRF -------------------------------------------------------
// Un seul jeton par session, partage par tous les formulaires.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/** Champ cache a placer dans chaque formulaire POST. */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="'
         . htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifie le jeton du POST courant. hash_equals : comparaison a temps constant.
 * En cas d'echec, on arrete immediatement — la requete n'est pas legitime.
 */
function csrfCheck(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        http_response_code(403);
        exit('Requête invalide (jeton de sécurité absent ou expiré). Rechargez la page et réessayez.');
    }
}

if (!headers_sent()) {
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
