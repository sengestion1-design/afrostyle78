<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$cart = $_SESSION['cart'] ?? [];
$cartCount = array_sum(array_column($cart, 'quantity'));

// Fetch categories for nav
$db = getDB();
$categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= SITE_URL ?>/favicon-32.png">
    <link rel="icon" type="image/png" sizes="192x192" href="<?= SITE_URL ?>/favicon-192.png">
    <link rel="apple-touch-icon" href="<?= SITE_URL ?>/apple-touch-icon.png">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>AfroStyle78 | Mode Africaine — Guyancourt (78)</title>
    <meta name="description" content="AFROSTYLE78 — Le chic & l'élégance du sur-mesure africain. Spécialiste Mariages & Cérémonies. Basé à Guyancourt (78), livraison France & international.">
    <meta name="keywords" content="mode africaine, sur-mesure, mariage africain, cérémonie, bazin, wax, kente, Guyancourt, Yvelines, livraison France">
    <meta property="og:title" content="<?= isset($ogTitle) ? htmlspecialchars($ogTitle) : 'AfroStyle78 | Mode Africaine Sur-Mesure — Guyancourt (78)' ?>">
    <meta property="og:description" content="<?= isset($ogDesc) ? htmlspecialchars($ogDesc) : 'Le chic & l\'élégance du sur-mesure. Spécialiste Mariages & Cérémonies. Livraison France & international.' ?>">
    <meta property="og:type" content="<?= (isset($ogType) && in_array($ogType, ['website','product','article'], true)) ? $ogType : 'website' ?>">
    <meta property="og:url" content="<?= isset($ogUrl) ? htmlspecialchars($ogUrl) : SITE_URL ?>">
    <meta property="og:image" content="<?= isset($ogImage) ? htmlspecialchars($ogImage) : SITE_URL.'/logo.jpg' ?>">
    <meta property="og:site_name" content="AfroStyle78">
    <meta property="og:locale" content="fr_FR">
    <?php if(isset($ogPrice)): ?>
    <meta property="product:price:amount" content="<?= htmlspecialchars((string)$ogPrice) ?>">
    <meta property="product:price:currency" content="EUR">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle ?? '') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDesc ?? '') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage ?? '') ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;0,700;1,300;1,400&family=Syne:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/main.css?v=<?= filemtime(__DIR__.'/../assets/css/main.css') ?>">
    <?= isset($extraHead) ? $extraHead : '' ?>
</head>
<body>

<div class="noise-overlay"></div>

<!-- TOP BAR -->
<div class="topbar">
  <div class="topbar-track">
    <span>✦ Spécialiste Mariages & Cérémonies</span>
    <span>Sur-mesure — Le chic & l'élégance africaine</span>
    <span>Guyancourt (78) &nbsp;·&nbsp; +33 6 44 72 87 30 &nbsp;·&nbsp; Livraison France & International</span>
    <span>✦ Spécialiste Mariages & Cérémonies</span>
    <span>Sur-mesure — Le chic & l'élégance africaine</span>
    <span>Guyancourt (78) &nbsp;·&nbsp; +33 6 44 72 87 30 &nbsp;·&nbsp; Livraison France & International</span>
  </div>
</div>

<!-- NAVBAR -->
<nav class="navbar" id="navbar">
    <div class="nav-inner">
        <a href="<?= SITE_URL ?>" class="nav-logo">
            <img src="<?= SITE_URL ?>/logo.jpg" alt="AfroStyle" class="logo-img">
        </a>

        <ul class="nav-links">
            <li><a href="<?= SITE_URL ?>">Accueil</a></li>
            <li class="has-dropdown">
                <a href="<?= SITE_URL ?>/boutique.php">Collections</a>
                <ul class="dropdown">
                    <li><a href="<?= SITE_URL ?>/boutique.php">Tout voir</a></li>
                    <?php foreach($categories as $cat): ?>
                    <li><a href="<?= SITE_URL ?>/boutique.php?cat=<?= $cat['slug'] ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <li><a href="<?= SITE_URL ?>/boutique.php?filter=featured">Nouveautés</a></li>
            <li><a href="<?= SITE_URL ?>/sur-mesure.php">Sur-Mesure</a></li>
            <li><a href="<?= SITE_URL ?>/suivi.php">Suivi commande</a></li>
        </ul>

        <div class="nav-actions">
            <button class="nav-search-btn" onclick="toggleSearch()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            </button>
            <?php if (!empty($_SESSION['customer_id'])): ?>
            <a href="<?= SITE_URL ?>/compte.php#commandes" class="nav-orders-btn" title="Mes commandes">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:18px;height:18px;"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                <span>Mes commandes</span>
            </a>
            <a href="<?= SITE_URL ?>/compte.php#profil" class="nav-orders-btn" title="Mon profil">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:18px;height:18px;"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                <span>Profil</span>
            </a>
            <a href="<?= SITE_URL ?>/compte.php" class="nav-account-btn">
                <div class="nav-avatar"><?= mb_strtoupper(mb_substr($_SESSION['customer_name'], 0, 1)) ?></div>
                <span class="nav-account-label">Bonjour, <strong><?= htmlspecialchars($_SESSION['customer_name']) ?></strong></span>
            </a>
            <button onclick="document.getElementById('logout-modal').style.display='flex'" class="nav-orders-btn" title="Déconnexion" style="color:#e53e3e;background:none;border:none;cursor:pointer;font-family:inherit;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:18px;height:18px;"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span>Déconnexion</span>
            </button>

<!-- MODALE DÉCONNEXION -->
<style>
@media (max-width: 768px) {
    #logout-modal > div {
        padding: 16px 18px !important;
        max-width: 300px !important;
    }
    #logout-modal > div h2 { font-size: 1.1rem !important; margin-bottom: 4px !important; }
    #logout-modal > div p { font-size: 0.78rem !important; margin-bottom: 14px !important; }
    #logout-modal > div > div:first-child { width: 34px !important; height: 34px !important; margin-bottom: 8px !important; }
    #logout-modal > div > div:first-child svg { width: 18px !important; height: 18px !important; }
    #logout-modal button, #logout-modal a { padding: 8px 16px !important; font-size: 0.75rem !important; }
}
</style>
<div id="logout-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:9999;align-items:center;justify-content:center;">
    <div style="background:#fff;max-width:380px;width:90%;padding:24px 28px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.2);border-top:3px solid #e53e3e;">
        <div style="width:42px;height:42px;border-radius:50%;background:#fff0f0;display:flex;align-items:center;justify-content:center;margin:0 auto 12px;">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#e53e3e" stroke-width="1.5"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </div>
        <h2 style="font-family:'Cormorant Garamond',serif;font-size:1.35rem;font-weight:400;color:#1a1008;margin-bottom:6px;">Se déconnecter ?</h2>
        <p style="color:#7a6248;font-size:0.85rem;line-height:1.6;margin-bottom:20px;">Vous allez quitter votre espace client.</p>
        <div style="display:flex;gap:10px;justify-content:center;">
            <button onclick="document.getElementById('logout-modal').style.display='none'" style="padding:9px 22px;border:1.5px solid #e0d8ce;background:#fff;color:#1a1008;font-family:inherit;font-size:0.82rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;cursor:pointer;">
                Annuler
            </button>
            <a href="<?= SITE_URL ?>/logout.php" style="padding:9px 22px;background:#e53e3e;color:#fff;font-family:inherit;font-size:0.82rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;text-decoration:none;display:inline-block;">
                Me déconnecter
            </a>
        </div>
    </div>
</div>
            <?php else: ?>
            <div class="nav-auth-links">
                <a href="<?= SITE_URL ?>/login.php" class="nav-auth-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Connexion
                </a>
                <span class="nav-auth-sep">|</span>
                <a href="<?= SITE_URL ?>/register.php" class="nav-auth-link nav-auth-register">
                    Créer un compte
                </a>
            </div>
            <?php endif; ?>
            <a href="<?= SITE_URL ?>/panier.php" class="cart-btn">
                <span class="cart-inner">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="cart-icon"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                    <?php if($cartCount > 0): ?>
                    <span class="cart-count"><?= $cartCount ?></span>
                    <?php endif; ?>
                </span>
            </a>
            <button class="hamburger" id="hamburger" onclick="toggleMobileMenu()">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</nav>

<script>
(function() {
    function setHeaderHeight() {
        var topbar = document.querySelector('.topbar');
        var navbar = document.querySelector('.navbar');
        var th = topbar ? topbar.offsetHeight : 37;
        var nh = navbar ? navbar.offsetHeight : 64;
        document.documentElement.style.setProperty('--topbar-height', th + 'px');
        document.documentElement.style.setProperty('--header-height', (th + nh) + 'px');
    }
    setHeaderHeight();
    window.addEventListener('resize', setHeaderHeight);
})();
</script>

<!-- BARRE AUTH MOBILE (visible uniquement sur mobile si non connecté) -->
<?php if (empty($_SESSION['customer_id'])): ?>
<div class="mobile-auth-bar" id="mobileAuthBar">
    <a href="<?= SITE_URL ?>/login.php" class="mobile-auth-connexion">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="15" height="15"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Connexion
    </a>
    <span class="mobile-auth-divider"></span>
    <a href="<?= SITE_URL ?>/register.php" class="mobile-auth-register">
        Créer un compte
    </a>
</div>
<?php endif; ?>


<!-- MOBILE MENU -->
<div class="mobile-menu" id="mobileMenu">
    <button class="mobile-close" onclick="toggleMobileMenu()">✕</button>
    <ul>
        <li><a href="<?= SITE_URL ?>">Accueil</a></li>

        <!-- Collections avec sous-menu accordéon CSS pur -->
        <li class="mobile-has-submenu">
            <input type="checkbox" id="mob-collections-toggle" class="mobile-submenu-check">
            <label for="mob-collections-toggle" class="mobile-menu-toggle">
                <span class="mobile-menu-label">Collections</span>
                <svg class="mobile-submenu-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="6 9 12 15 18 9"/></svg>
            </label>
            <ul class="mobile-submenu">
                <li><a href="<?= SITE_URL ?>/boutique" class="sub-link">✦ Nos collections</a></li>
                <?php foreach($categories as $cat): ?>
                <li><a href="<?= SITE_URL ?>/boutique?cat=<?= $cat['slug'] ?>" class="sub-link"><?= htmlspecialchars($cat['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </li>

        <li><a href="<?= SITE_URL ?>/sur-mesure">Sur-Mesure</a></li>
        <li><a href="<?= SITE_URL ?>/suivi">Suivi commande</a></li>
        <li><a href="<?= SITE_URL ?>/panier">Panier (<?= $cartCount ?>)</a></li>
        <?php if (!empty($_SESSION['customer_id'])): ?>
        <li><a href="<?= SITE_URL ?>/compte#commandes">Mes commandes</a></li>
        <li><a href="<?= SITE_URL ?>/compte">Mon compte</a></li>
        <?php else: ?>
        <li><a href="<?= SITE_URL ?>/login">Connexion</a></li>
        <li><a href="<?= SITE_URL ?>/register" style="color:var(--gold);">Créer un compte</a></li>
        <?php endif; ?>
    </ul>
</div>

<!-- SEARCH OVERLAY -->
<div class="search-overlay" id="searchOverlay">
    <button class="search-close" onclick="toggleSearch()">✕</button>
    <form action="<?= SITE_URL ?>/boutique.php" method="GET" class="search-form">
        <input type="text" name="q" placeholder="Rechercher une création..." autofocus>
        <button type="submit">Rechercher</button>
    </form>
</div>

<main class="main-content">
