</main>

<!-- BANNIÈRE RGPD COOKIES -->
<div id="cookie-banner" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:99999;background:#1a1008;border-top:2px solid #c8921a;padding:16px 24px;box-shadow:0 -4px 24px rgba(0,0,0,0.3);">
    <div style="max-width:1200px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;gap:16px;justify-content:space-between;">
        <div style="flex:1;min-width:260px;">
            <p style="margin:0 0 4px;color:#f5f0e8;font-size:0.82rem;font-weight:700;letter-spacing:0.05em;">🍪 Ce site utilise des cookies</p>
            <p style="margin:0;color:rgba(245,240,232,0.65);font-size:0.75rem;line-height:1.6;">
                Nous utilisons des cookies essentiels au fonctionnement du site (session, panier, sécurité).
                Conformément au <strong style="color:#c8921a;">RGPD</strong> et à la réglementation <strong style="color:#c8921a;">CNIL</strong>,
                aucun cookie publicitaire ou de tracking n'est utilisé sans votre consentement.
            </p>
        </div>
        <div style="display:flex;gap:10px;flex-shrink:0;flex-wrap:wrap;">
            <button onclick="acceptCookies()" style="background:#c8921a;color:#1a1008;border:none;padding:10px 20px;font-size:0.78rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;cursor:pointer;">
                Accepter
            </button>
            <button onclick="rejectCookies()" style="background:transparent;color:rgba(245,240,232,0.6);border:1px solid rgba(245,240,232,0.2);padding:10px 20px;font-size:0.78rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;cursor:pointer;">
                Cookies essentiels uniquement
            </button>
            <a href="/politique-confidentialite" style="color:rgba(245,240,232,0.4);font-size:0.72rem;text-decoration:underline;align-self:center;">En savoir plus</a>
        </div>
    </div>
</div>

<script>
(function() {
    if (!localStorage.getItem('cookie_consent')) {
        document.getElementById('cookie-banner').style.display = 'block';
    }
})();
function acceptCookies() {
    localStorage.setItem('cookie_consent', 'accepted');
    document.getElementById('cookie-banner').style.display = 'none';
}
function rejectCookies() {
    localStorage.setItem('cookie_consent', 'essential');
    document.getElementById('cookie-banner').style.display = 'none';
}
</script>

<!-- BOUTON WHATSAPP FLOTTANT -->
<a href="https://wa.me/33644728730?text=Bonjour%20AfroStyle78%2C%20je%20souhaite%20avoir%20des%20informations%20sur%20vos%20cr%C3%A9ations."
   target="_blank" rel="noopener" class="whatsapp-float" aria-label="Contacter sur WhatsApp">
    <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
    </svg>
    <span>Nous contacter</span>
</a>

<footer class="footer">
    <div class="footer-kente"></div>
    <div class="footer-inner">
        <div class="footer-brand">
            <img src="<?= SITE_URL ?>/logo.jpg" alt="AfroStyle" class="footer-logo">
            <p style="font-weight:700;font-size:1rem;color:var(--gold-pale);margin-bottom:6px;">AFROSTYLE78</p>
            <p>Le chic & l'élégance du sur-mesure africain.<br>Spécialiste Mariages & Cérémonies.</p>
            <div class="social-links">
                <a href="https://www.instagram.com/tenue_africaine_afrostyle78/" target="_blank" rel="noopener" aria-label="Instagram">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                </a>
                <a href="https://www.facebook.com/Aboubacry78" target="_blank" rel="noopener" aria-label="Facebook">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="https://wa.me/33644728730" target="_blank" rel="noopener" aria-label="WhatsApp">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </a>
            </div>
        </div>
        <div class="footer-col">
            <h4>Collections</h4>
            <ul>
                <?php
                if(!isset($categories)) {
                    $db = getDB();
                    $categories = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
                }
                foreach($categories as $cat): ?>
                <li><a href="<?= SITE_URL ?>/boutique.php?cat=<?= $cat['slug'] ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Services</h4>
            <ul>
                <li><a href="<?= SITE_URL ?>/sur-mesure.php">Commande Sur-Mesure</a></li>
                <li><a href="<?= SITE_URL ?>/suivi.php">Suivi de commande</a></li>
                <li><a href="<?= SITE_URL ?>/guide-des-tailles.php">Guide des tailles</a></li>
                <li><a href="<?= SITE_URL ?>/politique-confidentialite.php">Livraison & Retours</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Contact</h4>
            <ul>
                <li>
                    <span class="footer-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg></span>
                    Guyancourt, Yvelines (78)
                </li>
                <li>
                    <span class="footer-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8 19.79 19.79 0 01.01 1.18 2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 14.92z"/></svg></span>
                    <a href="tel:+33644728730" style="color:var(--gold);text-decoration:none;font-weight:600;">+33 6 44 72 87 30</a>
                </li>
                <li>
                    <span class="footer-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></span>
                    contact@afrostyle78.fr
                </li>
                <li>
                    <span class="footer-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg></span>
                    Lun–Sam : 9h–19h
                </li>
                <li>
                    <span class="footer-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13" rx="1"/><path d="M16 8l5 3v5h-5V8z"/></svg></span>
                    Livraison France & International
                </li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <p style="margin-bottom:8px;">
            <a href="<?= SITE_URL ?>/cgv" style="color:rgba(253,246,236,0.5);text-decoration:none;font-size:0.75rem;margin:0 10px;">CGV</a>
            <a href="<?= SITE_URL ?>/mentions-legales" style="color:rgba(253,246,236,0.5);text-decoration:none;font-size:0.75rem;margin:0 10px;">Mentions légales</a>
            <a href="<?= SITE_URL ?>/politique-confidentialite" style="color:rgba(253,246,236,0.5);text-decoration:none;font-size:0.75rem;margin:0 10px;">Confidentialité</a>
        </p>
        <p>© 2026 AfroStyle78 — Tous droits réservés · Mode Africaine Sur-Mesure · Guyancourt (78)</p>
        <p style="margin-top:8px;font-size:0.72rem;color:rgba(253,246,236,0.3);letter-spacing:0.08em;">
            Réalisé par
            <a href="https://www.sen-gestion.com" target="_blank" rel="noopener"
               style="color:rgba(200,146,26,0.6);text-decoration:none;font-weight:600;letter-spacing:0.05em;transition:color 0.3s;"
               onmouseover="this.style.color='#c8921a'" onmouseout="this.style.color='rgba(200,146,26,0.6)'">
                M. SECK
            </a>
        </p>
    </div>
</footer>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<?php if (isset($useTurnstile) && $useTurnstile): ?>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>
<?= isset($extraScripts) ? $extraScripts : '' ?>
</body>
</html>
