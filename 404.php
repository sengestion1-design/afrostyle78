<?php
http_response_code(404);
$pageTitle = 'Page introuvable';
require_once 'includes/header.php';
?>

<section style="background:#fff;min-height:60vh;display:flex;align-items:center;justify-content:center;padding:80px 20px;">
    <div style="text-align:center;max-width:500px;">
        <div style="font-family:'Cormorant Garamond',serif;font-size:8rem;font-weight:400;color:#e8dcc8;line-height:1;margin-bottom:16px;">404</div>
        <div style="width:60px;height:2px;background:#c8921a;margin:0 auto 24px;"></div>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:1.8rem;font-weight:400;color:#1a1008;margin-bottom:12px;">Page introuvable</h1>
        <p style="color:#7a6248;font-size:0.95rem;line-height:1.8;margin-bottom:36px;">
            La page que vous recherchez n'existe pas ou a été déplacée.<br>
            Découvrez nos collections ou retournez à l'accueil.
        </p>
        <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
            <a href="<?= SITE_URL ?>/" class="btn btn-primary">Retour à l'accueil</a>
            <a href="<?= SITE_URL ?>/boutique" class="btn btn-secondary">Voir la boutique</a>
        </div>
        <div style="margin-top:48px;color:rgba(200,146,26,0.4);font-size:2rem;letter-spacing:8px;">✦ ✦ ✦</div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
