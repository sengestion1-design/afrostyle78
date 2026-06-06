<?php
ob_start();
require_once 'config/config.php';
require_once 'config/database.php';
$db = getDB();

$slug = $_GET['slug'] ?? '';
if (!$slug) { header('Location: boutique.php'); exit; }

$stmt = $db->prepare("SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.slug = ? AND p.active = 1");
$stmt->execute([$slug]);
$product = $stmt->fetch();
if (!$product) { header('Location: boutique.php'); exit; }

$pageTitle = $product['name'];
$sizes  = json_decode($product['available_sizes']  ?? '[]', true);
$colors = json_decode($product['available_colors'] ?? '[]', true);
$images = json_decode($product['images'] ?? '[]', true);
$price  = $product['promo_price'] ?: $product['price'];

require_once 'includes/header.php';

// Cart action
$cartMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $selectedSize     = trim($_POST['selected_size'] ?? '');
    $selectedColor    = trim($_POST['selected_color'] ?? '');
    $selectedColorHex = trim($_POST['selected_color_hex'] ?? '');
    $qty      = max(1, (int)($_POST['quantity'] ?? 1));
    $isCustom = isset($_POST['is_custom_measure']) && $_POST['is_custom_measure'] == '1';

    if (!$selectedSize && !$isCustom) {
        $cartMsg = '<div class="alert alert-error">Veuillez choisir une taille.</div>';
    } elseif (!empty($colors) && !$selectedColor) {
        $cartMsg = '<div class="alert alert-error">Veuillez choisir une couleur.</div>';
    } else {
        $cartKey = $product['id'] . '_' . ($selectedSize ?: 'SUR-MESURE') . ($selectedColor ? '_' . preg_replace('/[^a-z0-9]/', '', strtolower($selectedColor)) : '');
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

        $measurements = null;
        if ($isCustom) {
            $measurements = [
                'tour_poitrine' => $_POST['tour_poitrine'] ?? '',
                'tour_taille' => $_POST['tour_taille'] ?? '',
                'tour_hanches' => $_POST['tour_hanches'] ?? '',
                'longueur_epaule' => $_POST['longueur_epaule'] ?? '',
                'longueur_totale' => $_POST['longueur_totale'] ?? '',
                'longueur_manche' => $_POST['longueur_manche'] ?? '',
                'tour_cou' => $_POST['tour_cou'] ?? '',
                'tour_bras' => $_POST['tour_bras'] ?? '',
                'notes_mesures' => $_POST['notes_mesures'] ?? '',
            ];
        }

        if (isset($_SESSION['cart'][$cartKey])) {
            $_SESSION['cart'][$cartKey]['quantity'] += $qty;
        } else {
            $_SESSION['cart'][$cartKey] = [
                'product_id' => $product['id'],
                'name'       => $product['name'],
                'price'      => $price,
                'image'      => $product['image'],
                'size'       => $selectedSize ?: 'SUR-MESURE',
                'color'      => $selectedColor,
                'color_hex'  => $selectedColorHex,
                'quantity'   => $qty,
                'is_custom'  => $isCustom,
                'measurements' => $measurements,
            ];
        }
        ob_end_clean();
        header('Location: ' . SITE_URL . '/panier.php?added=1');
        exit;
    }
}

// Related products
$related = $db->prepare("SELECT * FROM products WHERE category_id = ? AND id != ? AND active = 1 LIMIT 4");
$related->execute([$product['category_id'], $product['id']]);
$relatedProducts = $related->fetchAll();
?>

<div class="container">
    <div class="breadcrumb">
        <a href="<?= SITE_URL ?>">Accueil</a><span>›</span>
        <a href="boutique.php">Boutique</a><span>›</span>
        <?php if($product['cat_name']): ?>
        <a href="boutique.php?cat=<?= $product['cat_slug'] ?>"><?= htmlspecialchars($product['cat_name']) ?></a><span>›</span>
        <?php endif; ?>
        <span class="current"><?= htmlspecialchars($product['name']) ?></span>
    </div>
</div>

<section class="product-detail">
    <div class="container">
        <?= $cartMsg ?>
        <div class="product-detail-grid">
            <!-- GALLERY -->
            <div class="product-gallery">
                <div class="gallery-main">
                    <?php if($product['image']): ?>
                    <img src="<?= UPLOADS_URL . htmlspecialchars($product['image']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" id="mainImage">
                    <?php else: ?>
                    <div class="product-placeholder" style="aspect-ratio:3/4; font-size:8rem;">👗</div>
                    <?php endif; ?>
                </div>
                <?php
                $allPhotos = [];
                if ($product['image']) $allPhotos[] = $product['image'];
                foreach ($images as $img) { if ($img !== $product['image']) $allPhotos[] = $img; }
                ?>
                <?php if(count($allPhotos) > 1): ?>
                <div class="gallery-thumbs">
                    <?php foreach($allPhotos as $i => $img): ?>
                    <div class="gallery-thumb <?= $i===0?'active':'' ?>" data-src="<?= UPLOADS_URL . htmlspecialchars($img) ?>"
                         onclick="switchMainImage(this)">
                        <img src="<?= UPLOADS_URL . htmlspecialchars($img) ?>" alt="" loading="lazy">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- INFO -->
            <div class="product-detail-info">
                <div class="product-detail-category"><?= htmlspecialchars($product['cat_name'] ?? 'Collection') ?></div>
                <h1 class="product-detail-name"><?= htmlspecialchars($product['name']) ?></h1>
                <div class="product-detail-price">
                    <?php if($product['promo_price']): ?>
                    <span class="price-promo"><?= number_format($product['promo_price'], 0, ',', ' ') ?> <?= CURRENCY ?></span>
                    <span class="price-old" style="font-size:1rem; font-weight:400; margin-left:10px;"><?= number_format($product['price'], 0, ',', ' ') ?></span>
                    <?php else: ?>
                    <?= number_format($product['price'], 0, ',', ' ') ?> <?= CURRENCY ?>
                    <?php endif; ?>
                </div>

                <form method="POST" action="">
                    <input type="hidden" name="add_to_cart" value="1">
                    <input type="hidden" name="selected_size" id="selected_size" value="">
                    <input type="hidden" name="selected_color" id="selected_color" value="">
                    <input type="hidden" name="selected_color_hex" id="selected_color_hex" value="">
                    <input type="hidden" name="is_custom_measure" id="is_custom_measure" value="0">

                    <!-- COLOR SELECTOR -->
                    <?php if (!empty($colors)): ?>
                    <div class="size-section" style="margin-bottom:20px;">
                        <div class="option-label">Couleur <span id="color-label-text">— Choisissez une couleur</span></div>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:10px;">
                            <?php foreach ($colors as $col): ?>
                            <button type="button" class="color-swatch-btn"
                                    data-color="<?= htmlspecialchars($col['name']) ?>"
                                    data-hex="<?= htmlspecialchars($col['hex']) ?>"
                                    title="<?= htmlspecialchars($col['name']) ?>"
                                    style="width:36px;height:36px;border-radius:50%;background:<?= htmlspecialchars($col['hex']) ?>;border:2px solid transparent;cursor:pointer;transition:all 0.2s;flex-shrink:0;"
                                    onclick="selectColor(this)">
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- SIZE SELECTOR -->
                    <?php if(!empty($sizes)): ?>
                    <div class="size-section">
                        <div class="option-label">Taille <span>— Choisissez votre taille</span></div>
                        <div class="size-grid">
                            <?php foreach($sizes as $sz): ?>
                            <button type="button" class="size-btn" data-size="<?= htmlspecialchars($sz) ?>"><?= htmlspecialchars($sz) ?></button>
                            <?php endforeach; ?>
                        </div>
                        <div style="margin-top:10px; font-size:0.72rem; color:var(--text-muted);">
                            <a href="#" style="color:var(--gold);">Guide des tailles →</a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- SUR-MESURE -->
                    <?php if($product['allow_custom_measure']): ?>
                    <button type="button" class="custom-measure-toggle" id="measureToggle">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 12h20M2 12l4-4M2 12l4 4M22 12l-4-4M22 12l-4 4"/></svg>
                        Commander sur-mesure
                        <span class="measure-badge">+Sur-Mesure</span>
                    </button>
                    <div class="measure-form" id="measureForm">
                        <p style="font-size:0.78rem; color:var(--text-muted); margin-bottom:20px; line-height:1.7;">
                            Entrez vos mesures en centimètres pour une confection parfaite. Laissez vide les mesures non pertinentes.
                        </p>
                        <div class="measure-grid">
                            <div class="measure-field">
                                <label>Tour de poitrine <span class="measure-unit">(cm)</span></label>
                                <input type="number" name="tour_poitrine" step="0.5" min="60" max="160" placeholder="ex: 90">
                            </div>
                            <div class="measure-field">
                                <label>Tour de taille <span class="measure-unit">(cm)</span></label>
                                <input type="number" name="tour_taille" step="0.5" min="50" max="150" placeholder="ex: 70">
                            </div>
                            <div class="measure-field">
                                <label>Tour de hanches <span class="measure-unit">(cm)</span></label>
                                <input type="number" name="tour_hanches" step="0.5" min="60" max="160" placeholder="ex: 95">
                            </div>
                            <div class="measure-field">
                                <label>Longueur épaule <span class="measure-unit">(cm)</span></label>
                                <input type="number" name="longueur_epaule" step="0.5" min="30" max="60" placeholder="ex: 40">
                            </div>
                            <div class="measure-field">
                                <label>Longueur totale <span class="measure-unit">(cm)</span></label>
                                <input type="number" name="longueur_totale" step="0.5" min="40" max="180" placeholder="ex: 120">
                            </div>
                            <div class="measure-field">
                                <label>Longueur manche <span class="measure-unit">(cm)</span></label>
                                <input type="number" name="longueur_manche" step="0.5" min="10" max="80" placeholder="ex: 60">
                            </div>
                            <div class="measure-field">
                                <label>Tour de cou <span class="measure-unit">(cm)</span></label>
                                <input type="number" name="tour_cou" step="0.5" min="25" max="60" placeholder="ex: 36">
                            </div>
                            <div class="measure-field">
                                <label>Tour de bras <span class="measure-unit">(cm)</span></label>
                                <input type="number" name="tour_bras" step="0.5" min="20" max="60" placeholder="ex: 30">
                            </div>
                        </div>
                        <div class="measure-field" style="margin-top:16px;">
                            <label>Notes supplémentaires</label>
                            <textarea name="notes_mesures" rows="2" placeholder="Détails particuliers, couleur souhaitée..."></textarea>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- QTY + ADD -->
                    <div class="qty-cart">
                        <div class="qty-selector">
                            <button type="button" class="qty-btn" onclick="changeQty(-1)">−</button>
                            <input type="number" name="quantity" class="qty-input" value="1" min="1" max="99">
                            <button type="button" class="qty-btn" onclick="changeQty(1)">+</button>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg" style="flex:1;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                            Ajouter au panier
                        </button>
                    </div>
                </form>

                <!-- PRODUCT DESC -->
                <div class="product-detail-desc">
                    <?= nl2br(htmlspecialchars($product['description'])) ?>
                    <?php if($product['stock'] > 0 && $product['stock'] < 5): ?>
                    <div class="alert alert-info" style="margin-top:16px;">⚡ Plus que <?= $product['stock'] ?> en stock !</div>
                    <?php endif; ?>
                </div>

                <!-- DELIVERY INFO -->
                <div class="delivery-info-grid">
                    <div style="padding:14px; background:var(--cream-2); font-size:0.78rem;">
                        <div style="font-weight:700; margin-bottom:4px;">🚚 Livraison</div>
                        <div style="color:var(--text-muted);">Dakar & régions disponibles</div>
                    </div>
                    <div style="padding:14px; background:var(--cream-2); font-size:0.78rem;">
                        <div style="font-weight:700; margin-bottom:4px;">✂️ Confection</div>
                        <div style="color:var(--text-muted);">7–14 jours ouvrables</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RELATED -->
        <?php if(!empty($relatedProducts)): ?>
        <div style="margin-top:80px;">
            <div class="section-header" style="text-align:left;">
                <div class="section-eyebrow">Suggestions</div>
                <h2 class="section-title">Vous aimerez aussi</h2>
            </div>
            <div class="products-grid">
                <?php foreach($relatedProducts as $rp): ?>
                <a href="produit.php?slug=<?= $rp['slug'] ?>" class="product-card">
                    <div class="product-image-wrap">
                        <?php if($rp['image']): ?>
                        <img src="<?= UPLOADS_URL . htmlspecialchars($rp['image']) ?>" alt="<?= htmlspecialchars($rp['name']) ?>" loading="lazy">
                        <?php else: ?>
                        <div class="product-placeholder">👗</div>
                        <?php endif; ?>
                    </div>
                    <div class="product-info">
                        <div class="product-name"><?= htmlspecialchars($rp['name']) ?></div>
                        <div class="product-price"><span class="price-current"><?= number_format($rp['promo_price'] ?: $rp['price'], 0, ',', ' ') ?> <?= CURRENCY ?></span></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
// Color selection
function selectColor(btn) {
    document.querySelectorAll('.color-swatch-btn').forEach(b => {
        b.style.border = '2px solid transparent';
        b.style.transform = 'scale(1)';
    });
    btn.style.border = '2px solid #c8921a';
    btn.style.transform = 'scale(1.2)';
    document.getElementById('selected_color').value     = btn.dataset.color;
    document.getElementById('selected_color_hex').value = btn.dataset.hex;
    document.getElementById('color-label-text').textContent = '— ' + btn.dataset.color;
}

// Size selection
document.querySelectorAll('.size-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.size-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('selected_size').value = this.dataset.size;
    });
});

function switchMainImage(thumb) {
    const main = document.getElementById('mainImage');
    if (!main) return;
    // Fade out
    main.style.transition = 'opacity 0.2s';
    main.style.opacity = '0';
    setTimeout(() => {
        main.src = thumb.dataset.src;
        main.style.opacity = '1';
    }, 200);
    // Active state
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}
</script>
<?php require_once 'includes/footer.php'; ?>
