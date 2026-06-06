<?php
ob_start();
$pageTitle = 'Connexion';
require_once 'includes/header.php';

if (isset($_SESSION['customer_id'])) {
    header('Location: ' . SITE_URL . '/');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = 'Email et mot de passe requis.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, first_name, email, password_hash FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $customer = $stmt->fetch();

        if (!$customer || !password_verify($password, $customer['password_hash'] ?? '')) {
            $errors[] = 'Email ou mot de passe incorrect.';
        } else {
            $_SESSION['customer_id']    = $customer['id'];
            $_SESSION['customer_name']  = $customer['first_name'];
            $_SESSION['customer_email'] = $customer['email'];

            $redirect = $_SESSION['redirect_after_login'] ?? SITE_URL . '/';
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
            <h1 class="auth-title">Connexion</h1>
            <p class="auth-subtitle">Accédez à votre espace client</p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="auth-errors">
            <?php foreach ($errors as $e): ?>
            <p>⚠ <?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
        <div class="auth-success">✓ Compte créé avec succès ! Connectez-vous.</div>
        <?php endif; ?>

        <form method="POST" class="auth-form">
            <div class="form-group">
                <label>Adresse email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="fatou@example.com" autofocus required>
            </div>

            <div class="form-group">
                <label>Mot de passe *</label>
                <div class="input-password">
                    <input type="password" name="password" id="pw1" placeholder="Votre mot de passe" required>
                    <button type="button" class="pw-toggle" onclick="togglePw('pw1',this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <p style="text-align:right;margin:-8px 0 16px;font-size:13px;">
                <a href="<?= SITE_URL ?>/mot-de-passe-oublie" style="color:#c8921a;">Mot de passe oublié ?</a>
            </p>

            <button type="submit" class="btn btn-primary btn-full">Se connecter</button>

            <p class="auth-switch">Pas encore de compte ? <a href="<?= SITE_URL ?>/register.php">Créer un compte</a></p>
        </form>
    </div>
</section>

<script>
function togglePw(id, btn) {
    const input = document.getElementById(id);
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.classList.toggle('active');
}
</script>

<?php require_once 'includes/footer.php'; ?>
