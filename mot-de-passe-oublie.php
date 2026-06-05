<?php
ob_start();
$pageTitle = 'Mot de passe oublié';
require_once 'includes/header.php';
require_once 'config/mailer.php';

$errors   = [];
$success  = '';
$step     = isset($_GET['token']) ? 2 : 1;
$token    = trim($_GET['token'] ?? '');

// ─── STEP 2 POST : Reset password ────────────────────────────────────────────
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token       = trim($_POST['token'] ?? '');
    $password    = $_POST['password'] ?? '';
    $passwordCfm = $_POST['password_confirm'] ?? '';

    if (!$token) {
        $errors[] = 'Lien de réinitialisation invalide.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    } elseif ($password !== $passwordCfm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM customers WHERE reset_token = ? AND reset_token_expires > NOW()");
        $stmt->execute([$token]);
        $customer = $stmt->fetch();

        if (!$customer) {
            $errors[] = 'Ce lien est invalide ou a expiré. Veuillez faire une nouvelle demande.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $upd  = $db->prepare("UPDATE customers SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?");
            $upd->execute([$hash, $customer['id']]);
            $success = 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.';
            $step    = 0; // show only success
        }
    }
}

// ─── STEP 1 POST : Send reset email ──────────────────────────────────────────
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Veuillez entrer une adresse email valide.';
    } else {
        $db   = getDB();
        $stmt = $db->prepare("SELECT id, first_name FROM customers WHERE email = ?");
        $stmt->execute([$email]);
        $customer = $stmt->fetch();

        // Always show success to avoid user enumeration
        if ($customer) {
            $resetToken   = bin2hex(random_bytes(32));
            $expires      = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $upd = $db->prepare("UPDATE customers SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
            $upd->execute([$resetToken, $expires, $customer['id']]);

            $resetLink = SITE_URL . '/mot-de-passe-oublie?token=' . $resetToken;
            $firstName = htmlspecialchars($customer['first_name']);

            $logoPath = __DIR__ . '/logo.jpg';
            $logoTag  = file_exists($logoPath)
                ? '<img src="cid:afrostyle_logo" alt="AfroStyle" style="height:100px;width:100px;object-fit:contain;border-radius:50%;">'
                : '<span style="color:#c8921a;font-size:24px;font-weight:bold;">AfroStyle</span>';

            $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f0e8;font-family:Georgia,serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <tr><td style="background:#1a1008;padding:36px 48px 28px;text-align:center;border-bottom:2px solid #c8921a;">
    <div style="margin-bottom:14px;">' . $logoTag . '</div>
    <h1 style="margin:0 0 4px;color:#f5f0e8;font-family:Georgia,serif;font-size:28px;font-weight:400;letter-spacing:2px;">AfroStyle</h1>
    <p style="margin:0;color:rgba(245,240,232,0.5);font-size:12px;letter-spacing:3px;">✦ DAKAR, SÉNÉGAL ✦</p>
  </td></tr>

  <tr><td style="background:#ffffff;padding:48px 48px 40px;">
    <p style="margin:0 0 8px;color:#c8921a;font-size:12px;letter-spacing:3px;text-transform:uppercase;">Sécurité du compte</p>
    <h2 style="margin:0 0 24px;color:#1a1008;font-family:Georgia,serif;font-size:26px;font-weight:400;">
      Réinitialisation de votre mot de passe
    </h2>

    <p style="margin:0 0 20px;color:#555;font-size:16px;line-height:1.8;">
      Bonjour <strong style="color:#1a1008;">' . $firstName . '</strong>,
    </p>
    <p style="margin:0 0 20px;color:#555;font-size:16px;line-height:1.8;">
      Vous avez demandé à réinitialiser le mot de passe de votre compte AfroStyle.
      Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.
    </p>
    <p style="margin:0 0 32px;color:#999;font-size:14px;line-height:1.7;">
      Ce lien est valable pendant <strong>1 heure</strong>. Si vous n\'avez pas fait cette demande, ignorez cet email — votre compte reste sécurisé.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:32px;">
      <tr><td align="center">
        <a href="' . $resetLink . '"
           style="display:inline-block;background:#c8921a;color:#1a1008;text-decoration:none;
                  font-size:13px;font-weight:bold;letter-spacing:3px;text-transform:uppercase;
                  padding:16px 48px;">
          Réinitialiser mon mot de passe
        </a>
      </td></tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px;">
      <tr>
        <td style="border-top:1px solid rgba(200,146,26,0.3);height:1px;"></td>
        <td style="padding:0 16px;white-space:nowrap;color:#c8921a;font-size:14px;">✦</td>
        <td style="border-top:1px solid rgba(200,146,26,0.3);height:1px;"></td>
      </tr>
    </table>

    <p style="margin:0;color:#bbb;font-size:12px;line-height:1.6;text-align:center;">
      Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
      <a href="' . $resetLink . '" style="color:#c8921a;word-break:break-all;">' . $resetLink . '</a>
    </p>
  </td></tr>

  <tr><td style="background:#1a1008;padding:32px 48px;text-align:center;">
    <p style="margin:0 0 8px;color:#c8921a;font-size:12px;letter-spacing:2px;">AfroStyle Atelier</p>
    <p style="margin:0 0 4px;color:rgba(245,240,232,0.5);font-size:12px;">📍 Dakar, Sénégal &nbsp;|&nbsp; 📞 +33 6 44 72 87 30</p>
    <p style="margin:0;color:rgba(245,240,232,0.3);font-size:11px;">© 2024 AfroStyle — Tous droits réservés</p>
  </td></tr>

</table></td></tr></table></body></html>';

            $embeds = file_exists($logoPath) ? ['afrostyle_logo' => $logoPath] : [];
            sendMail($email, $customer['first_name'], 'Réinitialisation de votre mot de passe', $html, $embeds);
        }

        $success = 'Si un compte existe avec cette adresse email, vous recevrez un lien de réinitialisation dans quelques minutes.';
    }
}
?>

<section class="auth-section">
    <div class="auth-container">
        <div class="auth-brand">
            <div class="auth-brand-logo">✦</div>
            <h1 class="auth-title">Mot de passe oublié</h1>
            <p class="auth-subtitle">
                <?php if ($step === 2): ?>Choisissez un nouveau mot de passe<?php else: ?>Recevez un lien de réinitialisation<?php endif; ?>
            </p>
        </div>

        <?php if (!empty($errors)): ?>
        <div class="auth-errors">
            <?php foreach ($errors as $e): ?>
            <p>⚠ <?= htmlspecialchars($e) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="auth-success">✓ <?= htmlspecialchars($success) ?></div>
        <?php if ($step === 0): ?>
        <p style="text-align:center;margin-top:20px;">
            <a href="<?= SITE_URL ?>/login" style="color:#c8921a;">Retour à la connexion</a>
        </p>
        <?php endif; ?>
        <?php endif; ?>

        <?php if (!$success && $step === 1): ?>
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label>Adresse email *</label>
                <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                       placeholder="fatou@example.com" autofocus required>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Envoyer le lien</button>

            <p class="auth-switch">Vous avez votre mot de passe ? <a href="<?= SITE_URL ?>/login">Se connecter</a></p>
        </form>
        <?php endif; ?>

        <?php if ($step === 2 && empty($success)): ?>
        <form method="POST" class="auth-form">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-group">
                <label>Nouveau mot de passe *</label>
                <div class="input-password">
                    <input type="password" name="password" id="pw1" placeholder="Au moins 8 caractères" autofocus required>
                    <button type="button" class="pw-toggle" onclick="togglePw('pw1',this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label>Confirmer le mot de passe *</label>
                <div class="input-password">
                    <input type="password" name="password_confirm" id="pw2" placeholder="Retapez le mot de passe" required>
                    <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-full">Enregistrer le mot de passe</button>
        </form>
        <?php endif; ?>
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
