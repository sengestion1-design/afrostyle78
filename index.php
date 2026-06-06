<?php
$pageTitle = 'AfroStyle78 — Mode Africaine Sur-Mesure | Mariages & Cérémonies | Guyancourt (78)';
require_once 'includes/header.php';

$db = getDB();
$featured    = $db->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.featured=1 AND p.active=1 LIMIT 32")->fetchAll();
$allCats     = $db->query("SELECT cat.*, COUNT(p.id) as prod_count FROM categories cat LEFT JOIN products p ON p.category_id = cat.id GROUP BY cat.id")->fetchAll();
$catIcons    = ['robes'=>'👗','ensemble-homme'=>'🧥','ensemble-femme'=>'👘','accessoires'=>'💎','bazin'=>'🪡'];
$catAllImage = $db->query("SELECT setting_value FROM settings WHERE setting_key='cat_all_image'")->fetchColumn();
?>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-pattern"></div>
    <div class="hero-accent"></div>

    <div class="hero-content">
        <div class="hero-text">
            <div class="hero-eyebrow">AFROSTYLE78 — Guyancourt (78)</div>
            <h1 class="hero-title">
                L'Afrique<br>
                <em>réinventée</em>
            </h1>
            <p class="hero-subtitle" style="font-size:1.1rem; max-width:480px; line-height:1.75; margin-bottom:32px;">Le chic & l'élégance du sur-mesure africain. Spécialiste Mariages & Cérémonies. Livraison France & International.</p>
            <div class="hero-actions">
                <a href="boutique.php" class="btn btn-primary btn-lg"><span class="btn-text-full">Explorer les collections</span><span class="btn-text-short">Collections</span></a>
                <a href="sur-mesure.php" class="btn btn-outline" style="border-color:rgba(253,246,236,0.3);">Sur-Mesure</a>
            </div>
            <div class="hero-stat-grid">
                <div class="hero-stat">
                    <div class="hero-stat-num">500+</div>
                    <div class="hero-stat-label">Clients satisfaits</div>
                </div>
                <div class="hero-stat" style="padding-left:40px; border-left:1px solid rgba(200,146,26,0.2);">
                    <div class="hero-stat-num">7–14j</div>
                    <div class="hero-stat-label">Délai confection</div>
                </div>
                <div class="hero-stat" style="padding-left:40px; border-left:1px solid rgba(200,146,26,0.2);">
                    <div class="hero-stat-num">100%</div>
                    <div class="hero-stat-label">Fait à la main</div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2 PHOTOS CÔTE À CÔTE -->
    <div class="hero-photos">
        <div class="hero-photo-wrap hero-photo-left">
            <span class="hp-corner hp-tl"></span><span class="hp-corner hp-tr"></span>
            <span class="hp-corner hp-bl"></span><span class="hp-corner hp-br"></span>
            <div class="hp-tag"><span>Collection Bazin</span></div>
            <img src="<?= SITE_URL ?>/assets/coupe.PNG" alt="AfroStyle Collection" class="hero-photo-img">
            <div class="hp-overlay"></div>
        </div>
        <div class="hp-divider">
            <div class="hp-divider-line"></div>
            <span class="hp-divider-star">✦</span>
            <div class="hp-divider-line"></div>
        </div>
        <div class="hero-photo-wrap hero-photo-right">
            <span class="hp-corner hp-tl"></span><span class="hp-corner hp-tr"></span>
            <span class="hp-corner hp-bl"></span><span class="hp-corner hp-br"></span>
            <div class="hp-tag"><span>Nouvelle Collection</span></div>
            <img src="<?= SITE_URL ?>/assets/coupe2.PNG" alt="AfroStyle Collection 2" class="hero-photo-img">
            <div class="hp-overlay"></div>
        </div>
    </div>

</section>

<!-- TRUST BAR -->
<div style="background:#fff;border-top:1px solid #ece6dc;border-bottom:1px solid #ece6dc;">
    <div class="container">
        <div style="display:flex;flex-wrap:wrap;justify-content:center;gap:0;padding:0;">
            <div style="display:flex;align-items:center;gap:10px;padding:16px 28px;border-right:1px solid #ece6dc;flex:1;min-width:180px;justify-content:center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c8921a" stroke-width="1.5"><path d="M1 3h15v13H1zM16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                <div><div style="font-size:0.75rem;font-weight:700;color:#1a1008;letter-spacing:0.05em;">Livraison rapide</div><div style="font-size:0.68rem;color:#7a6248;">France & International</div></div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;padding:16px 28px;border-right:1px solid #ece6dc;flex:1;min-width:180px;justify-content:center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c8921a" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                <div><div style="font-size:0.75rem;font-weight:700;color:#1a1008;letter-spacing:0.05em;">Paiement sécurisé</div><div style="font-size:0.68rem;color:#7a6248;">CB, Wave, Orange Money</div></div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;padding:16px 28px;border-right:1px solid #ece6dc;flex:1;min-width:180px;justify-content:center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c8921a" stroke-width="1.5"><path d="M12 2L3 7v6c0 5 4 9.3 9 11 5-1.7 9-6 9-11V7L12 2z"/></svg>
                <div><div style="font-size:0.75rem;font-weight:700;color:#1a1008;letter-spacing:0.05em;">Sur-mesure garanti</div><div style="font-size:0.68rem;color:#7a6248;">Toutes morphologies</div></div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;padding:16px 28px;flex:1;min-width:180px;justify-content:center;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c8921a" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                <div><div style="font-size:0.75rem;font-weight:700;color:#1a1008;letter-spacing:0.05em;">Qualité artisanale</div><div style="font-size:0.68rem;color:#7a6248;">100% fait à la main</div></div>
            </div>
        </div>
    </div>
</div>

<!-- CATEGORIES STRIP -->
<section class="categories-strip">
    <div class="container">
        <div class="section-header" style="text-align:left; margin-bottom:40px;">
            <div class="section-eyebrow" style="color:var(--gold-pale);">Nos univers</div>
            <h2 class="section-title" style="color:var(--cream);">Explorez par <em>catégorie</em></h2>
        </div>
    </div>
    <div class="categories-scroll-outer">
    <div class="categories-scroll">
        <a href="boutique.php" class="cat-card" style="border-color:var(--gold);">
            <?php if ($catAllImage): ?>
            <div class="cat-photo">
                <img src="<?= UPLOADS_URL . htmlspecialchars($catAllImage) ?>" alt="Toutes collections">
            </div>
            <?php else: ?>
            <div class="cat-icon" style="opacity:0.4;">✦</div>
            <?php endif; ?>
            <div class="cat-name" style="color:var(--gold);">Tout voir</div>
            <div class="cat-count">Toutes collections</div>
        </a>
        <?php foreach($allCats as $cat): ?>
        <a href="boutique.php?cat=<?= $cat['slug'] ?>" class="cat-card">
            <?php if (!empty($cat['image'])): ?>
            <div class="cat-photo">
                <img src="<?= UPLOADS_URL . htmlspecialchars($cat['image']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>">
            </div>
            <?php else: ?>
            <div class="cat-icon"><?= $catIcons[$cat['slug']] ?? '👔' ?></div>
            <?php endif; ?>
            <div class="cat-name"><?= htmlspecialchars($cat['name']) ?></div>
            <div class="cat-count"><?= $cat['prod_count'] ?> article<?= $cat['prod_count'] > 1 ? 's' : '' ?></div>
        </a>
        <?php endforeach; ?>
    </div>
    </div><!-- /.categories-scroll-outer -->

<script>
(function() {
  /*
   * Carousel auto-scroll — iOS Safari compatible
   * Stratégie : translateX sur le strip, jamais scrollLeft.
   * Safari annule scrollLeft quand scroll-snap est actif ET quand
   * html/body ont overflow-x:hidden. translateX n'est affecté
   * par aucun de ces deux problèmes.
   *
   * Boucle seamless : les cartes originales sont clonées une fois.
   * L'animation avance de 0 à -stripHalfW puis saute silencieusement
   * à 0 (les clones reprennent exactement là où les originaux s'arrêtent).
   */

  function init() {
    var strip  = document.querySelector('.categories-scroll');
    var outer  = document.querySelector('.categories-scroll-outer');
    if (!strip || !outer) return;

    /* ── 1. Clones pour la boucle seamless ── */
    var originals = Array.prototype.slice.call(strip.querySelectorAll('.cat-card'));
    originals.forEach(function(card) {
      var clone = card.cloneNode(true);
      clone.setAttribute('aria-hidden', 'true');
      clone.setAttribute('tabindex', '-1');
      clone.addEventListener('click', function(e) { e.preventDefault(); });
      strip.appendChild(clone);
    });

    /* ── 2. État ── */
    var PX_PER_SEC = 55;        // vitesse en pixels/seconde
    var currentX   = 0;         // translateX courant (valeur négative)
    var halfW      = 0;         // largeur d'un jeu de cartes (recalculée)
    var paused     = false;
    var lastTime   = null;
    var resumeTimer = null;
    var rafId       = null;

    /* ── 3. Calcul de halfW après layout ── */
    function measureHalf() {
      /* La moitié = largeur totale du strip / 2
         (originaux + clones sont identiques) */
      halfW = strip.scrollWidth / 2;
      /* Fallback si scrollWidth n'est pas encore dispo (Safari) */
      if (halfW <= 0) {
        var gap  = parseFloat(getComputedStyle(strip).gap) || 10;
        var card = strip.querySelector('.cat-card');
        if (card) {
          halfW = originals.length * (card.offsetWidth + gap);
        }
      }
      return halfW;
    }

    /* ── 4. Boucle RAF ── */
    function step(ts) {
      if (!paused) {
        if (lastTime !== null) {
          var delta = ts - lastTime;
          /* Sécurité : si delta > 200ms (tab caché, etc.) on ignore le saut */
          if (delta < 200) {
            currentX -= (PX_PER_SEC * delta) / 1000;
            /* Recalcule halfW si pas encore stable (Safari lazy layout) */
            var hw = halfW > 0 ? halfW : measureHalf();
            if (hw > 0 && currentX <= -hw) {
              currentX += hw;   /* saut invisible — les clones prennent la relève */
            }
            strip.style.transform = 'translateX(' + currentX + 'px)';
          }
        }
        lastTime = ts;
      } else {
        lastTime = null;    /* évite le saut brutal au retour de pause */
      }
      rafId = requestAnimationFrame(step);
    }

    /* ── 5. Swipe touch : drag libre puis reprise ── */
    var touchStartX    = 0;
    var touchStartTX   = 0;   /* translateX au début du touch */
    var isDragging     = false;

    outer.addEventListener('touchstart', function(e) {
      isDragging   = true;
      touchStartX  = e.touches[0].clientX;
      touchStartTX = currentX;
      paused       = true;
      clearTimeout(resumeTimer);
      /* Coupe la transition CSS pendant le drag */
      strip.style.transition = 'none';
    }, { passive: true });

    outer.addEventListener('touchmove', function(e) {
      if (!isDragging) return;
      var dx   = e.touches[0].clientX - touchStartX;
      var newX = touchStartTX + dx;
      /* Borne : pas de débordement hors boucle */
      var hw = halfW > 0 ? halfW : measureHalf();
      if (hw > 0) {
        if (newX > 0)   newX = 0;
        if (newX < -hw) newX = -hw + 1;
      }
      currentX = newX;
      strip.style.transform = 'translateX(' + currentX + 'px)';
    }, { passive: true });

    outer.addEventListener('touchend', function() {
      isDragging = false;
      clearTimeout(resumeTimer);
      resumeTimer = setTimeout(function() {
        paused = false;
      }, 1500);
    }, { passive: true });

    /* ── 6. Pause hover (desktop) ── */
    outer.addEventListener('mouseenter', function() { paused = true; });
    outer.addEventListener('mouseleave', function() { paused = false; });

    /* ── 7. Pause quand l'onglet est caché (économie batterie) ── */
    document.addEventListener('visibilitychange', function() {
      if (document.hidden) {
        paused = true;
      } else {
        setTimeout(function() { paused = false; }, 300);
      }
    });

    /* ── 8. Recalcul au resize ── */
    var resizeTimer;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function() {
        var hw = measureHalf();
        /* Si on a dépassé la nouvelle moitié, recentre */
        if (hw > 0 && currentX <= -hw) {
          currentX = currentX % (-hw) || 0;
          strip.style.transform = 'translateX(' + currentX + 'px)';
        }
      }, 150);
    });

    /* ── 9. Démarrage : attend images + délai Safari layout ── */
    function startAfterImages() {
      var imgs  = strip.querySelectorAll('img');
      var total = imgs.length;
      if (total === 0) {
        setTimeout(function() {
          measureHalf();
          rafId = requestAnimationFrame(step);
        }, 100);
        return;
      }
      var loaded = 0;
      function onLoad() {
        loaded++;
        if (loaded >= total) {
          setTimeout(function() {
            measureHalf();
            rafId = requestAnimationFrame(step);
          }, 120); /* Safari a besoin d'un tick supplémentaire après onload */
        }
      }
      imgs.forEach(function(img) {
        if (img.complete) { onLoad(); }
        else {
          img.addEventListener('load',  onLoad);
          img.addEventListener('error', onLoad);
        }
      });
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', startAfterImages);
    } else {
      startAfterImages();
    }
  }

  /* Lance après le chargement complet pour que offsetWidth soit fiable */
  if (document.readyState === 'complete') {
    init();
  } else {
    window.addEventListener('load', init);
  }
})();
</script>
</section>

<!-- FEATURED PRODUCTS -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-eyebrow">Sélection</div>
            <h2 class="section-title">Nos créations <em>phares</em></h2>
            <p class="section-subtitle">Des pièces uniques, brodées à la main, pensées pour sublimer votre silhouette.</p>
        </div>
        <?php if(empty($featured)): ?>
        <div class="empty-state">
            <div class="empty-state-icon">👗</div>
            <h3>Collection en préparation</h3>
            <p>Nos créations arrivent bientôt. Revenez nous voir !</p>
        </div>
        <?php else: ?>
        <div class="products-grid">
            <?php foreach($featured as $p): ?>
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
                        <button class="btn btn-primary btn-sm btn-full"
                            data-img="<?= $p['image'] ? UPLOADS_URL . htmlspecialchars($p['image']) : '' ?>"
                            data-colors="<?= htmlspecialchars($p['available_colors'] ?? '[]') ?>"
                            onclick="event.preventDefault(); openSizeModal(<?= $p['id'] ?>, '<?= htmlspecialchars($p['name']) ?>', '<?= htmlspecialchars($p['available_sizes'] ?? '') ?>', this)">Ajouter au panier</button>
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
        <div class="text-center mt-40">
            <a href="boutique.php" class="btn btn-dark btn-lg">Voir toute la collection</a>
        </div>

        <!-- SLIDER CATÉGORIES -->
        <div class="cat-slider-wrap">
            <div class="cat-slider">
                <a href="boutique.php" class="cat-slide" style="border-color:var(--gold);">
                    <?php if($catAllImage): ?>
                    <img src="<?= UPLOADS_URL . htmlspecialchars($catAllImage) ?>" alt="Toutes collections">
                    <?php else: ?>
                    <div class="cat-slide-placeholder"></div>
                    <?php endif; ?>
                    <div class="cat-slide-overlay"></div>
                    <div class="cat-slide-info">
                        <div class="cat-slide-name" style="color:var(--gold);">Tout voir</div>
                        <div class="cat-slide-count">Toutes collections</div>
                    </div>
                </a>
                <?php foreach(array_merge($allCats, $allCats) as $cat): ?>
                <a href="boutique.php?cat=<?= $cat['slug'] ?>" class="cat-slide">
                    <?php if(!empty($cat['image'])): ?>
                    <img src="<?= UPLOADS_URL . htmlspecialchars($cat['image']) ?>" alt="<?= htmlspecialchars($cat['name']) ?>">
                    <?php else: ?>
                    <div class="cat-slide-placeholder"><?= $catIcons[$cat['slug']] ?? '👔' ?></div>
                    <?php endif; ?>
                    <div class="cat-slide-overlay"></div>
                    <div class="cat-slide-info">
                        <div class="cat-slide-name"><?= htmlspecialchars($cat['name']) ?></div>
                        <div class="cat-slide-count" style="color:var(--gold);"><?= $cat['prod_count'] ?> article<?= $cat['prod_count'] > 1 ? 's' : '' ?></div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php endif; ?>
    </div>
</section>

<!-- MARIAGES & CÉRÉMONIES -->
<section class="mariage-section">
    <div class="mariage-header">
        <div class="section-eyebrow" style="color:var(--gold);">Spécialiste depuis plus de 10 ans</div>
        <h2 class="section-title" style="color:var(--cream);">Mariages & <em>Cérémonies</em></h2>
        <p class="mariage-intro">De la robe de mariée au costume du marié, des tenues de cour aux habits de famille — nous habillons vos moments les plus précieux avec l'élégance africaine.</p>
    </div>

    <!-- TYPES D'OCCASIONS -->
    <div class="container">
        <div class="occasion-grid">
            <div class="occasion-card">
                <div class="occasion-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
                </div>
                <h3>Mariages</h3>
                <p>Robes, costumes &amp; tenues famille assortis.</p>
            </div>
            <div class="occasion-card">
                <div class="occasion-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                </div>
                <h3>Baptêmes</h3>
                <p>Bazin brodé, boubou de fête &amp; ensembles.</p>
            </div>
            <div class="occasion-card">
                <div class="occasion-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
                </div>
                <h3>Fiançailles</h3>
                <p>Kente luxe, wax premium &amp; accessoires.</p>
            </div>
            <div class="occasion-card">
                <div class="occasion-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <h3>Événements</h3>
                <p>Grand boubou, 3 pièces &amp; robes de soirée.</p>
            </div>
        </div>

        <!-- CTA MARIAGE -->
        <div class="mariage-cta">
            <div class="mariage-cta-inner">
                <div class="mariage-cta-text">
                    <h3>Votre mariage mérite le meilleur</h3>
                    <p>Contactez-nous pour une consultation gratuite et personnalisée. Nous créons la tenue de vos rêves.</p>
                </div>
                <div class="mariage-cta-actions">
                    <a href="https://wa.me/33644728730?text=Bonjour%20AfroStyle78%2C%20je%20souhaite%20une%20consultation%20pour%20des%20tenues%20de%20mariage." target="_blank" class="btn btn-primary btn-lg">
                        Consultation gratuite
                    </a>
                    <a href="sur-mesure.php" class="btn btn-outline btn-lg" style="border-color:var(--gold);color:var(--gold);">
                        Commander sur-mesure
                    </a>
                </div>
            </div>
        </div>

    </div>
</section>

<!-- FEATURED STRIP / USP -->
<section class="featured-strip">
    <div class="container">
        <div class="featured-layout">
            <div class="featured-text">
                <div class="section-eyebrow" style="color:var(--gold);">Notre promesse</div>
                <h2 class="section-title">Artisanat africain,<br><em>excellence moderne</em></h2>
                <ul class="featured-list">
                    <li>Tissus sélectionnés : bazin riche, kente, wax premium</li>
                    <li>Broderies faites à la main par nos artisans</li>
                    <li>Sur-mesure disponible sur tous nos articles</li>
                    <li>Livraison France & International</li>
                    <li>Délai de confection : 7 à 14 jours ouvrables</li>
                </ul>
                <div class="featured-cta">
                    <a href="sur-mesure.php" class="btn btn-primary">Commander sur-mesure</a>
                </div>
            </div>
            <div style="border:2px solid rgba(200,146,26,0.35);">
                <img src="<?= SITE_URL ?>/assets/mode.PNG" alt="Mode africaine AfroStyle78"
                     style="width:100%;height:auto;display:block;">
            </div>
        </div>
    </div>
</section>

<!-- TESTIMONIALS -->
<section class="testimonial-section section-sm">
    <div class="container">
        <div class="section-header">
            <div class="section-eyebrow">Avis clients</div>
            <h2 class="section-title">Ils nous font <em>confiance</em></h2>
        </div>
        <div class="testimonials-grid">
            <div class="testimonial-card">
                <div class="testimonial-stars"><span>★</span><span>★</span><span>★</span><span>★</span><span>★</span></div>
                <p class="testimonial-text">Ma robe pour le mariage était absolument magnifique. La broderie à la main est d'une qualité exceptionnelle. Je recommande vivement !</p>
                <div class="testimonial-author">Fatou D. — Paris (75)</div>
            </div>
            <div class="testimonial-card">
                <div class="testimonial-stars"><span>★</span><span>★</span><span>★</span><span>★</span><span>★</span></div>
                <p class="testimonial-text">Mon boubou sur-mesure est arrivé exactement à ma taille. Le tissu bazin est d'une qualité rare. AfroStyle m'a rendu fier de ma culture.</p>
                <div class="testimonial-author">Mamadou K. — Lyon (69)</div>
            </div>
            <div class="testimonial-card">
                <div class="testimonial-stars"><span>★</span><span>★</span><span>★</span><span>★</span><span>★</span></div>
                <p class="testimonial-text">Service impeccable du début à la fin. J'ai pu suivre ma commande en temps réel. La livraison était rapide et le colis très bien emballé.</p>
                <div class="testimonial-author">Aïssatou B. — Versailles (78)</div>
            </div>
        </div>
    </div>
</section>

<!-- NOTRE HISTOIRE -->
<section style="background:#1a1008;padding:80px 0;">
    <div class="container">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:64px;align-items:center;">
            <div>
                <div class="section-eyebrow" style="color:var(--gold);">Notre histoire</div>
                <h2 class="section-title" style="color:#f5f0e8;">Une passion, <em>un héritage</em></h2>
                <p style="color:rgba(245,240,232,0.7);font-size:1rem;line-height:1.9;margin-bottom:20px;">
                    AfroStyle78 est né d'une passion profonde pour la mode africaine et d'un désir de la rendre accessible depuis la France. Basé à <strong style="color:#c8921a;">Guyancourt, dans les Yvelines (78)</strong>, notre atelier célèbre la richesse des tissus africains — bazin riche, kente, wax premium — en les sublimant par une coupe moderne et des broderies artisanales.
                </p>
                <p style="color:rgba(245,240,232,0.7);font-size:1rem;line-height:1.9;margin-bottom:32px;">
                    Chaque création est pensée sur mesure, pour que vous portiez une pièce unique qui vous ressemble, qu'il s'agisse d'un mariage, d'une cérémonie ou d'un moment du quotidien.
                </p>
                <a href="sur-mesure.php" class="btn btn-primary">Découvrir notre savoir-faire</a>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div style="background:rgba(200,146,26,0.08);border:1px solid rgba(200,146,26,0.2);padding:28px 20px;text-align:center;">
                    <div style="font-size:2rem;font-weight:800;color:#c8921a;">500+</div>
                    <div style="font-size:0.72rem;letter-spacing:0.1em;text-transform:uppercase;color:rgba(245,240,232,0.5);margin-top:6px;">Clients satisfaits</div>
                </div>
                <div style="background:rgba(200,146,26,0.08);border:1px solid rgba(200,146,26,0.2);padding:28px 20px;text-align:center;">
                    <div style="font-size:2rem;font-weight:800;color:#c8921a;">7–14j</div>
                    <div style="font-size:0.72rem;letter-spacing:0.1em;text-transform:uppercase;color:rgba(245,240,232,0.5);margin-top:6px;">Délai confection</div>
                </div>
                <div style="background:rgba(200,146,26,0.08);border:1px solid rgba(200,146,26,0.2);padding:28px 20px;text-align:center;">
                    <div style="font-size:2rem;font-weight:800;color:#c8921a;">100%</div>
                    <div style="font-size:0.72rem;letter-spacing:0.1em;text-transform:uppercase;color:rgba(245,240,232,0.5);margin-top:6px;">Fait à la main</div>
                </div>
                <div style="background:rgba(200,146,26,0.08);border:1px solid rgba(200,146,26,0.2);padding:28px 20px;text-align:center;">
                    <div style="font-size:2rem;font-weight:800;color:#c8921a;">🌍</div>
                    <div style="font-size:0.72rem;letter-spacing:0.1em;text-transform:uppercase;color:rgba(245,240,232,0.5);margin-top:6px;">Livraison mondiale</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- BADGES CONFIANCE -->
<div style="background:#f5f0e8;padding:32px 0;border-top:1px solid #e8dcc8;">
    <div class="container">
        <div style="display:flex;flex-wrap:wrap;justify-content:center;align-items:center;gap:32px;">
            <div style="display:flex;align-items:center;gap:10px;">
                <!-- VISA -->
                <svg width="46" height="30" viewBox="0 0 750 471" xmlns="http://www.w3.org/2000/svg"><rect width="750" height="471" rx="40" fill="#1a1f71"/><path d="M278 334l33-200h53l-33 200h-53zm246-195c-10-4-27-8-47-8-52 0-89 27-89 66-1 29 26 45 46 54 20 10 27 16 27 25-1 13-16 19-31 19-21 0-32-3-49-10l-7-3-7 44c12 5 34 10 57 10 55 0 91-27 91-68 0-23-14-40-45-54-19-9-30-15-30-25 0-8 10-17 31-17 18 0 31 4 41 8l5 2 7-43zm136-5h-41c-13 0-22 4-28 17l-79 183h56s9-24 11-30h68c2 7 6 30 6 30h50l-43-200zm-66 130c4-11 20-53 20-53s4-11 7-18l3 16 11 55h-41zm-384-130l-51 136-5-28c-10-30-40-63-74-79l47 171h57l85-200h-59z" fill="#fff"/><path d="M163 134H80l-1 5c65 16 108 55 126 102l-18-90c-3-13-12-17-24-17z" fill="#f9a51a"/></svg>
            </div>
            <div style="display:flex;align-items:center;gap:8px;color:#7a6248;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c8921a" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                PAIEMENT SSL SÉCURISÉ
            </div>
            <div style="display:flex;align-items:center;gap:8px;color:#7a6248;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c8921a" stroke-width="1.5"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8 19.79 19.79 0 01.01 1.18 2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92z"/></svg>
                WAVE / ORANGE MONEY
            </div>
            <div style="display:flex;align-items:center;gap:8px;color:#7a6248;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c8921a" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                GUYANCOURT (78) · FRANCE
            </div>
            <div style="display:flex;align-items:center;gap:8px;color:#7a6248;font-size:0.75rem;font-weight:600;letter-spacing:0.05em;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#c8921a" stroke-width="1.5"><polyline points="20 6 9 17 4 12"/></svg>
                SATISFACTION GARANTIE
            </div>
        </div>
    </div>
</div>

<!-- MODAL SÉLECTION TAILLE + COULEUR -->
<div id="sizeModal" style="display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.65); align-items:center; justify-content:center;">
    <div style="background:#fff; max-width:460px; width:92%; padding:40px 36px; position:relative; max-height:90vh; overflow-y:auto;">
        <button onclick="closeSizeModal()" style="position:absolute; top:16px; right:16px; background:none; border:none; font-size:1.4rem; cursor:pointer; color:#999;">✕</button>
        <p style="font-size:0.75rem; color:var(--gold); letter-spacing:0.18em; text-transform:uppercase; margin:0 0 6px;">✦ Personnaliser</p>
        <h3 id="modalProductName" style="font-family:'Cormorant Garamond',serif; font-size:1.6rem; font-weight:600; color:var(--dark); margin:0 0 28px; line-height:1.2;"></h3>

        <!-- TAILLES -->
        <p style="font-size:0.78rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:var(--text-muted); margin:0 0 12px;">1. Votre taille</p>
        <div id="modalSizes" style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:28px;"></div>

        <!-- COULEURS -->
        <div id="modalColorsSection" style="display:none; margin-bottom:28px;">
            <p style="font-size:0.78rem; font-weight:700; letter-spacing:0.12em; text-transform:uppercase; color:var(--text-muted); margin:0 0 12px;">2. Votre couleur</p>
            <div id="modalColors" style="display:flex; flex-wrap:wrap; gap:12px;"></div>
            <div id="selectedColorName" style="margin-top:10px; font-size:0.9rem; color:var(--dark); font-weight:600; min-height:20px;"></div>
        </div>

        <button onclick="confirmAddToCart()" class="btn btn-primary" style="width:100%; justify-content:center; font-size:1rem; padding:16px;">
            Ajouter au panier
        </button>
    </div>
</div>

<script>
let _modalProductId    = null;
let _modalSelectedSize = null;
let _modalSelectedColor = null;
let _modalColors       = [];
let _modalImgSrc       = '';
let _modalOriginBtn    = null;

const ALL_SIZES = ['XS','S','M','L','XL','XXL','3XL'];

function flyToCart(imgSrc, originEl) {
    const cartIcon = document.querySelector('.cart-btn');
    if (!cartIcon) return;

    // Trouver la carte produit la plus proche
    const productCard = originEl ? originEl.closest('.product-card') : null;
    const imgEl = productCard ? productCard.querySelector('.product-image-wrap img, .product-image-wrap .product-placeholder') : null;
    const sourceRect = imgEl ? imgEl.getBoundingClientRect() : (originEl ? originEl.getBoundingClientRect() : null);
    if (!sourceRect) return;

    const cartRect = cartIcon.getBoundingClientRect();

    // Points de départ et d'arrivée
    const startX = sourceRect.left + sourceRect.width / 2;
    const startY = sourceRect.top + sourceRect.height / 2;
    const endX   = cartRect.left + cartRect.width / 2;
    const endY   = cartRect.top + cartRect.height / 2;

    // Taille initiale = taille de l'image source
    const size = Math.min(sourceRect.width, sourceRect.height, 120);

    // Créer l'élément volant
    const fly = document.createElement('div');
    fly.style.cssText = `
        position: fixed;
        z-index: 99999;
        width: ${size}px;
        height: ${size}px;
        left: ${startX - size/2}px;
        top: ${startY - size/2}px;
        border-radius: 8px;
        overflow: hidden;
        border: 3px solid #c8921a;
        box-shadow: 0 12px 40px rgba(200,146,26,0.6);
        pointer-events: none;
        will-change: transform, opacity;
    `;
    if (imgSrc) {
        fly.innerHTML = `<img src="${imgSrc}" style="width:100%;height:100%;object-fit:cover;">`;
    } else {
        fly.innerHTML = `<div style="width:100%;height:100%;background:#c8921a;display:flex;align-items:center;justify-content:center;font-size:2rem;">👗</div>`;
    }
    document.body.appendChild(fly);

    // Animation via requestAnimationFrame — arc de Bézier quadratique
    const duration = 750; // ms
    const start = performance.now();

    // Point de contrôle de l'arc (haut à mi-chemin)
    const cpX = (startX + endX) / 2;
    const cpY = Math.min(startY, endY) - 200;

    function easeInOut(t) { return t < 0.5 ? 2*t*t : -1+(4-2*t)*t; }

    function animate(now) {
        const elapsed = now - start;
        const raw = Math.min(elapsed / duration, 1);
        const t   = easeInOut(raw);

        // Bézier quadratique
        const x = (1-t)*(1-t)*startX + 2*(1-t)*t*cpX + t*t*endX;
        const y = (1-t)*(1-t)*startY + 2*(1-t)*t*cpY + t*t*endY;

        // Rétrécir + tourner en arrivant
        const scale   = 1 - t * 0.85;
        const rotate  = t * 360;
        const opacity = t > 0.75 ? 1 - (t - 0.75) * 4 : 1;

        fly.style.left    = `${x - size/2}px`;
        fly.style.top     = `${y - size/2}px`;
        fly.style.transform = `scale(${scale}) rotate(${rotate}deg)`;
        fly.style.opacity   = opacity;

        if (raw < 1) {
            requestAnimationFrame(animate);
        } else {
            fly.remove();
            // Pulse panier
            cartIcon.animate([
                {transform:'scale(1)'},
                {transform:'scale(1.5)', offset:0.4},
                {transform:'scale(1)'}
            ], {duration:300, easing:'ease-out'});
        }
    }

    requestAnimationFrame(animate);
}

function openSizeModal(productId, productName, sizesStr, btn) {
    _modalImgSrc        = btn ? (btn.dataset.img || '') : '';
    _modalOriginBtn     = btn || null;
    _modalSelectedColor = null;
    _modalColors        = [];

    // Charger les couleurs
    try { _modalColors = JSON.parse(btn ? (btn.dataset.colors || '[]') : '[]'); } catch(e) { _modalColors = []; }

    // Construire la section couleurs
    const colSection = document.getElementById('modalColorsSection');
    const colContainer = document.getElementById('modalColors');
    colContainer.innerHTML = '';
    document.getElementById('selectedColorName').textContent = '';

    if (_modalColors.length > 0) {
        colSection.style.display = 'block';
        _modalColors.forEach(color => {
            const swatch = document.createElement('button');
            swatch.type = 'button';
            swatch.title = color.name;
            swatch.style.cssText = `
                width: 40px; height: 40px; border-radius: 50%;
                background: ${color.hex};
                border: 3px solid transparent;
                cursor: pointer; transition: all 0.2s;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                position: relative;
            `;
            swatch.onclick = () => {
                colContainer.querySelectorAll('button').forEach(b => {
                    b.style.border = '3px solid transparent';
                    b.style.transform = 'scale(1)';
                });
                swatch.style.border = '3px solid var(--dark)';
                swatch.style.transform = 'scale(1.2)';
                _modalSelectedColor = color;
                document.getElementById('selectedColorName').textContent = '✓ ' + color.name;
            };
            colContainer.appendChild(swatch);
        });
    } else {
        colSection.style.display = 'none';
    }
    _modalProductId = productId;
    _modalSelectedSize = null;
    document.getElementById('modalProductName').textContent = productName;

    let available = ALL_SIZES;
    if (sizesStr) {
        try {
            const parsed = JSON.parse(sizesStr);
            if (Array.isArray(parsed) && parsed.length) {
                available = parsed.map(s => String(s).trim().replace(/^["']+|["']+$/g, ''));
            }
        } catch(e) {
            available = sizesStr.replace(/[\[\]]/g, '').split(',').map(s => s.trim().replace(/^["']+|["']+$/g, '')).filter(Boolean);
        }
    }
    const container = document.getElementById('modalSizes');
    container.innerHTML = '';

    available.forEach(size => {
        const cleanSize = String(size).trim().replace(/^["']+|["']+$/g, '');
        const btn = document.createElement('button');
        btn.textContent = cleanSize;
        btn.style.cssText = 'padding:10px 20px; border:2px solid #e0d8ce; background:#fff; font-family:Syne,sans-serif; font-size:1rem; font-weight:700; cursor:pointer; transition:all 0.2s; letter-spacing:0.05em;';
        btn.onclick = () => {
            container.querySelectorAll('button').forEach(b => {
                b.style.background = '#fff';
                b.style.borderColor = '#e0d8ce';
                b.style.color = '#333';
            });
            btn.style.background = 'var(--dark)';
            btn.style.borderColor = 'var(--dark)';
            btn.style.color = 'var(--gold)';
            _modalSelectedSize = cleanSize;
        };
        container.appendChild(btn);
    });

    const modal = document.getElementById('sizeModal');
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeSizeModal() {
    document.getElementById('sizeModal').style.display = 'none';
    document.body.style.overflow = '';
    _modalProductId = null;
    _modalSelectedSize = null;
    // _modalImgSrc conservé pour l'animation
}

function confirmAddToCart() {
    if (!_modalSelectedSize) {
        const container = document.getElementById('modalSizes');
        container.style.outline = '2px solid #e53e3e';
        setTimeout(() => container.style.outline = '', 1000);
        return;
    }
    if (_modalColors.length > 0 && !_modalSelectedColor) {
        const container = document.getElementById('modalColors');
        container.style.outline = '2px solid #e53e3e';
        setTimeout(() => container.style.outline = '', 1000);
        return;
    }

    const colorParam = _modalSelectedColor
        ? '&color=' + encodeURIComponent(_modalSelectedColor.name) + '&color_hex=' + encodeURIComponent(_modalSelectedColor.hex)
        : '';

    fetch('<?= SITE_URL ?>/cart-action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add&product_id=' + _modalProductId + '&quantity=1&size=' + encodeURIComponent(_modalSelectedSize) + colorParam
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Sauvegarder le bouton source avant fermeture modal
            const savedOriginBtn = _modalOriginBtn;
            const savedImgSrc   = _modalImgSrc;

            closeSizeModal();

            // Lancer l'animation depuis la carte produit
            flyToCart(savedImgSrc, savedOriginBtn);

            // Mettre à jour le badge
            setTimeout(() => {
                const badge = document.querySelector('.cart-badge');
                if (badge) {
                    badge.textContent = data.cartCount;
                } else {
                    const cartBtn = document.querySelector('.cart-btn');
                    if (cartBtn) {
                        const b = document.createElement('span');
                        b.className = 'cart-badge';
                        b.textContent = data.cartCount;
                        cartBtn.appendChild(b);
                    }
                }
            }, 700);
        }
    });
}

// Fermer en cliquant dehors
document.getElementById('sizeModal').addEventListener('click', function(e) {
    if (e.target === this) closeSizeModal();
});
</script>

<?php require_once 'includes/footer.php'; ?>
