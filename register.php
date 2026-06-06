<?php
ob_start();
$pageTitle = 'Créer un compte';
$useTurnstile = true;
require_once 'includes/header.php';
require_once 'config/mailer.php';
require_once 'config/turnstile.php';

$errors = [];
$success = false;

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errors[] = 'Requête invalide.';
    }
    // Turnstile
    if (!verifyTurnstile($_POST['cf-turnstile-response'] ?? '')) {
        $errors[] = 'Vérification de sécurité échouée. Réessayez.';
    }
    // Rate limit : 3 inscriptions / heure / IP
    $ipKey = 'reg_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
    if (!isset($_SESSION[$ipKey])) $_SESSION[$ipKey] = ['count' => 0, 'first' => time()];
    if (time() - $_SESSION[$ipKey]['first'] > 3600) $_SESSION[$ipKey] = ['count' => 0, 'first' => time()];
    if ($_SESSION[$ipKey]['count'] >= 3) {
        $errors[] = 'Trop d\'inscriptions. Réessayez dans 1 heure.';
    } else {
        $_SESSION[$ipKey]['count']++;
    }

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    if (!$firstName) $errors[] = 'Le prénom est requis.';
    if (!$lastName)  $errors[] = 'Le nom est requis.';
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse email invalide.';
    if (strlen($password) < 8) $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    if ($password !== $confirm) $errors[] = 'Les mots de passe ne correspondent pas.';

    if (empty($errors)) {
        $db = getDB();
        $existing = $db->prepare("SELECT id FROM customers WHERE email = ?");
        $existing->execute([$email]);
        if ($existing->fetch()) {
            $errors[] = 'Un compte existe déjà avec cet email.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO customers (first_name, last_name, email, phone, password_hash) VALUES (?,?,?,?,?)");
            $stmt->execute([$firstName, $lastName, $email, $phone ?: null, $hash]);
            $customerId = $db->lastInsertId();

            $_SESSION['customer_id']   = $customerId;
            $_SESSION['customer_name'] = $firstName;
            $_SESSION['customer_email']= $email;

            // Email de bienvenue (non bloquant)
            emailWelcome($email, $firstName, $lastName);

            $redirect = $_SESSION['redirect_after_login'] ?? SITE_URL . '/compte.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        }
    }
}
?>

<section class="auth-section">
    <div class="auth-container">
        <div class="auth-brand">
            <div class="auth-brand-logo">✦</div>
            <h1 class="auth-title">Créer un compte</h1>
            <p class="auth-subtitle">Rejoignez la communauté AfroStyle</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="auth-errors">
            <?php foreach ($errors as $e): ?>
            <p>⚠ <?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <div class="auth-row">
                <div class="form-group">
                    <label>Prénom *</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" placeholder="Fatou" required>
                </div>
                <div class="form-group">
                    <label>Nom *</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" placeholder="Diallo" required>
                </div>
            </div>

            <div class="form-group">
                <label>Adresse email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="fatou@example.com" required>
            </div>

            <div class="form-group">
                <label>Téléphone</label>
                <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" placeholder="+33 6 44 72 87 30">
            </div>

            <div class="auth-row">
                <div class="form-group">
                    <label>Mot de passe *</label>
                    <div class="input-password">
                        <input type="password" name="password" id="pw1" placeholder="Min. 6 caractères" required>
                        <button type="button" class="pw-toggle" onclick="togglePw('pw1',this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirmer *</label>
                    <div class="input-password">
                        <input type="password" name="confirm_password" id="pw2" placeholder="Répétez" required>
                        <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <div class="pw-strength" id="pwStrength">
                <div class="pw-bar"><div id="pwBar"></div></div>
                <span id="pwLabel"></span>
            </div>

            <div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>" style="margin:12px 0;"></div>
            <button type="submit" class="btn btn-primary btn-full">Créer mon compte</button>

            <p class="auth-switch">Déjà un compte ? <a href="<?= SITE_URL ?>/login.php">Se connecter</a></p>
        </form>
    </div>
</section>

<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.classList.toggle('active');
}

document.getElementById('pw1').addEventListener('input', function() {
    const val = this.value;
    const bar = document.getElementById('pwBar');
    const label = document.getElementById('pwLabel');
    let score = 0;
    if (val.length >= 6) score++;
    if (val.length >= 10) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;
    const levels = ['','Très faible','Faible','Moyen','Fort','Très fort'];
    const colors = ['','#e53e3e','#e53e3e','#f6ad55','#48bb78','#38a169'];
    bar.style.width = (score * 20) + '%';
    bar.style.background = colors[score] || '#ccc';
    label.textContent = levels[score] || '';
    label.style.color = colors[score] || '#ccc';
    document.getElementById('pwStrength').style.display = val.length ? 'block' : 'none';
});
</script>

<?php require_once 'includes/footer.php'; ?>
