<?php
ob_start();
$pageTitle = 'Panier';
require_once 'includes/header.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$cart = $_SESSION['cart'] ?? [];

// Remove item — vérification CSRF
if (isset($_GET['remove'])) {
    if (!empty($_GET['csrf']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf'])) {
        $key = urldecode($_GET['remove']);
        unset($_SESSION['cart'][$key]);
        $cart = $_SESSION['cart'];
    }
    header('Location: panier.php');
    exit;
}
// Update qty
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    foreach ($_POST['qty'] as $key => $qty) {
        $qty = max(1, (int)$qty);
        if (isset($_SESSION['cart'][$key])) $_SESSION['cart'][$key]['quantity'] = $qty;
    }
    $cart = $_SESSION['cart'];
    header('Location: panier.php?updated=1');
    exit;
}

$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}
$total = $subtotal;
?>

<div class="container">
    <div class="breadcrumb">
        <a href="<?= SITE_URL ?>">Accueil</a><span>›</span>
        <span class="current">Panier</span>
    </div>
    <div style="margin-bottom:40px;">
        <h1 style="font-family:'Cormorant Garamond',serif; font-size:2.4rem; font-weight:400;">Votre <em style="color:var(--gold);">panier</em></h1>
    </div>

    <?php if(isset($_GET['added'])): ?>
    <div class="alert alert-success">✓ Article ajouté au panier avec succès !</div>
    <?php endif; ?>
    <?php if(isset($_GET['updated'])): ?>
    <div class="alert alert-info">Panier mis à jour.</div>
    <?php endif; ?>

    <?php if(empty($cart)): ?>
    <div class="empty-state" style="padding:120px 40px;">
        <div class="empty-state-icon">🛍️</div>
        <h3>Votre panier est vide</h3>
        <p>Découvrez nos créations et ajoutez vos pièces préférées.</p>
        <a href="boutique.php" class="btn btn-primary" style="margin-top:32px;">Explorer la boutique</a>
    </div>

    <?php else: ?>
    <div class="checkout-grid">
        <!-- CART ITEMS -->
        <div>
            <form method="POST" action="">
                <input type="hidden" name="update_cart" value="1">

                <!-- Desktop : tableau -->
                <table class="cart-table cart-table-desktop">
                    <thead>
                        <tr>
                            <th colspan="2">Article</th>
                            <th>Taille</th>
                            <th>Prix</th>
                            <th>Quantité</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($cart as $key => $item): ?>
                        <tr>
                            <td style="width:80px; padding-right:0;">
                                <?php if($item['image']): ?>
                                <img src="<?= UPLOADS_URL . htmlspecialchars($item['image']) ?>" alt="" class="cart-item-img">
                                <?php else: ?>
                                <div class="cart-item-img" style="display:flex;align-items:center;justify-content:center;font-size:2rem;">👗</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="cart-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                <?php if($item['is_custom'] && $item['measurements']): ?>
                                <div class="cart-item-size">Sur-mesure — <a href="#" onclick="toggleMeasures('m_<?= md5($key) ?>')">voir mesures</a></div>
                                <div id="m_<?= md5($key) ?>" style="display:none; margin-top:8px; font-size:0.72rem; color:var(--text-muted); background:var(--cream-2); padding:10px;">
                                    <?php foreach($item['measurements'] as $mk => $mv): if($mv): ?>
                                    <div><?= str_replace('_',' ', ucfirst($mk)) ?>: <?= htmlspecialchars($mv) ?> cm</div>
                                    <?php endif; endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="cart-item-size"><?= htmlspecialchars($item['size']) ?></span>
                                <?php if (!empty($item['color'])): ?>
                                <span style="display:inline-flex;align-items:center;gap:5px;margin-left:6px;">
                                    <span style="width:14px;height:14px;border-radius:50%;background:<?= htmlspecialchars($item['color_hex']) ?>;border:1.5px solid rgba(0,0,0,0.15);display:inline-block;"></span>
                                    <span style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($item['color']) ?></span>
                                </span>
                                <?php endif; ?>
                            </td>
                            <td><?= number_format($item['price'], 0, ',', ' ') ?> <?= CURRENCY ?></td>
                            <td>
                                <div class="qty-selector" style="width:fit-content;">
                                    <button type="button" class="qty-btn" onclick="adjustQty('<?= htmlspecialchars(addslashes($key)) ?>', -1)">−</button>
                                    <input type="number" name="qty[<?= htmlspecialchars($key) ?>]" class="qty-input cart-qty-input" id="qty_<?= md5($key) ?>" value="<?= $item['quantity'] ?>" min="1" max="99" style="width:50px;">
                                    <button type="button" class="qty-btn" onclick="adjustQty('<?= htmlspecialchars(addslashes($key)) ?>', 1)">+</button>
                                </div>
                            </td>
                            <td style="font-weight:700;"><?= number_format($item['price'] * $item['quantity'], 0, ',', ' ') ?> <?= CURRENCY ?></td>
                            <td>
                                <a href="panier.php?remove=<?= urlencode($key) ?>&csrf=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" class="remove-btn" title="Supprimer" onclick="return confirm('Supprimer cet article ?')">✕</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Mobile : cartes -->
                <div class="cart-cards-mobile">
                    <?php foreach($cart as $key => $item): ?>
                    <div style="display:flex; gap:12px; padding:16px 0; border-bottom:1px solid rgba(0,0,0,0.07); align-items:flex-start;">
                        <div style="flex-shrink:0; width:72px; height:88px; background:var(--cream-2); overflow:hidden;">
                            <?php if($item['image']): ?>
                            <img src="<?= UPLOADS_URL . htmlspecialchars($item['image']) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:2rem;">👗</div>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-family:'Cormorant Garamond',serif; font-size:1rem; font-weight:600; margin-bottom:4px;"><?= htmlspecialchars($item['name']) ?></div>
                            <div style="font-size:0.82rem; color:var(--text-muted); margin-bottom:8px;">
                                Taille : <?= htmlspecialchars($item['size']) ?>
                                <?php if (!empty($item['color'])): ?> · <?= htmlspecialchars($item['color']) ?><?php endif; ?>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap;">
                                <div class="qty-selector">
                                    <button type="button" class="qty-btn" onclick="adjustQty('<?= htmlspecialchars(addslashes($key)) ?>', -1)">−</button>
                                    <input type="number" name="qty[<?= htmlspecialchars($key) ?>]" class="qty-input cart-qty-input" value="<?= $item['quantity'] ?>" min="1" max="99" style="width:44px;">
                                    <button type="button" class="qty-btn" onclick="adjustQty('<?= htmlspecialchars(addslashes($key)) ?>', 1)">+</button>
                                </div>
                                <div style="font-weight:700; font-size:1rem;"><?= number_format($item['price'] * $item['quantity'], 0, ',', ' ') ?> <?= CURRENCY ?></div>
                            </div>
                        </div>
                        <a href="panier.php?remove=<?= urlencode($key) ?>&csrf=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" class="remove-btn" title="Supprimer" onclick="return confirm('Supprimer ?')" style="flex-shrink:0;">✕</a>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="display:flex; gap:12px; margin-top:20px; flex-wrap:wrap;">
                    <button type="submit" class="btn btn-dark btn-sm">Mettre à jour le panier</button>
                    <a href="boutique.php" class="btn btn-outline btn-sm" style="border-color:var(--cream-2); color:var(--dark);">← Continuer les achats</a>
                </div>
            </form>
        </div>

        <!-- SUMMARY -->
        <div>
            <div class="cart-summary">
                <div style="font-size:0.72rem; font-weight:700; letter-spacing:0.15em; text-transform:uppercase; color:var(--gold); margin-bottom:24px; padding-bottom:12px; border-bottom:1px solid rgba(200,146,26,0.2);">Récapitulatif</div>
                <div class="summary-row">
                    <span>Sous-total</span>
                    <span><?= number_format($subtotal, 0, ',', ' ') ?> <?= CURRENCY ?></span>
                </div>
                <div class="summary-row">
                    <span>Livraison</span>
                    <span style="color:#38a169; font-size:0.85rem;">Calculée à l'étape suivante</span>
                </div>
                <div class="summary-row summary-total" style="font-size:1.15rem; border-bottom:none; padding-top:16px; margin-top:8px; border-top:2px solid var(--dark);">
                    <span>Sous-total</span>
                    <span><?= number_format($subtotal, 0, ',', ' ') ?> <?= CURRENCY ?></span>
                </div>
                <a href="commande.php" class="btn btn-primary btn-lg btn-full" style="margin-top:24px;">Commander maintenant →</a>
                <div style="margin-top:16px; font-size:0.72rem; color:var(--text-muted); text-align:center; line-height:1.7;">
                    Paiement sécurisé · Livraison confirmée par téléphone
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function adjustQty(key, delta) {
    const id = 'qty_' + btoa(key).replace(/=/g,'').substring(0,8);
    const inputs = document.querySelectorAll('.cart-qty-input');
    inputs.forEach(inp => {
        const nameKey = inp.name.replace('qty[','').replace(']','');
        if (nameKey === key) {
            let v = parseInt(inp.value) || 1;
            inp.value = Math.max(1, v + delta);
        }
    });
}
function toggleMeasures(id) {
    const el = document.getElementById(id);
    if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>

<?php require_once 'includes/footer.php'; ?>
