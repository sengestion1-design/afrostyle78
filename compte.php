<?php
ob_start();
$pageTitle = 'Mon compte';
require_once 'includes/header.php';

if (empty($_SESSION['customer_id'])) {
    $_SESSION['redirect_after_login'] = SITE_URL . '/compte.php';
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

$db         = getDB();
$customerId = $_SESSION['customer_id'];

$customer = $db->prepare("SELECT * FROM customers WHERE id = ?");
$customer->execute([$customerId]);
$customer = $customer->fetch();

$orders = $db->prepare("
    SELECT o.*, COUNT(oi.id) as items_count
    FROM orders o
    LEFT JOIN order_items oi ON oi.order_id = o.id
    WHERE o.customer_id = ?
    GROUP BY o.id
    ORDER BY o.created_at DESC
");
$orders->execute([$customerId]);
$orders = $orders->fetchAll();

$statusLabels = [
    'pending'       => ['label' => 'En attente',     'color' => '#f6ad55'],
    'confirmed'     => ['label' => 'Confirmée',      'color' => '#48bb78'],
    'in_production' => ['label' => 'En confection',  'color' => '#63b3ed'],
    'shipped'       => ['label' => 'Expédiée',       'color' => '#9f7aea'],
    'delivered'     => ['label' => 'Livrée',         'color' => '#38a169'],
    'cancelled'     => ['label' => 'Annulée',        'color' => '#e53e3e'],
];

// Handle profile update
$profileMsg = '';
// Verifie le jeton CSRF avant tout traitement du POST.
csrfCheck();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fn   = trim($_POST['first_name'] ?? '');
    $ln   = trim($_POST['last_name'] ?? '');
    $ph   = trim($_POST['phone'] ?? '');
    $addr    = trim($_POST['address'] ?? '');
    $city    = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? 'Sénégal');

    $stmt = $db->prepare("UPDATE customers SET first_name=?, last_name=?, phone=?, address=?, city=?, country=? WHERE id=?");
    $stmt->execute([$fn, $ln, $ph, $addr, $city, $country, $customerId]);
    $_SESSION['customer_name'] = $fn;
    $profileMsg = 'success';

    $customer = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $customer->execute([$customerId]);
    $customer = $customer->fetch();
}

// Handle password change
$pwMsg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    $stmt = $db->prepare("SELECT password_hash FROM customers WHERE id = ?");
    $stmt->execute([$customerId]);
    $row = $stmt->fetch();

    if (!password_verify($current, $row['password_hash'] ?? '')) {
        $pwMsg = 'error';
    } elseif (strlen($new) < 6) {
        $pwMsg = 'short';
    } elseif ($new !== $confirm) {
        $pwMsg = 'mismatch';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        $db->prepare("UPDATE customers SET password_hash=? WHERE id=?")->execute([$hash, $customerId]);
        $pwMsg = 'success';
    }
}
?>

<section class="compte-section">
    <div class="compte-container">

        <!-- SIDEBAR -->
        <aside class="compte-sidebar">
            <div class="compte-avatar">
                <div class="avatar-circle"><?= mb_strtoupper(mb_substr($customer['first_name'], 0, 1)) ?><?= mb_strtoupper(mb_substr($customer['last_name'], 0, 1)) ?></div>
                <div class="compte-welcome">
                    <strong><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></strong>
                    <span><?= htmlspecialchars($customer['email']) ?></span>
                </div>
            </div>
            <nav class="compte-nav">
                <a href="#commandes" class="compte-nav-item active" onclick="showTab('commandes',this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Mes commandes
                </a>
                <a href="#profil" class="compte-nav-item" onclick="showTab('profil',this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    Mon profil
                </a>
                <a href="#securite" class="compte-nav-item" onclick="showTab('securite',this)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    Sécurité
                </a>
                <a href="<?= SITE_URL ?>/logout.php" class="compte-nav-item compte-logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    Déconnexion
                </a>
            </nav>
        </aside>

        <!-- MAIN CONTENT -->
        <div class="compte-main">

            <!-- COMMANDES TAB -->
            <div id="tab-commandes" class="compte-tab active">
                <h2 class="compte-section-title">Mes commandes <span><?= count($orders) ?></span></h2>

                <?php if (empty($orders)): ?>
                <div class="compte-empty">
                    <div class="compte-empty-icon">🛍</div>
                    <p>Vous n'avez pas encore passé de commande.</p>
                    <a href="<?= SITE_URL ?>/boutique.php" class="btn btn-primary">Découvrir la boutique</a>
                </div>
                <?php else: ?>
                <div class="orders-list">
                    <?php foreach ($orders as $order):
                        $st = $statusLabels[$order['status']] ?? ['label' => $order['status'], 'color' => '#ccc'];
                    ?>
                    <div class="order-card">
                        <div class="order-card-header">
                            <div>
                                <span class="order-number"><?= htmlspecialchars($order['order_number']) ?></span>
                                <span class="order-date"><?= date('d/m/Y', strtotime($order['created_at'])) ?></span>
                            </div>
                            <span class="order-status-badge" style="color:<?= $st['color'] ?>; border-color:<?= $st['color'] ?>22; background:<?= $st['color'] ?>11;">
                                <?= $st['label'] ?>
                            </span>
                        </div>
                        <div class="order-card-body">
                            <div class="order-meta">
                                <span><?= $order['items_count'] ?> article<?= $order['items_count'] > 1 ? 's' : '' ?></span>
                                <span>•</span>
                                <span><?= ucfirst($order['delivery_method']) ?></span>
                                <?php if ($order['delivery_city']): ?>
                                <span>•</span>
                                <span><?= htmlspecialchars($order['delivery_city']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="order-total">
                                <?= number_format($order['total_amount'], 0, ',', ' ') ?> €
                            </div>
                        </div>
                        <?php if (!empty($order['sender_phone'])): ?>
                        <div style="padding:8px 0; font-size:0.82rem; color:#7a6248; border-top:1px solid rgba(0,0,0,0.06); margin-top:8px;">
                            📱 N° ayant effectué le paiement : <strong><?= htmlspecialchars($order['sender_phone']) ?></strong>
                            <?php if ($order['payment_status'] === 'pending_verification'): ?>
                            <span style="margin-left:8px; background:#ebf8ff; color:#2b6cb0; font-size:0.75rem; padding:2px 8px; border-radius:10px;">🔍 En cours de vérification</span>
                            <?php elseif ($order['payment_status'] === 'paid'): ?>
                            <span style="margin-left:8px; background:#f0fff4; color:#276749; font-size:0.75rem; padding:2px 8px; border-radius:10px;">✓ Paiement confirmé</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="order-card-footer">
                            <a href="<?= SITE_URL ?>/suivi.php?ref=<?= urlencode($order['order_number']) ?>" class="btn btn-outline btn-sm">Suivre la commande →</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- PROFIL TAB -->
            <div id="tab-profil" class="compte-tab">
                <h2 class="compte-section-title">Mon profil</h2>

                <?php if ($profileMsg === 'success'): ?>
                <div class="auth-success">✓ Profil mis à jour avec succès.</div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
<?= csrfField() ?>
                    <input type="hidden" name="update_profile" value="1">
                    <div class="auth-row">
                        <div class="form-group">
                            <label>Prénom</label>
                            <input type="text" name="first_name" value="<?= htmlspecialchars($customer['first_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Nom</label>
                            <input type="text" name="last_name" value="<?= htmlspecialchars($customer['last_name']) ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?= htmlspecialchars($customer['email']) ?>" disabled>
                        <small>L'email ne peut pas être modifié.</small>
                    </div>
                    <div class="form-group">
                        <label>Téléphone</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" placeholder="+33 6 44 72 87 30">
                    </div>
                    <div class="form-group">
                        <label>Adresse</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($customer['address'] ?? '') ?>" placeholder="Rue, quartier...">
                    </div>
                    <div class="form-group">
                        <label>Ville</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($customer['city'] ?? '') ?>" placeholder="Dakar">
                    </div>
                    <div class="form-group">
                        <label>Pays</label>
                        <select name="country" style="width:100%;padding:14px 16px;border:1.5px solid #e0d8ce;font-family:inherit;font-size:1rem;background:#fff;outline:none;border-radius:2px;">
                            <?php
                            $countries = [
                                'SN'=>'🇸🇳 Sénégal','CI'=>'🇨🇮 Côte d\'Ivoire','ML'=>'🇲🇱 Mali',
                                'GN'=>'🇬🇳 Guinée','MR'=>'🇲🇷 Mauritanie','GH'=>'🇬🇭 Ghana','CM'=>'🇨🇲 Cameroun',
                                'FR'=>'🇫🇷 France','BE'=>'🇧🇪 Belgique','CH'=>'🇨🇭 Suisse',
                                'DE'=>'🇩🇪 Allemagne','GB'=>'🇬🇧 Royaume-Uni','ES'=>'🇪🇸 Espagne','IT'=>'🇮🇹 Italie',
                                'US'=>'🇺🇸 États-Unis','CA'=>'🇨🇦 Canada',
                            ];
                            $savedCountry = $customer['country'] ?? 'Sénégal';
                            foreach ($countries as $code => $label):
                                $selected = ($savedCountry === $code || $savedCountry === $label) ? 'selected' : '';
                            ?>
                            <option value="<?= $code ?>" <?= $selected ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
                </form>
            </div>

            <!-- SECURITE TAB -->
            <div id="tab-securite" class="compte-tab">
                <h2 class="compte-section-title">Changer le mot de passe</h2>

                <?php if ($pwMsg === 'success'): ?>
                <div class="auth-success">✓ Mot de passe modifié avec succès.</div>
                <?php elseif ($pwMsg === 'error'): ?>
                <div class="auth-errors"><p>⚠ Mot de passe actuel incorrect.</p></div>
                <?php elseif ($pwMsg === 'short'): ?>
                <div class="auth-errors"><p>⚠ Le nouveau mot de passe doit contenir au moins 6 caractères.</p></div>
                <?php elseif ($pwMsg === 'mismatch'): ?>
                <div class="auth-errors"><p>⚠ Les mots de passe ne correspondent pas.</p></div>
                <?php endif; ?>

                <form method="POST" class="auth-form">
<?= csrfField() ?>
                    <input type="hidden" name="change_password" value="1">
                    <div class="form-group">
                        <label>Mot de passe actuel</label>
                        <div class="input-password">
                            <input type="password" name="current_password" id="pw0" required>
                            <button type="button" class="pw-toggle" onclick="togglePw('pw0',this)">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </div>
                    </div>
                    <div class="auth-row">
                        <div class="form-group">
                            <label>Nouveau mot de passe</label>
                            <div class="input-password">
                                <input type="password" name="new_password" id="pw1" required>
                                <button type="button" class="pw-toggle" onclick="togglePw('pw1',this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Confirmer</label>
                            <div class="input-password">
                                <input type="password" name="confirm_password" id="pw2" required>
                                <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Modifier le mot de passe</button>
                </form>
            </div>

        </div>
    </div>
</section>

<script>
function showTab(id, el) {
    document.querySelectorAll('.compte-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.compte-nav-item').forEach(n => n.classList.remove('active'));
    document.getElementById('tab-' + id).classList.add('active');
    el.classList.add('active');
    return false;
}
function togglePw(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.classList.toggle('active');
}
// Activate tab from hash
const hash = location.hash.replace('#','');
if (hash) {
    const el = document.querySelector('.compte-nav-item[href="#'+hash+'"]');
    if (el) showTab(hash, el);
}
</script>

<?php require_once 'includes/footer.php'; ?>
