<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/turnstile.php';
require_once '../config/mailer.php';

if (isset($_SESSION['admin_id'])) { header('Location: ' . ADMIN_URL . '/index.php'); exit; }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$step  = isset($_SESSION['admin_2fa_code']) ? 'verify' : 'login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Requête invalide.';

    } elseif ($step === 'verify') {
        // ── ÉTAPE 2 : vérification du code 2FA ───────────────────────────────
        $entered = trim($_POST['tfa_code'] ?? '');
        if (time() > ($_SESSION['admin_2fa_expires'] ?? 0)) {
            unset($_SESSION['admin_2fa_code'], $_SESSION['admin_2fa_expires'], $_SESSION['admin_2fa_id'], $_SESSION['admin_2fa_username']);
            $step  = 'login';
            $error = 'Le code a expiré. Veuillez vous reconnecter.';
        } elseif (!hash_equals((string)$_SESSION['admin_2fa_code'], $entered)) {
            $error = 'Code incorrect.';
        } else {
            session_regenerate_id(true);
            $_SESSION['admin_id']       = $_SESSION['admin_2fa_id'];
            $_SESSION['admin_username'] = $_SESSION['admin_2fa_username'];
            unset($_SESSION['admin_2fa_code'], $_SESSION['admin_2fa_expires'], $_SESSION['admin_2fa_id'], $_SESSION['admin_2fa_username']);
            header('Location: ' . ADMIN_URL . '/index.php');
            exit;
        }

    } else {
        // ── ÉTAPE 1 : identifiant + mot de passe ─────────────────────────────
        if (!verifyTurnstile($_POST['cf-turnstile-response'] ?? '')) {
            $error = 'Vérification de sécurité échouée. Réessayez.';
        } else {
            $ipKey = 'admin_login_' . md5($_SERVER['REMOTE_ADDR'] ?? '');
            if (!isset($_SESSION[$ipKey])) $_SESSION[$ipKey] = ['count' => 0, 'first' => time()];
            if (time() - $_SESSION[$ipKey]['first'] > 900) $_SESSION[$ipKey] = ['count' => 0, 'first' => time()];

            if ($_SESSION[$ipKey]['count'] >= 5) {
                $error = 'Trop de tentatives. Réessayez dans 15 minutes.';
            } else {
                $_SESSION[$ipKey]['count']++;
                $username = trim($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';

                $stmt = getDB()->prepare("SELECT * FROM admins WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($password, $admin['password'])) {
                    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $_SESSION['admin_2fa_code']     = $code;
                    $_SESSION['admin_2fa_expires']  = time() + 600;
                    $_SESSION['admin_2fa_id']        = $admin['id'];
                    $_SESSION['admin_2fa_username']  = $admin['username'];
                    unset($_SESSION[$ipKey]);

                    $sent = sendMail(
                        $admin['email'],
                        $admin['username'],
                        '🔐 AfroStyle Admin — Code de vérification',
                        '<div style="font-family:Arial,sans-serif;padding:32px;background:#f5f0e8;">
                        <h2 style="color:#1a1008;">Code de connexion admin</h2>
                        <p style="font-size:16px;color:#555;">Votre code de vérification :</p>
                        <p style="font-size:40px;font-weight:bold;letter-spacing:12px;color:#c8921a;background:#1a1008;padding:16px 24px;display:inline-block;">' . $code . '</p>
                        <p style="color:#999;font-size:13px;margin-top:16px;">Ce code expire dans <strong>10 minutes</strong>. Ne le partagez jamais.</p>
                        </div>'
                    );

                    if (!$sent) {
                        unset($_SESSION['admin_2fa_code'], $_SESSION['admin_2fa_expires'], $_SESSION['admin_2fa_id'], $_SESSION['admin_2fa_username']);
                        $error = 'Impossible d\'envoyer le code. Vérifiez la configuration email.';
                    } else {
                        $step = 'verify';
                    }
                } else {
                    $error = 'Identifiants incorrects.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin — AfroStyle</title>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=Cormorant+Garamond:ital,wght@0,400;1,400&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Syne',sans-serif;background:#0E0A06;min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;}
        body::before{content:'';position:fixed;inset:0;opacity:0.05;background-image:repeating-linear-gradient(45deg,#C8921A 0,#C8921A 1px,transparent 1px,transparent 20px),repeating-linear-gradient(-45deg,#C8921A 0,#C8921A 1px,transparent 1px,transparent 20px);}
        .login-box{background:#1A1208;border:1px solid rgba(200,146,26,0.25);padding:56px 48px;width:420px;max-width:90vw;position:relative;z-index:1;}
        .login-box::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:linear-gradient(90deg,transparent,#C8921A,transparent);}
        .login-logo{text-align:center;margin-bottom:40px;}
        .login-logo img{height:56px;opacity:0.9;}
        .login-title{font-family:'Cormorant Garamond',serif;font-size:1.6rem;font-weight:400;color:#FDF6EC;text-align:center;margin-bottom:8px;}
        .login-sub{font-size:1rem;color:#7A6248;text-align:center;letter-spacing:0.15em;text-transform:uppercase;margin-bottom:40px;}
        .form-group{margin-bottom:20px;}
        .form-group label{display:block;font-size:1rem;font-weight:700;letter-spacing:0.15em;text-transform:uppercase;color:#7A6248;margin-bottom:8px;}
        .form-group input{width:100%;padding:14px 16px;background:rgba(253,246,236,0.04);border:1.5px solid rgba(200,146,26,0.15);color:#FDF6EC;font-family:'Syne',sans-serif;font-size:1.1rem;outline:none;transition:all 0.3s;}
        .form-group input:focus{border-color:#C8921A;background:rgba(200,146,26,0.06);}
        .btn-login{width:100%;padding:14px;background:#C8921A;color:#0E0A06;border:none;font-family:'Syne',sans-serif;font-size:1.05rem;font-weight:700;letter-spacing:0.15em;text-transform:uppercase;cursor:pointer;transition:all 0.3s;margin-top:8px;}
        .btn-login:hover{background:#E8B84B;}
        .error{background:rgba(192,57,43,0.15);border-left:3px solid #C0392B;color:#e74c3c;padding:12px 16px;margin-bottom:24px;font-size:1.1rem;}
        .back-link{display:block;text-align:center;margin-top:24px;color:#7A6248;font-size:1rem;letter-spacing:0.1em;text-decoration:none;transition:color 0.3s;}
        .back-link:hover{color:#C8921A;}
    </style>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body>
<div class="login-box">
    <div class="login-logo">
        <img src="<?= SITE_URL ?>/logo.jpg" alt="AfroStyle">
    </div>
    <h1 class="login-title">Administration</h1>
    <p class="login-sub">Panneau de gestion AfroStyle</p>
    <?php if($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($step === 'verify'): ?>
    <p style="color:rgba(253,246,236,0.6);font-size:0.9rem;text-align:center;margin-bottom:24px;line-height:1.6;">
        Un code à 6 chiffres a été envoyé à votre adresse email.<br>
        <small style="color:rgba(253,246,236,0.4);">Expiré dans 10 minutes.</small>
    </p>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="form-group">
            <label>Code de vérification</label>
            <input type="text" name="tfa_code" autofocus required placeholder="000000"
                   maxlength="6" inputmode="numeric" autocomplete="one-time-code"
                   style="letter-spacing:0.4em;font-size:1.6rem;text-align:center;">
        </div>
        <button type="submit" class="btn-login">Valider le code →</button>
    </form>

    <?php else: ?>
    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        <div class="form-group">
            <label>Identifiant</label>
            <input type="text" name="username" autofocus required placeholder="admin">
        </div>
        <div class="form-group">
            <label>Mot de passe</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>
        <div class="cf-turnstile" data-sitekey="<?= TURNSTILE_SITE_KEY ?>" data-theme="dark" style="margin:16px 0;"></div>
        <button type="submit" class="btn-login">Connexion →</button>
    </form>
    <?php endif; ?>
    <a href="<?= SITE_URL ?>" class="back-link">← Retour à la boutique</a>
</div>
</body>
</html>
