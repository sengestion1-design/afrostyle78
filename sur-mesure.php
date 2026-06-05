<?php
ob_start();
$pageTitle = 'Sur-Mesure';
require_once 'includes/header.php';
$db = getDB();
$products = $db->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.allow_custom_measure=1 AND p.active=1")->fetchAll();
?>

<section style="background:var(--dark); padding:80px 0; position:relative; overflow:hidden;">
    <div style="position:absolute; inset:0; opacity:0.05; background-image:repeating-linear-gradient(45deg,var(--gold) 0,var(--gold) 1px,transparent 1px,transparent 20px),repeating-linear-gradient(-45deg,var(--gold) 0,var(--gold) 1px,transparent 1px,transparent 20px);"></div>
    <div class="container" style="position:relative; z-index:1;">
        <div class="section-eyebrow" style="color:var(--gold);">Service Premium</div>
        <h1 class="section-title" style="color:var(--cream); margin-top:12px;">Confection <em>Sur-Mesure</em></h1>
        <p style="color:rgba(253,246,236,0.55); max-width:560px; font-size:1.25rem; line-height:1.8; margin-top:16px;">
            Chaque pièce est confectionnée à la main par nos artisans selon vos mesures exactes. Délai: 7 à 14 jours ouvrables.
        </p>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-eyebrow">Articles disponibles</div>
            <h2 class="section-title">Choisissez votre <em>création</em></h2>
        </div>
        <div class="products-grid">
            <?php foreach($products as $p): ?>
            <a href="produit.php?slug=<?= $p['slug'] ?>" class="product-card">
                <div class="product-image-wrap">
                    <?php if($p['image']): ?>
                    <img src="<?= UPLOADS_URL . htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    <?php else: ?>
                    <div class="product-placeholder">👗</div>
                    <?php endif; ?>
                    <div class="product-badge">Sur-Mesure</div>
                </div>
                <div class="product-info">
                    <div class="product-category"><?= htmlspecialchars($p['cat_name'] ?? '') ?></div>
                    <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="product-price"><span class="price-current"><?= number_format($p['price'], 0, ',', ' ') ?> <?= CURRENCY ?></span></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- HOW IT WORKS -->
        <div class="steps-section">
            <div class="section-header"><h2 class="section-title" style="color:var(--cream);">Comment ça <em>marche</em> ?</h2></div>
            <div class="steps-grid">
                <?php
                $steps = [
                    ['1','Choisissez','Sélectionnez votre article et activez l\'option Sur-Mesure'],
                    ['2','Mesurez','Entrez vos mesures directement sur la fiche produit'],
                    ['3','Commandez','Finalisez votre commande. Nous vous rappelons pour confirmer'],
                    ['4','Recevez','Votre création arrive chez vous en 7–14 jours'],
                ];
                foreach($steps as $s): ?>
                <div style="text-align:center;">
                    <div style="width:60px; height:60px; background:rgba(200,146,26,0.15); border:1px solid rgba(200,146,26,0.3); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 20px; font-family:'Cormorant Garamond',serif; font-size:1.5rem; color:var(--gold);"><?= $s[0] ?></div>
                    <h3 style="color:var(--cream); font-size:1.1rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; margin-bottom:10px;"><?= $s[1] ?></h3>
                    <p style="color:rgba(253,246,236,0.5); font-size:1.05rem; line-height:1.7;"><?= $s[2] ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
