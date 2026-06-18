<?php
ob_start();
$pageTitle = 'Commander';
require_once 'includes/header.php';
require_once 'config/mailer.php';

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) { header('Location: panier.php'); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$db = getDB();
$errors = [];
$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['quantity'], $cart));

// Charger les zones de livraison actives
$siteSettings  = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$waveNumber    = $siteSettings['wave_number'] ?? '+33 6 44 72 87 30';
$omNumber      = $siteSettings['orange_money_number'] ?? '+33 6 44 72 87 30';
$bankName      = $siteSettings['bank_name'] ?? '';
$bankOwner     = $siteSettings['bank_owner'] ?? '';
$bankIban      = $siteSettings['bank_iban'] ?? '';
$waveApiKey    = $siteSettings['wave_api_key'] ?? '';
$shippingZones = $db->query("SELECT * FROM shipping_zones WHERE active=1 ORDER BY sort_order, id")->fetchAll();
$shippingById  = [];
foreach ($shippingZones as $z) $shippingById[$z['id']] = $z;

// Nombre total d'articles
$totalQty = array_sum(array_column($cart, 'quantity'));

// Zone sélectionnée
$selectedZoneId = (int)($_POST['shipping_zone_id'] ?? $shippingZones[0]['id'] ?? 0);
$selectedZone   = $shippingById[$selectedZoneId] ?? ($shippingZones[0] ?? null);

// Calcul livraison selon palier quantité
function calcShipping(array $zone, int $qty): float {
    $base = (float)$zone['price'];
    if ($base === 0.0) return 0;
    if ($qty >= 6) return round($base * (1 + (float)$zone['surcharge_6_plus'] / 100));
    if ($qty >= 3) return round($base * (1 + (float)$zone['surcharge_3_5'] / 100));
    return $base;
}
$delivery = $selectedZone ? calcShipping($selectedZone, $totalQty) : 0;

// Pré-remplir avec les infos du client connecté
// Détection pays par IP
function detectCountryByIp(): string {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $ip = trim(explode(',', $ip)[0]);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        $data = @file_get_contents("https://ipapi.co/{$ip}/country/");
        if ($data && strlen($data) === 2) return strtoupper($data);
    }
    return 'SN';
}
$detectedCountry = $_POST['country_code'] ?? $_SESSION['detected_country'] ?? null;
$_SESSION['detected_country'] = $detectedCountry;

$prefill = ['first_name'=>'','last_name'=>'','email'=>'','phone'=>'','address'=>'','city'=>'Dakar','country'=>'SN'];
if (!empty($_SESSION['customer_id'])) {
    $stmt = $db->prepare("SELECT first_name, last_name, email, phone, address, city, country FROM customers WHERE id = ?");
    $stmt->execute([$_SESSION['customer_id']]);
    $cust = $stmt->fetch();
    if ($cust) {
        $prefill['first_name'] = $cust['first_name'];
        $prefill['last_name']  = $cust['last_name'];
        $prefill['email']      = $cust['email'];
        $prefill['phone']      = $cust['phone'] ?? '';
        $prefill['address']    = $cust['address'] ?? '';
        $prefill['city']       = $cust['city'] ?: 'Dakar';
        $prefill['country']    = $cust['country'] ?? 'SN';
        // Utiliser le pays du profil si pas encore détecté
        if (!$detectedCountry) $detectedCountry = $prefill['country'];
    }
}
if (!$detectedCountry) $detectedCountry = detectCountryByIp();
// Toujours prioritiser le pays du profil client s'il est connecté
if (!empty($_SESSION['customer_id']) && !empty($prefill['country'])) {
    $detectedCountry = $prefill['country'];
}
$_SESSION['detected_country'] = $detectedCountry;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Requête invalide. Veuillez recharger la page.';
    }
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $address   = trim($_POST['address'] ?? '');
    $city      = trim($_POST['city'] ?? '');
    $deliveryMethod = $selectedZone ? $selectedZone['method_code'] : 'domicile';
    $delivery       = $selectedZone ? calcShipping($selectedZone, $totalQty) : 0;
    $paymentMethod  = $_POST['payment_method'] ?? 'cash';
    $notes     = trim($_POST['notes'] ?? '');

    if (!$firstName) $errors[] = 'Le prénom est requis.';
    if (!$lastName)  $errors[] = 'Le nom est requis.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email invalide.';
    if (!$phone)     $errors[] = 'Le téléphone est requis.';
    if (!$address)   $errors[] = 'L\'adresse est requise.';
    if (!$city)      $errors[] = 'La ville est requise.';

    if (empty($errors)) {
        try {
            // Save or reuse customer
            if (!empty($_SESSION['customer_id'])) {
                $customerId = $_SESSION['customer_id'];
                $db->prepare("UPDATE customers SET first_name=?, last_name=?, phone=?, address=?, city=? WHERE id=?")
                   ->execute([$firstName, $lastName, $phone, $address, $city, $customerId]);
            } else {
                $existing = $db->prepare("SELECT id FROM customers WHERE email=?");
                $existing->execute([$email]);
                $existingCustomer = $existing->fetch();
                if ($existingCustomer) {
                    $customerId = $existingCustomer['id'];
                    $db->prepare("UPDATE customers SET first_name=?, last_name=?, phone=?, address=?, city=? WHERE id=?")
                       ->execute([$firstName, $lastName, $phone, $address, $city, $customerId]);
                } else {
                    $stmtCust = $db->prepare("INSERT INTO customers (first_name, last_name, email, phone, address, city) VALUES (?,?,?,?,?,?)");
                    $stmtCust->execute([$firstName, $lastName, $email, $phone, $address, $city]);
                    $customerId = $db->lastInsertId();
                }
            }

            // Create order
            $orderNumber  = 'AFS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $confirmToken = bin2hex(random_bytes(32));
            $total = $subtotal + $delivery;
            $stmtOrder = $db->prepare("INSERT INTO orders (order_number, customer_id, total_amount, delivery_method, delivery_address, delivery_city, delivery_fee, payment_method, payment_status, notes, status, confirm_token) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmtOrder->execute([$orderNumber, $customerId, $total, $deliveryMethod, $address, $city, $delivery, $paymentMethod, 'unpaid', $notes, 'pending', $confirmToken]);
            $orderId = $db->lastInsertId();

            // Insert order items
            foreach ($cart as $item) {
                $stmtItem = $db->prepare("INSERT INTO order_items (order_id, product_id, product_name, size, color, color_hex, quantity, unit_price, is_custom_measure) VALUES (?,?,?,?,?,?,?,?,?)");
                $stmtItem->execute([$orderId, $item['product_id'], $item['name'], $item['size'], $item['color'] ?? null, $item['color_hex'] ?? null, $item['quantity'], $item['price'], !empty($item['is_custom']) ? 1 : 0]);
                $itemId = $db->lastInsertId();

                if (!empty($item['is_custom']) && !empty($item['measurements'])) {
                    $m = $item['measurements'];
                    $stmtM = $db->prepare("INSERT INTO measurements (order_item_id, tour_poitrine, tour_taille, tour_hanches, longueur_epaule, longueur_totale, longueur_manche, tour_cou, tour_bras, notes) VALUES (?,?,?,?,?,?,?,?,?,?)");
                    $stmtM->execute([$itemId,
                        $m['tour_poitrine'] ?: null, $m['tour_taille'] ?: null, $m['tour_hanches'] ?: null,
                        $m['longueur_epaule'] ?: null, $m['longueur_totale'] ?: null, $m['longueur_manche'] ?: null,
                        $m['tour_cou'] ?: null, $m['tour_bras'] ?: null, $m['notes_mesures'] ?: null
                    ]);
                }
            }

            // Initial tracking
            $db->prepare("INSERT INTO delivery_tracking (order_id, status, note) VALUES (?,?,?)")
               ->execute([$orderId, 'pending', 'Commande reçue et en attente de validation.']);

            // Email confirmation — uniquement pour espèces (paiement immédiat sans vérification)
            if ($paymentMethod === 'cash') {
                $orderForEmail = [
                    'order_number'    => $orderNumber,
                    'total_amount'    => $total,
                    'delivery_fee'    => $delivery,
                    'delivery_address'=> $address,
                    'delivery_city'   => $city,
                    'payment_method'  => $paymentMethod,
                    'sender_phone'    => '',
                ];
                $cartItems = array_values($cart);
                $itemsForEmail = array_map(fn($i) => [
                    'product_name' => $i['name'],
                    'size'         => $i['size'],
                    'quantity'     => $i['quantity'],
                    'unit_price'   => $i['price'],
                ], $cartItems);
                emailOrderConfirmation($email, $firstName, $orderForEmail, $itemsForEmail);
            }

            // Clear cart & redirect
            $_SESSION['cart'] = [];
            $_SESSION['last_order'] = ['number' => $orderNumber, 'total' => $total, 'name' => "$firstName $lastName"];

            ob_end_clean();
            header('Location: ' . SITE_URL . '/confirmation.php?order=' . $orderNumber . '&t=' . $confirmToken);
            exit;

        } catch (PDOException $e) {
            $errors[] = 'Erreur lors de la commande : ' . $e->getMessage();
        }
    }
}

$total = $subtotal + $delivery;
?>

<div class="container">
    <div class="breadcrumb">
        <a href="<?= SITE_URL ?>">Accueil</a><span>›</span>
        <a href="panier.php">Panier</a><span>›</span>
        <span class="current">Commander</span>
    </div>
    <h1 style="font-family:'Cormorant Garamond',serif; font-size:2.4rem; font-weight:400; margin-bottom:40px;">Finaliser la <em style="color:var(--gold);">commande</em></h1>

    <?php if(!empty($errors)): ?>
    <div class="alert alert-error"><?= implode('<br>', array_map('htmlspecialchars', $errors)) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="checkout-grid">
            <!-- FORM -->
            <div>
                <div class="form-section">
                    <div class="form-section-title">Informations personnelles</div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Prénom *</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? $prefill['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Nom *</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? $prefill['last_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $prefill['email']) ?>" <?= !empty($_SESSION['customer_id']) ? 'readonly style="background:#f5f0e8;color:#888;cursor:not-allowed;"' : '' ?> required>
                        </div>
                        <div class="form-group">
                            <label>Téléphone *</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? $prefill['phone']) ?>" placeholder="+33 6 44 72 87 30" required>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Mode de livraison</div>

                    <!-- PAYS -->
                    <div class="form-group" style="margin-bottom:20px;">
                        <label>Votre pays *</label>
                        <select id="country_select" name="country_code" onchange="filterShippingZones(this.value)" style="width:100%;padding:14px 16px;border:1.5px solid #e0d8ce;font-family:inherit;font-size:1rem;background:#fff;outline:none;">
                            <optgroup label="🇸🇳 Sénégal">
                                <option value="SN" <?= $detectedCountry==='SN'?'selected':'' ?>>🇸🇳 Sénégal</option>
                            </optgroup>
                            <optgroup label="🌍 Afrique">
                                <option value="CI" <?= $detectedCountry==='CI'?'selected':'' ?>>🇨🇮 Côte d'Ivoire</option>
                                <option value="ML" <?= $detectedCountry==='ML'?'selected':'' ?>>🇲🇱 Mali</option>
                                <option value="GN" <?= $detectedCountry==='GN'?'selected':'' ?>>🇬🇳 Guinée</option>
                                <option value="MR" <?= $detectedCountry==='MR'?'selected':'' ?>>🇲🇷 Mauritanie</option>
                                <option value="GH" <?= $detectedCountry==='GH'?'selected':'' ?>>🇬🇭 Ghana</option>
                                <option value="CM" <?= $detectedCountry==='CM'?'selected':'' ?>>🇨🇲 Cameroun</option>
                            </optgroup>
                            <optgroup label="🇪🇺 Europe">
                                <option value="FR" <?= $detectedCountry==='FR'?'selected':'' ?>>🇫🇷 France</option>
                                <option value="BE" <?= $detectedCountry==='BE'?'selected':'' ?>>🇧🇪 Belgique</option>
                                <option value="CH" <?= $detectedCountry==='CH'?'selected':'' ?>>🇨🇭 Suisse</option>
                                <option value="DE" <?= $detectedCountry==='DE'?'selected':'' ?>>🇩🇪 Allemagne</option>
                                <option value="GB" <?= $detectedCountry==='GB'?'selected':'' ?>>🇬🇧 Royaume-Uni</option>
                                <option value="ES" <?= $detectedCountry==='ES'?'selected':'' ?>>🇪🇸 Espagne</option>
                                <option value="IT" <?= $detectedCountry==='IT'?'selected':'' ?>>🇮🇹 Italie</option>
                            </optgroup>
                            <optgroup label="🌎 Amérique">
                                <option value="US" <?= $detectedCountry==='US'?'selected':'' ?>>🇺🇸 États-Unis</option>
                                <option value="CA" <?= $detectedCountry==='CA'?'selected':'' ?>>🇨🇦 Canada</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- OPTIONS LIVRAISON DYNAMIQUES -->
                    <div id="shipping-options">
                    <?php foreach ($shippingZones as $z):
                        // Logo transporteur ou emoji selon le nom
                        $carrierLogos = [
                            'Colissimo'     => '<img src="'.SITE_URL.'/assets/logocolissimo.webp" alt="Colissimo" style="width:52px;height:52px;object-fit:contain;">',
                            'Chronopost'    => '<img src="'.SITE_URL.'/assets/logochronoposte.png" alt="Chronopost" style="width:52px;height:52px;object-fit:contain;">',
                            'Mondial Relay' => '<img src="'.SITE_URL.'/assets/logomondialrelay.png" alt="Mondial Relay" style="width:52px;height:52px;object-fit:contain;">',
                        ];
                        $methodName = trim($z['method']);
                        $typeIcon = $carrierLogos[$methodName] ?? (['local'=>'🏪','national'=>'🚚','international'=>'✈️'][$z['zone_type']] ?? '📦');
                        $isSelected = $z['id'] === $selectedZoneId;
                        $countries = $z['countries'] ? array_map('trim', explode(',', $z['countries'])) : [];
                        $dataCountries = $z['countries'] ? htmlspecialchars($z['countries']) : 'ALL';
                    ?>
                    <div class="delivery-option shipping-opt"
                         data-countries="<?= $dataCountries ?>"
                         data-price="<?= $z['price'] ?>"
                         data-zone-id="<?= $z['id'] ?>">
                        <input type="radio" name="shipping_zone_id"
                               id="zone_<?= $z['id'] ?>"
                               value="<?= $z['id'] ?>"
                               <?= $isSelected ? 'checked' : '' ?>
                               onchange="updateDeliveryPrice(<?= $z['id'] ?>)">
                        <label for="zone_<?= $z['id'] ?>">
                            <span class="pay-icon"><?= $typeIcon ?></span>
                            <span class="pay-details">
                                <strong><?= htmlspecialchars($z['method']) ?></strong>
                                <small><?= $z['price'] > 0 ? number_format($z['price'],0,',',' ').' €' : '<span style="color:#38a169;">Gratuit</span>' ?><?= $z['delay'] ? ' · '.htmlspecialchars($z['delay']) : '' ?><?= $z['description'] ? ' — '.htmlspecialchars($z['description']) : '' ?></small>
                            </span>
                        </label>
                        <div class="check-icon" style="display:none; width:24px; height:24px; border-radius:50%; background:#38a169; color:#fff; align-items:center; justify-content:center; font-size:0.85rem; flex-shrink:0; margin-left:auto;">✓</div>
                    </div>
                    <?php endforeach; ?>
                    </div>
                    <div id="no-shipping" style="display:none; padding:16px; background:#fff8f0; border:1px solid #f6ad55; color:#c05621; font-size:0.95rem;">
                        ⚠ Aucune option de livraison disponible pour ce pays. Contactez-nous au +33 6 44 72 87 30.
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section-title">Adresse de livraison</div>
                    <div class="form-grid">
                        <div class="form-group full">
                            <label>Adresse complète *</label>
                            <input type="text" name="address" value="<?= htmlspecialchars($_POST['address'] ?? $prefill['address']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Ville *</label>
                            <input type="text" name="city" value="<?= htmlspecialchars($_POST['city'] ?? $prefill['city']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Notes de livraison</label>
                            <input type="text" name="delivery_notes" placeholder="Quartier, repère...">
                        </div>
                        <div class="form-group full">
                            <label>Commentaire sur la commande</label>
                            <textarea name="notes" rows="3" placeholder="Couleur souhaitée, occasion spéciale..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- MODE DE PAIEMENT -->
                <div class="form-section">
                    <div class="form-section-title">Mode de paiement</div>
                    <p style="color:var(--text-muted);font-size:0.95rem;margin-bottom:16px;">Choisissez votre mode de paiement. Vous effectuerez le paiement à l'étape suivante.</p>
                    <div class="payment-options" id="payment-options">
                        <div class="payment-option">
                            <input type="radio" name="payment_method" id="pay_paydunya" value="paydunya" checked>
                            <label for="pay_paydunya">
                                <span class="pay-icon">🌍</span>
                                <span class="pay-details">
                                    <strong>PayDunya</strong>
                                    <small>Wave, Orange Money, Carte bancaire & plus</small>
                                </span>
                            </label>
                            <div class="check-icon" style="display:none; width:24px; height:24px; border-radius:50%; background:#38a169; color:#fff; align-items:center; justify-content:center; font-size:0.85rem; flex-shrink:0; margin-left:auto;">✓</div>
                        </div>
                        <div class="payment-option">
                            <input type="radio" name="payment_method" id="pay_cash" value="cash">
                            <label for="pay_cash">
                                <span class="pay-icon">💵</span>
                                <span class="pay-details">
                                    <strong>Espèces à la livraison</strong>
                                    <small>Paiement à la réception</small>
                                </span>
                            </label>
                            <div class="check-icon" style="display:none; width:24px; height:24px; border-radius:50%; background:#38a169; color:#fff; align-items:center; justify-content:center; font-size:0.85rem; flex-shrink:0; margin-left:auto;">✓</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ORDER SUMMARY -->
            <div>
                <div class="cart-summary" style="position:sticky; top:100px;">
                    <div style="font-size:0.72rem; font-weight:700; letter-spacing:0.15em; text-transform:uppercase; color:var(--gold); margin-bottom:24px; padding-bottom:12px; border-bottom:1px solid rgba(200,146,26,0.2);">Ma commande</div>
                    <?php foreach($cart as $item): ?>
                    <div style="display:flex; gap:12px; margin-bottom:16px; padding-bottom:16px; border-bottom:1px solid rgba(0,0,0,0.06);">
                        <div style="width:56px; height:68px; background:var(--cream-2); overflow:hidden; flex-shrink:0;">
                            <?php if($item['image']): ?>
                            <img src="<?= UPLOADS_URL . htmlspecialchars($item['image']) ?>" style="width:100%; height:100%; object-fit:cover;">
                            <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:1.5rem;">👗</div>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1; min-width:0;">
                            <div style="font-size:0.82rem; font-weight:600; line-height:1.3;"><?= htmlspecialchars($item['name']) ?></div>
                            <div style="font-size:0.7rem; color:var(--text-muted); margin-top:2px;">Taille: <?= htmlspecialchars($item['size']) ?> · Qté: <?= $item['quantity'] ?></div>
                            <?php if (!empty($item['color'])): ?>
                            <div style="display:flex;align-items:center;gap:5px;margin-top:3px;">
                                <?php if (!empty($item['color_hex'])): ?>
                                <span style="width:12px;height:12px;border-radius:50%;background:<?= htmlspecialchars($item['color_hex']) ?>;border:1px solid rgba(0,0,0,0.2);display:inline-block;"></span>
                                <?php endif; ?>
                                <span style="font-size:0.68rem;color:var(--text-muted);"><?= htmlspecialchars($item['color']) ?></span>
                            </div>
                            <?php endif; ?>
                            <?php if($item['is_custom']): ?><div style="font-size:0.68rem; color:var(--gold); margin-top:2px;">Sur-mesure ✓</div><?php endif; ?>
                        </div>
                        <div style="font-size:0.85rem; font-weight:700; white-space:nowrap;"><?= number_format($item['price'] * $item['quantity'], 0, ',', ' ') ?></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="summary-row"><span>Sous-total</span><span><?= number_format($subtotal, 0, ',', ' ') ?> <?= CURRENCY ?></span></div>
                    <div class="summary-row"><span>Livraison</span><span id="summary-delivery"><?= $delivery > 0 ? number_format($delivery, 0, ',', ' ').' '.CURRENCY : '<span style="color:#38a169;">Gratuit</span>' ?></span></div>
                    <div class="summary-row summary-total" style="border-bottom:none; padding-top:16px; margin-top:8px; border-top:2px solid var(--dark);">
                        <span>Total</span><span id="summary-total"><?= number_format($total, 0, ',', ' ') ?> <?= CURRENCY ?></span>
                    </div>
                    <div style="margin-top:20px;padding:14px;background:#fdf6ec;border:1px solid #e8c97a;border-radius:6px;">
                        <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;font-size:0.85rem;line-height:1.5;">
                            <input type="checkbox" name="cgv_accepted" id="cgv_accepted" required
                                   style="margin-top:3px;accent-color:var(--primary);width:16px;height:16px;flex-shrink:0;">
                            <span>J'ai lu et j'accepte les <a href="<?= SITE_URL ?>/cgv" target="_blank" style="color:var(--primary);font-weight:700;text-decoration:underline;">Conditions Générales de Vente</a> ainsi que la <a href="<?= SITE_URL ?>/politique-confidentialite" target="_blank" style="color:var(--primary);text-decoration:underline;">Politique de confidentialité</a>. Je reconnais que les articles sur mesure ne sont pas soumis au droit de rétractation.</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-full" style="margin-top:16px;">
                        Continuer vers le paiement →
                    </button>
                    <div style="margin-top:12px; font-size:0.7rem; color:var(--text-muted); text-align:center;">
                        🔒 Paiement sécurisé — Vous effectuerez le paiement à l'étape suivante.
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const SUBTOTAL  = <?= $subtotal ?>;
const TOTAL_QTY = <?= $totalQty ?>;
const ZONES     = <?= json_encode(array_values($shippingZones)) ?>;

function calcShippingJS(zone) {
    const base = parseFloat(zone.price);
    if (base === 0) return 0;
    if (TOTAL_QTY >= 6) return Math.round(base * (1 + parseFloat(zone.surcharge_6_plus) / 100));
    if (TOTAL_QTY >= 3) return Math.round(base * (1 + parseFloat(zone.surcharge_3_5) / 100));
    return base;
}

function updateDeliveryPrice(zoneId) {
    const zone = ZONES.find(z => z.id == zoneId);
    if (!zone) return;
    const price    = calcShippingJS(zone);
    const delivery = document.getElementById('summary-delivery');
    const total    = document.getElementById('summary-total');

    if (price > 0) {
        let label = price.toLocaleString('fr-FR') + ' €';
        if (TOTAL_QTY >= 6 && parseFloat(zone.price) > 0)
            label += ' <small style="color:var(--muted)">(+' + zone.surcharge_6_plus + '%, 6+ art.)</small>';
        else if (TOTAL_QTY >= 3 && parseFloat(zone.price) > 0)
            label += ' <small style="color:var(--muted)">(+' + zone.surcharge_3_5 + '%, 3-5 art.)</small>';
        delivery.innerHTML = label;
    } else {
        delivery.innerHTML = '<span style="color:#38a169;">Gratuit</span>';
    }
    total.textContent = (SUBTOTAL + price).toLocaleString('fr-FR') + ' €';
}

function filterShippingZones(countryCode) {
    const opts = document.querySelectorAll('.shipping-opt');
    let visible = 0, firstVisible = null;

    opts.forEach(opt => {
        const zoneId   = opt.dataset.zoneId;
        const zone     = ZONES.find(z => z.id == zoneId);
        const zoneType = zone?.zone_type;
        let show = false;
        if (zoneType === 'local' || zoneType === 'national') {
            show = (countryCode === 'SN');
        } else {
            show = (countryCode !== 'SN');
        }
        opt.style.display = show ? '' : 'none';
        if (show) { visible++; if (!firstVisible) firstVisible = opt; }
    });

    if (firstVisible) {
        const radio = firstVisible.querySelector('input[type=radio]');
        radio.checked = true;
        updateDeliveryPrice(firstVisible.dataset.zoneId);
    }
    document.getElementById('no-shipping').style.display = visible === 0 ? 'block' : 'none';
}

function highlightSelected(name) {
    document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
        const opt = r.closest('.delivery-option, .payment-option');
        if (!opt) return;
        const check = opt.querySelector('.check-icon');
        if (r.checked) {
            opt.style.borderColor = '#38a169';
            opt.style.background  = 'rgba(56,161,105,0.06)';
            if (check) check.style.display = 'flex';
        } else {
            opt.style.borderColor = '';
            opt.style.background  = '';
            if (check) check.style.display = 'none';
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const countrySelect = document.getElementById('country_select');
    filterShippingZones(countrySelect.value);
    countrySelect.addEventListener('change', () => filterShippingZones(countrySelect.value));

    document.querySelectorAll('.shipping-opt input[type=radio]').forEach(r => {
        r.addEventListener('change', () => {
            updateDeliveryPrice(r.value);
            highlightSelected('shipping_zone_id');
        });
    });

    document.querySelectorAll('input[name="payment_method"]').forEach(r => {
        r.addEventListener('change', () => highlightSelected('payment_method'));
    });

    // Init au chargement
    highlightSelected('shipping_zone_id');
    highlightSelected('payment_method');
});
</script>

<?php require_once 'includes/footer.php'; ?>
