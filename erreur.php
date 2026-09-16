<?php
/**
 * Page d'erreur generique (400, 403, 500, 503).
 *
 * VOLONTAIREMENT AUTONOME : aucun require, aucun acces base de donnees,
 * aucune police distante. Une erreur 500 signifie souvent que PHP ou MySQL
 * est en panne — charger includes/header.php ici ferait planter la page
 * d'erreur elle-meme et renverrait une page blanche a la place.
 *
 * Le code est lu depuis REDIRECT_STATUS, fourni par Apache via ErrorDocument.
 */

$code = (int)($_SERVER['REDIRECT_STATUS'] ?? 0);

$messages = [
    400 => [
        'titre' => 'Requête invalide',
        'texte' => "Votre navigateur a envoyé une demande que nous n'avons pas pu interpréter.<br>Vérifiez l'adresse saisie ou revenez à l'accueil.",
    ],
    403 => [
        'titre' => 'Accès refusé',
        'texte' => "Vous n'avez pas l'autorisation d'accéder à cette page.<br>Si vous pensez qu'il s'agit d'une erreur, contactez-nous.",
    ],
    500 => [
        'titre' => 'Une erreur est survenue',
        'texte' => "Notre serveur a rencontré un problème inattendu.<br>Nos équipes en sont informées, merci de réessayer dans quelques instants.",
    ],
    503 => [
        'titre' => 'Site en maintenance',
        'texte' => "Le site est momentanément indisponible pour maintenance.<br>Nous revenons très vite.",
    ],
];

if (!isset($messages[$code])) {
    $code = 500;
}
$titre = $messages[$code]['titre'];
$texte = $messages[$code]['texte'];

// Apache a deja positionne le statut, mais un appel direct au fichier, non.
if (!headers_sent()) {
    http_response_code($code);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $code ?> — <?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?> | AfroStyle</title>
<style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    body{
        /* Polices systeme : une erreur 500 ne doit dependre d'aucun appel reseau. */
        font-family:'Syne',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;
        background:#fff;color:#1a1008;min-height:100vh;
        display:flex;align-items:center;justify-content:center;padding:80px 20px;
    }
    .wrap{text-align:center;max-width:500px}
    .code{
        font-family:'Cormorant Garamond',Georgia,'Times New Roman',serif;
        font-size:8rem;font-weight:400;color:#e8dcc8;line-height:1;margin-bottom:16px;
    }
    .rule{width:60px;height:2px;background:#c8921a;margin:0 auto 24px}
    h1{
        font-family:'Cormorant Garamond',Georgia,'Times New Roman',serif;
        font-size:1.8rem;font-weight:400;margin-bottom:12px;
    }
    p{color:#7a6248;font-size:0.95rem;line-height:1.8;margin-bottom:36px}
    .actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}
    a.btn{
        display:inline-block;padding:14px 28px;text-decoration:none;
        font-size:0.85rem;font-weight:700;letter-spacing:0.15em;text-transform:uppercase;
        transition:background 0.3s,color 0.3s;
    }
    .btn-primary{background:#c8921a;color:#fff}
    .btn-primary:hover{background:#a87a12}
    .btn-secondary{background:transparent;color:#1a1008;border:1.5px solid #e8dcc8}
    .btn-secondary:hover{border-color:#c8921a;color:#c8921a}
    .mark{margin-top:48px;color:rgba(200,146,26,0.4);font-size:2rem;letter-spacing:8px}
    @media (max-width:480px){
        .code{font-size:5.5rem}
        a.btn{width:100%}
    }
</style>
</head>
<body>
<div class="wrap">
    <div class="code"><?= $code ?></div>
    <div class="rule"></div>
    <h1><?= htmlspecialchars($titre, ENT_QUOTES, 'UTF-8') ?></h1>
    <p><?= $texte ?></p>
    <div class="actions">
        <a href="/" class="btn btn-primary">Retour à l'accueil</a>
        <a href="/boutique" class="btn btn-secondary">Voir la boutique</a>
    </div>
    <div class="mark">✦ ✦ ✦</div>
</div>
</body>
</html>
