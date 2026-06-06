<?php
ob_start();
$pageTitle = 'Boutique';
require_once 'includes/header.php';

$db = getDB();

// Filters
$catSlug = $_GET['cat'] ?? '';
$q = trim($_GET['q'] ?? '');
$sort = $_GET['sort'] ?? 'newest';
$filter = $_GET['filter'] ?? '';

$where = ['p.active = 1'];
$params = [];

if ($catSlug) {
    $where[] = 'c.slug = ?';
    $params[] = $catSlug;
}
if ($q) {
    $where[] = '(p.name LIKE ? OR p.description LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($filter === 'featured') {
    $where[] = 'p.featured = 1';
}
if ($filter === 'promo') {
    $where[] = 'p.promo_price IS NOT NULL';
}

$orderBy = match($sort) {
    'price_asc' => 'COALESCE(p.promo_price, p.price) ASC',
    'price_desc' => 'COALESCE(p.promo_price, p.price) DESC',
    'name_asc' => 'p.name ASC',
    default => 'p.created_at DESC'
};

$sql = "SELECT p.*, c.name as cat_name, c.slug as cat_slug FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE " . implode(' AND ', $where) . " ORDER BY $orderBy";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$currentCat = null;
if ($catSlug) {
    $stmt2 = $db->prepare("SELECT * FROM categories WHERE slug = ?");
    $stmt2->execute([$catSlug]);
    $currentCat = $stmt2->fetch();
}
?>

<div class="container">
    <div class="breadcrumb">
        <a href="<?= SITE_URL ?>">Accueil</a>
        <span>›</span>
        <?php if($currentCat): ?>
        <a href="boutique.php">Boutique</a>
        <span>›</span>
        <span class="current"><?= htmlspecialchars($currentCat['name']) ?></span>
        <?php elseif($q): ?>
        <a href="boutique.php">Boutique</a>
        <span>›</span>
        <span class="current">Résultats pour "<?= htmlspecialchars($q) ?>"</span>
        <?php else: ?>
        <span class="current">Boutique</span>
        <?php endif; ?>
    </div>
</div>

<div class="filters-bar">
    <div class="container">
        <?php
            $baseParams = ['q' => $q, 'cat' => $catSlug, 'sort' => $sort !== 'newest' ? $sort : '', 'filter' => $filter];
            function filterUrl(array $base, array $override): string {
                $p = array_filter(array_merge($base, $override), fn($v) => $v !== '' && $v !== null);
                return 'boutique.php' . ($p ? '?' . http_build_query($p) : '');
            }
        ?>
        <div class="filters-inner">
            <span class="filter-label">Filtrer :</span>
            <a href="boutique.php" class="filter-btn <?= !$catSlug && !$filter ? 'active' : '' ?>">Tout</a>
            <?php foreach($categories as $cat): ?>
            <a href="<?= filterUrl($baseParams, ['cat' => $cat['slug'], 'filter' => '']) ?>" class="filter-btn <?= $catSlug === $cat['slug'] ? 'active' : '' ?>"><?= htmlspecialchars($cat['name']) ?></a>
            <?php endforeach; ?>
            <a href="<?= filterUrl($baseParams, ['filter' => 'featured']) ?>" class="filter-btn <?= $filter === 'featured' ? 'active' : '' ?>">★ Coups de cœur</a>
            <a href="<?= filterUrl($baseParams, ['filter' => 'promo']) ?>" class="filter-btn <?= $filter === 'promo' ? 'active' : '' ?>">Promotions</a>

            <form method="GET" style="margin-left:auto; display:flex; gap:8px; align-items:center;">
                <?php if($catSlug): ?><input type="hidden" name="cat" value="<?= htmlspecialchars($catSlug) ?>"><?php endif; ?>
                <?php if($q): ?><input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>"><?php endif; ?>
                <select name="sort" class="sort-select" onchange="this.form.submit()">
                    <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Plus récents</option>
                    <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Prix croissant</option>
                    <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Prix décroissant</option>
                    <option value="name_asc" <?= $sort==='name_asc'?'selected':'' ?>>A–Z</option>
                </select>
            </form>
        </div>
    </div>
</div>

<section class="section">
    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:40px;">
            <h1 style="font-family:'Cormorant Garamond',serif; font-size:2rem; font-weight:400;">
                <?php if($currentCat): ?>
                    <?= htmlspecialchars($currentCat['name']) ?>
                <?php elseif($q): ?>
                    Résultats pour "<em><?= htmlspecialchars($q) ?></em>"
                <?php elseif($filter === 'featured'): ?>
                    Coups de cœur
                <?php else: ?>
                    Toutes nos <em style="color:var(--gold);">créations</em>
                <?php endif; ?>
            </h1>
            <span style="color:var(--text-muted); font-size:0.8rem;"><?= count($products) ?> article<?= count($products) > 1 ? 's' : '' ?></span>
        </div>

        <?php if(empty($products)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">🔍</div>
            <h3>Aucun article trouvé</h3>
            <p>Essayez de modifier vos filtres ou <a href="boutique.php" style="color:var(--gold);">voir toute la collection</a>.</p>
        </div>
        <?php else: ?>
        <div class="products-grid">
            <?php foreach($products as $p): ?>
            <a href="produit.php?slug=<?= $p['slug'] ?>" class="product-card">
                <div class="product-image-wrap">
                    <?php if($p['image']): ?>
                    <img src="<?= UPLOADS_URL . htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                    <?php else: ?>
                    <div class="product-placeholder">👗</div>
                    <?php endif; ?>
                    <?php if($p['promo_price']): ?>
                    <div class="product-badge promo">Promo</div>
                    <?php elseif($p['featured']): ?>
                    <div class="product-badge">Coup de cœur</div>
                    <?php endif; ?>
                    <div class="product-actions">
                        <button class="btn btn-primary btn-sm btn-full" onclick="event.preventDefault(); window.location='produit.php?slug=<?= $p['slug'] ?>'">Voir le produit</button>
                    </div>
                </div>
                <div class="product-info">
                    <div class="product-category"><?= htmlspecialchars($p['cat_name'] ?? '') ?></div>
                    <div class="product-name"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="product-price">
                        <?php if($p['promo_price']): ?>
                        <span class="price-current price-promo"><?= number_format($p['promo_price'], 0, ',', ' ') ?> <?= CURRENCY ?></span>
                        <span class="price-old"><?= number_format($p['price'], 0, ',', ' ') ?></span>
                        <?php else: ?>
                        <span class="price-current"><?= number_format($p['price'], 0, ',', ' ') ?> <?= CURRENCY ?></span>
                        <?php endif; ?>
                        <?php if($p['allow_custom_measure']): ?><span class="tag">Sur-mesure</span><?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<script>
(function() {
  if (window.innerWidth > 768) return;
  var inner = document.querySelector('.filters-inner');
  if (!inner) return;

  var PX_PER_SEC = 40;
  var currentX = 0;
  var maxX = 0;
  var paused = false;
  var lastTime = null;
  var rafId = null;
  var resumeTimer = null;

  function measure() {
    maxX = inner.scrollWidth - inner.offsetWidth;
  }

  function step(ts) {
    if (!lastTime) lastTime = ts;
    var dt = ts - lastTime;
    lastTime = ts;

    if (!paused && maxX > 0) {
      currentX += PX_PER_SEC * dt / 1000;
      if (currentX >= maxX) currentX = 0;
      inner.scrollLeft = currentX;
    }
    rafId = requestAnimationFrame(step);
  }

  function pause() {
    paused = true;
    clearTimeout(resumeTimer);
    resumeTimer = setTimeout(function() { paused = false; }, 2000);
  }

  window.addEventListener('resize', measure);
  inner.addEventListener('touchstart', pause, { passive: true });
  inner.addEventListener('touchmove', function() {
    currentX = inner.scrollLeft;
  }, { passive: true });

  measure();
  rafId = requestAnimationFrame(step);
})();
</script>

<?php require_once 'includes/footer.php'; ?>
