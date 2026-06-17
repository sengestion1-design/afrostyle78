<?php
ob_start();
$pageTitle = 'Commande confirmée';
require_once 'includes/header.php';

$orderNumber   = $_GET['order'] ?? '';
$paymentStatus = $_GET['payment'] ?? '';

$db    = getDB();
$order = null;
if ($orderNumber) {
    $stmt = $db->prepare("SELECT o.*, c.first_name, c.last_name, c.email FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.order_number=?");
    $stmt->execute([$orderNumber]);
    $order = $stmt->fetch();
}

$allSettings = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$waveNumber     = $allSettings['wave_number'] ?? '';
$waveOwner      = $allSettings['wave_owner_name'] ?? '';
$omNumber       = $allSettings['orange_money_number'] ?? '';
$omOwner        = $allSettings['om_owner_name'] ?? '';
$waveApiKey  = $allSettings['wave_api_key'] ?? '';
$stripeOk    = !empty($allSettings['stripe_secret_key']);

$isPaid = $order && $order['payment_status'] === 'paid';
$method = $order['payment_method'] ?? '';

// Génération token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Traitement confirmation Wave/OM manuel
$confirmMsg   = '';
$confirmError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_mobile_payment']) && $order) {
    // Vérification propriété commande
    $isOwner = !empty($_SESSION['customer_id']) && (int)$order['customer_id'] === (int)$_SESSION['customer_id'];
    $token   = $_GET['t'] ?? $_POST['confirm_token_get'] ?? '';
    $isGuest = empty($_SESSION['customer_id']) && !empty($token) && !empty($order['confirm_token'])
               && hash_equals($order['confirm_token'], $token);
    if (!$isOwner && !$isGuest) {
        http_response_code(403);
        exit('Accès refusé.');
    }
    // Vérification CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Token invalide.');
    }
    $senderPhone = trim($_POST['sender_phone'] ?? '');
    if (!$senderPhone) {
        $confirmError = 'Veuillez entrer votre numéro de téléphone.';
    } elseif (!preg_match('/^\+?[\d\s\-\.]{6,20}$/', $senderPhone)) {
        $confirmError = 'Numéro de téléphone invalide.';
    } else {
        $db->prepare("UPDATE orders SET sender_phone=?, status='confirmed', payment_status='pending_verification' WHERE order_number=?")
           ->execute([$senderPhone, $orderNumber]);
        $db->prepare("INSERT INTO delivery_tracking (order_id, status, note) VALUES (?,?,?)")
           ->execute([$order['id'], 'confirmed', 'Paiement ' . strtoupper($method) . ' déclaré — numéro expéditeur : ' . $senderPhone . '. En attente de vérification.']);

        // Envoyer l'email de confirmation maintenant que le paiement est déclaré
        require_once __DIR__ . '/config/mailer.php';
        $items = $db->prepare("SELECT product_name, size, quantity, unit_price FROM order_items WHERE order_id=?");
        $items->execute([$order['id']]);
        $orderForEmail = [
            'order_number'    => $orderNumber,
            'total_amount'    => $order['total_amount'],
            'delivery_fee'    => $order['delivery_fee'],
            'delivery_address'=> $order['delivery_address'],
            'delivery_city'   => $order['delivery_city'],
            'payment_method'  => $method,
            'sender_phone'    => $senderPhone,
        ];
        emailOrderConfirmation($order['email'], $order['first_name'], $orderForEmail, $items->fetchAll());

        $confirmMsg = 'success';
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $stmt = $db->prepare("SELECT o.*, c.first_name, c.last_name, c.email FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.order_number=?");
        $stmt->execute([$orderNumber]);
        $order = $stmt->fetch();
        $isPaid = false;
    }
}
?>

<div class="container" style="padding:60px 20px; max-width:700px; margin:0 auto;">
    <div style="background:#fff; padding:clamp(20px,5vw,48px); box-shadow:0 4px 40px rgba(0,0,0,0.07);">

    <?php if ($isPaid): ?>
        <!-- ✅ PAIEMENT CONFIRMÉ -->
        <div style="text-align:center; margin-bottom:32px;">
            <div style="font-size:3.5rem; margin-bottom:12px;">✅</div>
            <div style="font-size:0.78rem; font-weight:700; letter-spacing:0.15em; color:var(--gold); text-transform:uppercase; margin-bottom:8px;"><?= htmlspecialchars($orderNumber) ?></div>
            <h1 style="font-family:'Cormorant Garamond',serif; font-size:2rem; font-weight:400; margin-bottom:12px;">Paiement confirmé !</h1>
            <p style="color:var(--text-muted); font-size:1rem; line-height:1.8;">
                Merci <strong><?= htmlspecialchars($order['first_name']) ?></strong> ! Votre paiement a été reçu.<br>
                Nos artisans vont commencer la confection de votre commande.
            </p>
        </div>

    <?php elseif ($order && $order['payment_status'] === 'pending_verification'): ?>
        <!-- ⏳ EN ATTENTE DE VÉRIFICATION -->
        <div style="text-align:center; margin-bottom:32px;">
            <div style="font-size:3.5rem; margin-bottom:12px;">⏳</div>
            <div style="font-size:0.78rem; font-weight:700; letter-spacing:0.15em; color:var(--gold); text-transform:uppercase; margin-bottom:8px;"><?= htmlspecialchars($orderNumber) ?></div>
            <h1 style="font-family:'Cormorant Garamond',serif; font-size:2rem; font-weight:400; margin-bottom:12px;">Paiement en vérification</h1>
            <p style="color:var(--text-muted); font-size:1rem; line-height:1.8;">
                Votre paiement est en cours de vérification par notre équipe.<br>
                Vous recevrez une confirmation par email sous 24h.
            </p>
        </div>
        <div style="background:#fffbf0; border:1px solid rgba(200,146,26,0.3); padding:16px 20px; font-size:0.9rem; color:#7a6248; margin-bottom:28px;">
            📸 Si ce n'est pas encore fait, envoyez la capture d'écran de votre paiement par WhatsApp au <strong><?= htmlspecialchars($method === 'orange_money' ? $omNumber : $waveNumber) ?></strong>
        </div>

    <?php elseif ($order && !$isPaid): ?>
        <!-- 💳 PAGE DE PAIEMENT -->
        <div style="text-align:center; margin-bottom:32px;">
            <div style="font-size:3rem; margin-bottom:12px;">🛍️</div>
            <div style="font-size:0.78rem; font-weight:700; letter-spacing:0.15em; color:var(--gold); text-transform:uppercase; margin-bottom:8px;"><?= htmlspecialchars($orderNumber) ?></div>
            <h1 style="font-family:'Cormorant Garamond',serif; font-size:2rem; font-weight:400; margin-bottom:8px;">Finalisez votre paiement</h1>
            <p style="color:var(--text-muted); font-size:1rem;">
                Montant à payer : <strong style="font-size:1.5rem; color:var(--dark);"><?= number_format($order['total_amount'], 0, ',', ' ') ?> €</strong>
            </p>
        </div>

        <?php if ($confirmMsg === 'success'): ?>
        <div style="background:#f0fff4; border:1px solid #9ae6b4; color:#276749; padding:16px 20px; margin-bottom:24px; font-size:0.95rem;">
            ✓ Votre paiement a été enregistré. Notre équipe va vérifier le transfert sous 24h.
        </div>
        <?php endif; ?>

        <?php if ($confirmError): ?>
        <div style="background:#fff5f5; border:1px solid #fed7d7; color:#c53030; padding:16px 20px; margin-bottom:24px; font-size:0.95rem;">
            ⚠ <?= htmlspecialchars($confirmError) ?>
        </div>
        <?php endif; ?>

        <!-- WAVE -->
        <?php if ($method === 'wave'): ?>
        <div style="border:2px solid #00b464; border-radius:8px; overflow:hidden; margin-bottom:20px;">
            <div style="background:#00b464; padding:16px 24px; display:flex; align-items:center; gap:12px;">
                <span style="font-size:1.8rem;">📱</span>
                <span style="font-weight:700; color:#fff; font-size:1.1rem;">Payer par Wave</span>
            </div>
            <div style="padding:24px;">
                <?php if ($waveApiKey): ?>
                <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:16px;">Cliquez ci-dessous pour être redirigé vers le paiement Wave sécurisé.</p>
                <button onclick="payWithWave()" id="wave-btn" style="background:#00b464;color:#fff;border:none;padding:14px 28px;font-size:1rem;font-weight:700;cursor:pointer;width:100%;border-radius:4px;">
                    📱 Payer <?= number_format($order['total_amount'], 0, ',', ' ') ?> € avec Wave
                </button>
                <?php else: ?>
                <div style="background:#f0fff8; border:1px solid #9ae6b4; padding:16px 20px; margin-bottom:20px; border-radius:4px;">
                    <div style="font-size:0.85rem; color:#555; margin-bottom:4px;">Envoyez exactement :</div>
                    <div style="font-size:clamp(1.4rem,5vw,2rem); font-weight:700; color:#00b464;"><?= number_format($order['total_amount'], 0, ',', ' ') ?> €</div>
                    <div style="height:1px; background:#d4f0e4; margin:12px 0;"></div>
                    <div style="font-size:0.8rem; color:#555; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:6px;">Destinataire Wave</div>
                    <?php if($waveOwner): ?>
                    <div style="font-size:1rem; font-weight:700; color:#1a1a1a; margin-bottom:4px;">👤 <?= htmlspecialchars($waveOwner) ?></div>
                    <?php endif; ?>
                    <div style="font-size:1.4rem; font-weight:700; color:#00b464; letter-spacing:0.05em; margin-bottom:8px; overflow-wrap:break-word;"><?= htmlspecialchars($waveNumber) ?></div>
                    <div style="font-size:0.8rem; color:#888;">Référence : <strong><?= htmlspecialchars($orderNumber) ?></strong></div>
                </div>
                <form method="POST">
                    <input type="hidden" name="confirm_mobile_payment" value="1">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="confirm_token_get" value="<?= htmlspecialchars($_GET['t'] ?? '') ?>">
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.82rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px; color:var(--dark);">Votre numéro Wave ayant effectué le transfert *</label>
                        <input type="tel" name="sender_phone" placeholder="Ex: +221 77 000 00 00" required
                               style="width:100%; padding:14px 16px; border:1.5px solid #e0d8ce; font-family:inherit; font-size:1rem; outline:none; box-sizing:border-box; border-radius:4px;">
                    </div>
                    <button type="submit" style="background:#00b464; color:#fff; border:none; padding:14px 28px; font-size:1rem; font-weight:700; cursor:pointer; width:100%; border-radius:4px;">
                        ✓ Confirmer mon paiement Wave
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ORANGE MONEY -->
        <?php if ($method === 'orange_money'): ?>
        <div style="border:2px solid #ff8c00; border-radius:8px; overflow:hidden; margin-bottom:20px;">
            <div style="background:#ff8c00; padding:16px 24px; display:flex; align-items:center; gap:12px;">
                <span style="font-size:1.8rem;">📱</span>
                <span style="font-weight:700; color:#fff; font-size:1.1rem;">Payer par Orange Money</span>
            </div>
            <div style="padding:24px;">
                <div style="background:#fff9f0; border:1px solid #fbd38d; padding:16px 20px; margin-bottom:20px; border-radius:4px;">
                    <div style="font-size:0.85rem; color:#555; margin-bottom:4px;">Envoyez exactement :</div>
                    <div style="font-size:clamp(1.4rem,5vw,2rem); font-weight:700; color:#ff8c00;"><?= number_format($order['total_amount'], 0, ',', ' ') ?> €</div>
                    <div style="height:1px; background:#fde8c8; margin:12px 0;"></div>
                    <div style="font-size:0.8rem; color:#555; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:6px;">Destinataire Orange Money</div>
                    <?php if($omOwner): ?>
                    <div style="font-size:1rem; font-weight:700; color:#1a1a1a; margin-bottom:4px;">👤 <?= htmlspecialchars($omOwner) ?></div>
                    <?php endif; ?>
                    <div style="font-size:1.4rem; font-weight:700; color:#ff8c00; letter-spacing:0.05em; margin-bottom:8px; overflow-wrap:break-word;"><?= htmlspecialchars($omNumber) ?></div>
                    <div style="font-size:0.8rem; color:#888;">Référence : <strong><?= htmlspecialchars($orderNumber) ?></strong></div>
                </div>
                <form method="POST">
                    <input type="hidden" name="confirm_mobile_payment" value="1">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="confirm_token_get" value="<?= htmlspecialchars($_GET['t'] ?? '') ?>">
                    <div style="margin-bottom:16px;">
                        <label style="display:block; font-size:0.82rem; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; margin-bottom:8px; color:var(--dark);">Votre numéro Orange Money ayant effectué le transfert *</label>
                        <input type="tel" name="sender_phone" placeholder="Ex: +33 6 00 00 00 00" required
                               style="width:100%; padding:14px 16px; border:1.5px solid #e0d8ce; font-family:inherit; font-size:1rem; outline:none; box-sizing:border-box; border-radius:4px;">
                    </div>
                    <button type="submit" style="background:#ff8c00; color:#fff; border:none; padding:14px 28px; font-size:1rem; font-weight:700; cursor:pointer; width:100%; border-radius:4px;">
                        ✓ Confirmer mon paiement Orange Money
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- PAYDUNYA (couvre aussi carte, wave, orange_money legacy) -->
        <?php if (in_array($method, ['paydunya', 'carte', 'wave', 'orange_money', 'stripe'])): ?>
        <div style="border:2px solid #e67e22; border-radius:8px; overflow:hidden; margin-bottom:20px;">
            <div style="background:#e67e22; padding:16px 24px; display:flex; align-items:center; gap:12px;">
                <span style="font-size:1.8rem;">🌍</span>
                <span style="font-weight:700; color:#fff; font-size:1.1rem;">Payer via PayDunya</span>
            </div>
            <div style="padding:24px;">
                <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:12px;">
                    Paiement sécurisé via PayDunya — Wave, Orange Money, Expresso, Free Money, Djamo, Carte bancaire.
                </p>
                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px;">
                    <span style="background:#f8f9fa; border:1px solid #e0d8ce; padding:6px 12px; font-size:0.8rem; font-weight:600; border-radius:4px;">Wave</span>
                    <span style="background:#f8f9fa; border:1px solid #e0d8ce; padding:6px 12px; font-size:0.8rem; font-weight:600; border-radius:4px;">Orange Money</span>
                    <span style="background:#f8f9fa; border:1px solid #e0d8ce; padding:6px 12px; font-size:0.8rem; font-weight:600; border-radius:4px;">Free Money</span>
                    <span style="background:#f8f9fa; border:1px solid #e0d8ce; padding:6px 12px; font-size:0.8rem; font-weight:600; border-radius:4px;">Expresso</span>
                    <span style="background:#f8f9fa; border:1px solid #e0d8ce; padding:6px 12px; font-size:0.8rem; font-weight:600; border-radius:4px;">Djamo</span>
                    <span style="background:#f8f9fa; border:1px solid #e0d8ce; padding:6px 12px; font-size:0.8rem; font-weight:600; border-radius:4px;">Carte bancaire</span>
                </div>
                <button onclick="payWithPaydunya()" id="paydunya-btn" style="background:#e67e22; color:#fff; border:none; padding:14px 28px; font-size:1rem; font-weight:700; cursor:pointer; width:100%; border-radius:4px;">
                    🌍 Payer <?= number_format($order['total_amount'], 0, ',', ' ') ?> € via PayDunya
                </button>
                <div style="margin-top:10px; font-size:0.75rem; color:var(--text-muted); text-align:center;">
                    🔒 Paiement 100% sécurisé — Le montant sera converti en XOF (FCFA)
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- CARTE BANCAIRE -->
        <?php if (in_array($method, ['carte', 'stripe'])): ?>
        <div style="border:2px solid #4f46e5; border-radius:8px; overflow:hidden; margin-bottom:20px;">
            <div style="background:#4f46e5; padding:16px 24px; display:flex; align-items:center; gap:12px;">
                <span style="font-size:1.8rem;">💳</span>
                <span style="font-weight:700; color:#fff; font-size:1.1rem;">Payer par carte bancaire</span>
            </div>
            <div style="padding:24px;">
                <?php if ($stripeOk): ?>
                <p style="color:var(--text-muted); font-size:0.95rem; margin-bottom:16px;">
                    Paiement 100% sécurisé via Stripe. Visa, Mastercard, American Express acceptés.
                </p>
                <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:16px;">
                    <span style="background:#f8f9fa; border:1px solid #e0d8ce; padding:6px 12px; font-size:0.8rem; font-weight:600; border-radius:4px;">VISA</span>
                    <span style="background:#f8f9fa; border:1px solid #e0d8ce; padding:6px 12px; font-size:0.8rem; font-weight:600; border-radius:4px;">Mastercard</span>
                    <span style="background:#f8f9fa; border:1px solid #e0d8ce; padding:6px 12px; font-size:0.8rem; font-weight:600; border-radius:4px;">Amex</span>
                </div>
                <button onclick="payWithStripe()" id="stripe-btn" style="background:#4f46e5; color:#fff; border:none; padding:14px 28px; font-size:1rem; font-weight:700; cursor:pointer; width:100%; border-radius:4px;">
                    🔒 Payer <?= number_format($order['total_amount'], 0, ',', ' ') ?> € par carte
                </button>
                <div style="margin-top:10px; font-size:0.75rem; color:var(--text-muted); text-align:center;">
                    🔒 Vos données bancaires sont chiffrées et ne sont jamais stockées sur notre site
                </div>
                <?php else: ?>
                <p style="color:#c53030; font-size:0.95rem;">Le paiement par carte n'est pas encore configuré. Veuillez choisir un autre mode de paiement ou nous contacter.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- ESPÈCES -->
        <?php if ($method === 'cash'): ?>
        <div style="border:2px solid #38a169; border-radius:8px; overflow:hidden; margin-bottom:20px;">
            <div style="background:#38a169; padding:16px 24px; display:flex; align-items:center; gap:12px;">
                <span style="font-size:1.8rem;">💵</span>
                <span style="font-weight:700; color:#fff; font-size:1.1rem;">Paiement à la livraison</span>
            </div>
            <div style="padding:24px;">
                <p style="color:var(--text-muted); font-size:0.95rem; line-height:1.8; margin:0;">
                    Vous payez <strong style="color:var(--dark); font-size:1.1rem;"><?= number_format($order['total_amount'], 0, ',', ' ') ?> €</strong> en espèces directement au livreur ou en boutique.<br>
                    Votre commande est confirmée et sera préparée immédiatement.
                </p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (in_array($method, ['wave', 'orange_money']) && !$waveApiKey): ?>
        <div style="background:#fffbf0; border:1px solid rgba(200,146,26,0.3); padding:14px 20px; font-size:0.85rem; color:#7a6248; border-radius:4px; margin-bottom:20px;">
            📸 Après le transfert, envoyez aussi la <strong>capture d'écran</strong> par WhatsApp au <strong><?= htmlspecialchars($method === 'orange_money' ? $omNumber : $waveNumber) ?></strong>
        </div>
        <?php endif; ?>

    <?php else: ?>
        <div style="text-align:center;">
            <div style="font-size:3rem; margin-bottom:12px;">🎉</div>
            <h1 style="font-family:'Cormorant Garamond',serif; font-size:2rem; font-weight:400;">Commande enregistrée</h1>
        </div>
    <?php endif; ?>

    <!-- ÉTAPES -->
    <?php if ($paymentStatus !== 'cancelled'): ?>
    <div style="background:var(--cream-2); padding:24px; margin:28px 0;">
        <div style="font-size:0.78rem; font-weight:700; letter-spacing:0.15em; text-transform:uppercase; color:var(--gold); margin-bottom:16px;">Étapes suivantes</div>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <span style="background:var(--gold); color:var(--dark); width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.78rem; font-weight:700; flex-shrink:0;">1</span>
                <span style="font-size:0.95rem;"><?= $isPaid ? '✅ Paiement confirmé' : 'Notre équipe valide votre paiement sous 24h' ?></span>
            </div>
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <span style="background:var(--gold); color:var(--dark); width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.78rem; font-weight:700; flex-shrink:0;">2</span>
                <span style="font-size:0.95rem;">Nos artisans commencent la confection (7–14 jours)</span>
            </div>
            <div style="display:flex; gap:12px; align-items:flex-start;">
                <span style="background:var(--gold); color:var(--dark); width:26px; height:26px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:0.78rem; font-weight:700; flex-shrink:0;">3</span>
                <span style="font-size:0.95rem;">Livraison à votre adresse ou retrait en boutique</span>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div style="display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
        <a href="<?= SITE_URL ?>/suivi.php?ref=<?= urlencode($orderNumber) ?>" class="btn btn-primary">Suivre ma commande</a>
        <a href="<?= SITE_URL ?>/boutique.php" class="btn btn-dark">Continuer les achats</a>
    </div>

    </div>
</div>

<?php if ($order && in_array($method, ['paydunya','carte','wave','orange_money','stripe']) && !$isPaid): ?>
<script>
function payWithPaydunya() {
    const btn = document.getElementById('paydunya-btn');
    btn.textContent = '⏳ Redirection vers PayDunya...';
    btn.disabled = true;
    fetch('<?= SITE_URL ?>/paydunya-checkout.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'order_number=<?= urlencode($orderNumber) ?>&confirm_token=<?= urlencode($order["confirm_token"] ?? "") ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.url) {
            window.location.href = data.url;
        } else {
            alert('Erreur : ' + (data.error || 'Réessayez.'));
            btn.textContent = '🌍 Payer via PayDunya';
            btn.disabled = false;
        }
    })
    .catch(() => {
        alert('Erreur réseau. Réessayez.');
        btn.textContent = '🌍 Payer via PayDunya';
        btn.disabled = false;
    });
}
</script>
<?php endif; ?>

<?php if ($order && $method === 'wave' && $waveApiKey && !$isPaid): ?>
<script>
function payWithWave() {
    const btn = document.getElementById('wave-btn');
    btn.textContent = '⏳ Redirection...';
    btn.disabled = true;
    fetch('<?= SITE_URL ?>/wave-checkout.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'order_number=<?= urlencode($orderNumber) ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.url) {
            window.location.href = data.url;
        } else {
            alert('Erreur : ' + (data.error || 'Réessayez.'));
            btn.textContent = '📱 Payer avec Wave';
            btn.disabled = false;
        }
    });
}
</script>
<?php endif; ?>

<?php if ($order && in_array($method, ['carte','stripe']) && $stripeOk && !$isPaid): ?>
<script>
function payWithStripe() {
    const btn = document.getElementById('stripe-btn');
    btn.textContent = '⏳ Redirection...';
    btn.disabled = true;
    fetch('<?= SITE_URL ?>/stripe-checkout.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'order_number=<?= urlencode($orderNumber) ?>'
    })
    .then(r => r.json())
    .then(data => {
        if (data.url) {
            window.location.href = data.url;
        } else {
            alert('Erreur : ' + (data.error || 'Réessayez.'));
            btn.textContent = '🔒 Payer par carte';
            btn.disabled = false;
        }
    });
}
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
