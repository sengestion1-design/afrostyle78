<?php
require_once 'includes/auth.php';
$db  = getDB();
$msg = '';

// Sauvegarder
$allowedKeys = [
    'site_name','site_phone','site_email','site_address',
    'wave_number','wave_owner_name','orange_money_number','om_owner_name','bank_name','bank_iban','bank_owner',
    'wave_api_key',
    'stripe_public_key','stripe_secret_key','stripe_currency','stripe_fcfa_to_eur',
    'paydunya_master_key','paydunya_private_key','paydunya_token','paydunya_public_key',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Créer un nouveau compte admin ────────────────────────────────────────
    if (!empty($_POST['new_admin_username'])) {
        $newUser  = trim($_POST['new_admin_username']);
        $newEmail = trim($_POST['new_admin_email'] ?? '');
        $newPw    = $_POST['new_admin_password'] ?? '';
        $newPwCf  = $_POST['new_admin_password_confirm'] ?? '';

        $exists = $db->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
        $exists->execute([$newUser, $newEmail]);

        if (!$newUser || !$newPw) {
            $msg = 'error_new_admin_empty';
        } elseif (strlen($newPw) < 6) {
            $msg = 'error_new_admin_short';
        } elseif ($newPw !== $newPwCf) {
            $msg = 'error_new_admin_confirm';
        } elseif ($exists->fetch()) {
            $msg = 'error_new_admin_exists';
        } else {
            $hash = password_hash($newPw, PASSWORD_DEFAULT);
            $db->prepare("INSERT INTO admins (username, email, password) VALUES (?, ?, ?)")
               ->execute([$newUser, $newEmail ?: null, $hash]);
            $msg = 'success_new_admin';
        }
    }

    // ── Modifier compte admin ─────────────────────────────────────────────────
    if (!empty($_POST['admin_new_password'])) {
        $adminCurrentPw  = $_POST['admin_current_password'] ?? '';
        $adminNewPw      = $_POST['admin_new_password'] ?? '';
        $adminNewPwConf  = $_POST['admin_new_password_confirm'] ?? '';
        $adminNewUser    = trim($_POST['admin_username'] ?? '');

        $admin = $db->prepare("SELECT * FROM admins WHERE id = ?");
        $admin->execute([$_SESSION['admin_id']]);
        $adminRow = $admin->fetch();

        if (!$adminRow || !password_verify($adminCurrentPw, $adminRow['password'])) {
            $msg = 'error_admin_pw';
        } elseif (strlen($adminNewPw) < 6) {
            $msg = 'error_admin_short';
        } elseif ($adminNewPw !== $adminNewPwConf) {
            $msg = 'error_admin_confirm';
        } else {
            $hash = password_hash($adminNewPw, PASSWORD_DEFAULT);
            $updates = "password = ?";
            $params  = [$hash];
            if ($adminNewUser) {
                $updates .= ", username = ?";
                $params[] = $adminNewUser;
                $_SESSION['admin_username'] = $adminNewUser;
            }
            $params[] = $_SESSION['admin_id'];
            $db->prepare("UPDATE admins SET $updates WHERE id = ?")->execute($params);
            $msg = 'success_admin';
        }
    }

    // Sauvegarder config email dans mail.php
    if (!empty($_POST['mail_username'])) {
        $mailUsername  = trim($_POST['mail_username']);
        $mailPassword  = trim($_POST['mail_password']);
        $mailFromName  = trim($_POST['mail_from_name']) ?: 'AfroStyle Atelier';
        // Garder l'ancien mot de passe si le champ est vide
        if (empty($mailPassword)) {
            require_once __DIR__ . '/../config/mail.php';
            $mailPassword = MAIL_PASSWORD;
        }
        $mailContent = "<?php\n// Configuration SMTP — mise à jour depuis l'admin\ndefine('MAIL_HOST',       'smtp.gmail.com');\ndefine('MAIL_PORT',       587);\ndefine('MAIL_USERNAME',   " . var_export($mailUsername, true) . ");\ndefine('MAIL_PASSWORD',   " . var_export($mailPassword, true) . ");\ndefine('MAIL_FROM_EMAIL', " . var_export($mailUsername, true) . ");\ndefine('MAIL_FROM_NAME',  " . var_export($mailFromName, true) . ");\ndefine('MAIL_ENCRYPTION', 'tls');\n";
        file_put_contents(__DIR__ . '/../config/mail.php', $mailContent);
    }

    foreach ($allowedKeys as $key) {
        if (isset($_POST[$key])) {
            $stmt = $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key=?");
            $stmt->execute([trim($_POST[$key]), $key]);
        }
    }
    // Upload photo "Tout voir"
    if (isset($_FILES['cat_all_image']) && $_FILES['cat_all_image']['size'] > 0 && $_FILES['cat_all_image']['error'] === UPLOAD_ERR_OK) {
        $ext  = strtolower(pathinfo($_FILES['cat_all_image']['name'], PATHINFO_EXTENSION));
        $name = uniqid('cat_all_', true) . '.' . $ext;
        if (move_uploaded_file($_FILES['cat_all_image']['tmp_name'], UPLOADS_DIR . $name)) {
            $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key='cat_all_image'")->execute([$name]);
        }
    }
    if (isset($_POST['delete_cat_all_image'])) {
        $db->prepare("UPDATE settings SET setting_value='' WHERE setting_key='cat_all_image'")->execute();
    }
    $msg = 'success';
}

// Initialiser les clés PayDunya si absentes
try {
    foreach (['paydunya_master_key','paydunya_private_key','paydunya_token','paydunya_public_key'] as $k) {
        $db->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?,?)")->execute([$k, '']);
    }
} catch (Exception $e) { /* ignore */ }

// Charger tous les settings
$rows = $db->query("SELECT * FROM settings ORDER BY setting_group, id")->fetchAll();
$settings = [];
foreach ($rows as $r) $settings[$r['setting_key']] = $r;

function sv(array $settings, string $key): string {
    return htmlspecialchars($settings[$key]['setting_value'] ?? '');
}

$currentPage = 'parametres';
$adminTitle  = 'Paramètres';
require_once 'includes/admin_header.php';
?>

<div class="admin-content">

<?php if ($msg === 'success'): ?>
<div style="background:#f0fff4;border:1px solid #9ae6b4;color:#276749;padding:14px 20px;margin-bottom:24px;font-size:1rem;display:flex;align-items:center;gap:10px;">
    ✓ Paramètres enregistrés avec succès.
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">

<div style="display:grid; grid-template-columns:1fr 1fr; gap:28px; align-items:start;">

  <!-- COLONNE GAUCHE -->
  <div style="display:flex; flex-direction:column; gap:24px;">

    <!-- PAIEMENTS MOBILES -->
    <div class="admin-card">
      <div style="font-size:1.1rem;font-weight:700;color:var(--dark);margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #f0ebe0;">
        📱 Paiements mobiles & virement
      </div>
      <div class="admin-form">
        <div style="margin-bottom:16px;">
          <label>Numéro Wave</label>
          <input type="text" name="wave_number" value="<?= sv($settings,'wave_number') ?>" placeholder="+221 77 000 00 00">
        </div>
        <div style="margin-bottom:16px;">
          <label>Nom du titulaire Wave</label>
          <input type="text" name="wave_owner_name" value="<?= sv($settings,'wave_owner_name') ?>" placeholder="Ex: AfroStyle Atelier">
          <small style="color:var(--muted);font-size:0.82rem;">Affiché au client lors du paiement Wave</small>
        </div>
        <div style="margin-bottom:16px;">
          <label>Numéro Orange Money</label>
          <input type="text" name="orange_money_number" value="<?= sv($settings,'orange_money_number') ?>" placeholder="+221 77 000 00 00">
        </div>
        <div style="margin-bottom:16px;">
          <label>Nom du titulaire Orange Money</label>
          <input type="text" name="om_owner_name" value="<?= sv($settings,'om_owner_name') ?>" placeholder="Ex: AfroStyle Atelier">
          <small style="color:var(--muted);font-size:0.82rem;">Affiché au client lors du paiement Orange Money</small>
        </div>
        <div style="margin-bottom:16px;">
          <label>Banque</label>
          <input type="text" name="bank_name" value="<?= sv($settings,'bank_name') ?>" placeholder="CBAO Dakar">
        </div>
        <div style="margin-bottom:16px;">
          <label>Titulaire du compte</label>
          <input type="text" name="bank_owner" value="<?= sv($settings,'bank_owner') ?>" placeholder="AfroStyle Atelier">
        </div>
        <div style="margin-bottom:0;">
          <label>IBAN / RIB</label>
          <input type="text" name="bank_iban" value="<?= sv($settings,'bank_iban') ?>" placeholder="FR76 0000 0000 0000 0000 0000 000">
        </div>
      </div>
    </div>

  </div>

  <!-- COLONNE DROITE -->
  <div style="display:flex; flex-direction:column; gap:24px;">

    <!-- INFOS SITE -->
    <div class="admin-card">
      <div style="font-size:1.1rem;font-weight:700;color:var(--dark);margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #f0ebe0;">
        🏪 Informations du site
      </div>
      <div class="admin-form">
        <div style="margin-bottom:16px;">
          <label>Nom du site</label>
          <input type="text" name="site_name" value="<?= sv($settings,'site_name') ?>" placeholder="AfroStyle">
        </div>
        <div style="margin-bottom:16px;">
          <label>Téléphone</label>
          <input type="text" name="site_phone" value="<?= sv($settings,'site_phone') ?>" placeholder="+33 6 44 72 87 30">
        </div>
        <div style="margin-bottom:16px;">
          <label>Email de contact</label>
          <input type="email" name="site_email" value="<?= sv($settings,'site_email') ?>" placeholder="contact@afrostyle.sn">
        </div>
        <div style="margin-bottom:0;">
          <label>Adresse</label>
          <input type="text" name="site_address" value="<?= sv($settings,'site_address') ?>" placeholder="Dakar, Sénégal">
        </div>
      </div>
    </div>

    <!-- EMAIL SMTP -->
    <div class="admin-card">
      <div style="font-size:1.1rem;font-weight:700;color:var(--dark);margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #f0ebe0;">
        ✉️ Configuration Email (Gmail SMTP)
      </div>
      <?php
        $mailFile = __DIR__ . '/../config/mail.php';
        $mailCfg  = [];
        if (file_exists($mailFile)) {
            $lines = file($mailFile);
            foreach ($lines as $line) {
                if (preg_match("/define\('(MAIL_\w+)',\s*'([^']*)'\)/", $line, $m)) {
                    $mailCfg[$m[1]] = $m[2];
                }
            }
        }
      ?>
      <div class="admin-form">
        <div style="margin-bottom:16px;">
          <label>Adresse Gmail expéditeur</label>
          <input type="email" name="mail_username"
                 value="<?= htmlspecialchars($mailCfg['MAIL_USERNAME'] ?? '') ?>"
                 placeholder="votre@gmail.com">
          <small style="color:var(--muted);font-size:0.85rem;">Doit être un compte Gmail avec accès SMTP activé.</small>
        </div>
        <div style="margin-bottom:16px;">
          <label>Mot de passe d'application Google</label>
          <div style="position:relative;">
            <input type="password" name="mail_password" id="mail_pass"
                   placeholder="Laisser vide pour ne pas changer"
                   autocomplete="new-password">
            <button type="button" onclick="document.getElementById('mail_pass').type = document.getElementById('mail_pass').type==='password'?'text':'password'"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);">👁</button>
          </div>
          <small style="color:var(--muted);font-size:0.85rem;">
            Générer sur <strong>myaccount.google.com</strong> → Sécurité → Mots de passe des applications.
          </small>
        </div>
        <div style="margin-bottom:0;">
          <label>Nom expéditeur</label>
          <input type="text" name="mail_from_name"
                 value="<?= htmlspecialchars($mailCfg['MAIL_FROM_NAME'] ?? 'AfroStyle Atelier') ?>"
                 placeholder="AfroStyle Atelier">
        </div>
        <div style="margin-top:16px;background:#fffbf0;border:1px solid rgba(200,146,26,0.2);padding:14px 16px;font-size:0.88rem;color:#7a6248;">
          📧 Expéditeur actuel : <strong><?= htmlspecialchars($mailCfg['MAIL_FROM_EMAIL'] ?? 'non configuré') ?></strong>
        </div>
        <div style="margin-top:16px;background:#e8f9f0;border:1px solid rgba(0,180,100,0.2);padding:14px 16px;font-size:0.88rem;color:#276749;">
          🔗 URL Webhook Wave à configurer sur wave.com/business :<br>
          <strong style="word-break:break-all;"><?= SITE_URL ?>/wave-webhook.php</strong>
        </div>
      </div>
    </div>

    <!-- WAVE BUSINESS API -->
    <div class="admin-card">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #f0ebe0;">
        <div style="background:#00b464;color:#fff;padding:8px 14px;font-size:1.1rem;font-weight:700;border-radius:4px;">Wave</div>
        <div>
          <div style="font-size:1.1rem;font-weight:700;color:var(--dark);">Wave Business API</div>
          <div style="font-size:0.88rem;color:var(--muted);">Paiement automatique via Wave</div>
        </div>
        <div style="margin-left:auto;">
          <?php $waveApiKey = $settings['wave_api_key']['setting_value'] ?? ''; ?>
          <span style="padding:4px 14px;font-size:0.82rem;font-weight:700;border-radius:20px;
            <?= $waveApiKey ? 'background:rgba(0,180,100,0.1);color:#276749;' : 'background:rgba(200,200,200,0.2);color:#999;' ?>">
            <?= $waveApiKey ? '🟢 ACTIF' : '⚪ NON CONFIGURÉ' ?>
          </span>
        </div>
      </div>
      <div class="admin-form">
        <div style="margin-bottom:16px;">
          <label>Wave API Key</label>
          <div style="position:relative;">
            <input type="password" name="wave_api_key" id="wave_api_key"
                   value="<?= htmlspecialchars($waveApiKey) ?>"
                   placeholder="wave_sn_prod_xxxxxxxxxxxx">
            <button type="button" onclick="document.getElementById('wave_api_key').type = document.getElementById('wave_api_key').type==='password'?'text':'password'"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);">👁</button>
          </div>
          <small style="color:var(--muted);font-size:0.85rem;">Disponible sur <strong>wave.com/business</strong> → API → Clés.</small>
        </div>
        <div style="background:#e8f9f0;border:1px solid rgba(0,180,100,0.2);padding:14px 16px;font-size:0.88rem;color:#276749;">
          ℹ️ Une fois la clé ajoutée, les clients pourront payer directement par Wave ou carte bancaire sans intervention manuelle.
        </div>
      </div>
    </div>

  </div>
</div>

<!-- STRIPE + PAYDUNYA EN PLEINE LARGEUR -->
<div style="display:flex; flex-direction:column; gap:24px; margin-top:24px;">

    <!-- PAYDUNYA -->
    <div class="admin-card">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #f0ebe0;">
        <div style="background:#e67e22;color:#fff;padding:8px 14px;font-size:1.1rem;font-weight:700;border-radius:4px;">PD</div>
        <div>
          <div style="font-size:1.1rem;font-weight:700;color:var(--dark);">PayDunya</div>
          <div style="font-size:0.88rem;color:var(--muted);">Wave, Orange Money, Carte bancaire (Sénégal)</div>
        </div>
        <div style="margin-left:auto;">
          <?php $pdKey = $settings['paydunya_master_key']['setting_value'] ?? ''; ?>
          <span style="padding:4px 14px;font-size:0.82rem;font-weight:700;border-radius:20px;
            <?= $pdKey ? 'background:rgba(230,126,34,0.1);color:#e67e22;' : 'background:rgba(200,200,200,0.2);color:#999;' ?>">
            <?= $pdKey ? '🟢 ACTIF' : '⚪ NON CONFIGURÉ' ?>
          </span>
        </div>
      </div>
      <div class="admin-form">
        <div style="margin-bottom:16px;">
          <label>Clé Principale (Master Key)</label>
          <div style="position:relative;">
            <input type="password" name="paydunya_master_key" id="paydunya_master_key"
                   value="<?= sv($settings,'paydunya_master_key') ?>"
                   placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
            <button type="button" onclick="document.getElementById('paydunya_master_key').type=document.getElementById('paydunya_master_key').type==='password'?'text':'password'"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);">👁</button>
          </div>
        </div>
        <div style="margin-bottom:16px;">
          <label>Clé Privée de Production (Private Key)</label>
          <div style="position:relative;">
            <input type="password" name="paydunya_private_key" id="paydunya_private_key"
                   value="<?= sv($settings,'paydunya_private_key') ?>"
                   placeholder="live_private_xxxxxxxxxxxxxxxxxxxx">
            <button type="button" onclick="document.getElementById('paydunya_private_key').type=document.getElementById('paydunya_private_key').type==='password'?'text':'password'"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);">👁</button>
          </div>
        </div>
        <div style="margin-bottom:16px;">
          <label>Token de Production</label>
          <div style="position:relative;">
            <input type="password" name="paydunya_token" id="paydunya_token"
                   value="<?= sv($settings,'paydunya_token') ?>"
                   placeholder="xxxxxxxxxxxxxxxxxxxx">
            <button type="button" onclick="document.getElementById('paydunya_token').type=document.getElementById('paydunya_token').type==='password'?'text':'password'"
                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--muted);">👁</button>
          </div>
        </div>
        <div style="margin-bottom:16px;">
          <label>Clé Publique de Production (Public Key)</label>
          <input type="text" name="paydunya_public_key"
                 value="<?= sv($settings,'paydunya_public_key') ?>"
                 placeholder="live_public_xxxxxxxxxxxxxxxxxxxx">
        </div>
        <div style="background:#fef3e8;border:1px solid rgba(230,126,34,0.3);padding:14px 16px;font-size:0.88rem;color:#c0692a;">
          ℹ️ Récupère tes clés sur <strong>app.paydunya.com</strong> → Applications → Clés API de Production.
        </div>
      </div>
    </div>

</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:28px; align-items:start; margin-top:24px;">
  <div>
    <!-- PHOTO TOUT VOIR -->
    <div class="admin-card">
      <div style="font-size:1.1rem;font-weight:700;color:var(--dark);margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #f0ebe0;">
        🖼️ Photo carte "Toutes collections"
      </div>
      <div class="admin-form">
        <?php
        $catAllImg = $settings['cat_all_image']['setting_value'] ?? '';
        ?>
        <?php if ($catAllImg): ?>
        <div style="margin-bottom:16px;position:relative;display:inline-block;">
          <img src="<?= UPLOADS_URL . htmlspecialchars($catAllImg) ?>"
               style="width:100%;max-height:200px;object-fit:cover;border:1px solid #e0d8ce;">
          <div style="position:absolute;top:8px;right:8px;background:rgba(200,146,26,0.9);color:var(--dark);padding:4px 10px;font-size:0.8rem;font-weight:700;">✦ Tout voir</div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.95rem;color:#e53e3e;font-weight:600;text-transform:none;letter-spacing:0;margin-bottom:12px;">
          <input type="checkbox" name="delete_cat_all_image" value="1">
          Supprimer cette photo
        </label>
        <?php endif; ?>

        <div style="margin-bottom:8px;">
          <label><?= $catAllImg ? 'Remplacer la photo' : 'Ajouter une photo' ?></label>
          <input type="file" name="cat_all_image" accept="image/*">
        </div>
        <small style="color:var(--muted);font-size:0.85rem;">
          Recommandé : format portrait ou carré (ex: 600×800px)<br>
          Cette photo s'affiche derrière le titre "Tout voir" dans la section catégories.
        </small>
      </div>
    </div>

  </div>

  <div>
    <!-- AIDE WAVE -->
    <div style="background:#e8f9f0;border:1px solid rgba(0,180,100,0.2);padding:24px;">
      <p style="margin:0 0 12px;font-size:0.9rem;font-weight:700;color:#00b464;letter-spacing:0.05em;text-transform:uppercase;">Comment obtenir une clé Wave Business ?</p>
      <ol style="margin:0;padding-left:20px;font-size:0.95rem;color:#444;line-height:2;">
        <li>Créez un compte sur <strong>wave.com/fr/business</strong></li>
        <li>Vérifiez votre identité (pièce d'identité + NINEA)</li>
        <li>Allez dans <strong>Paramètres → API</strong></li>
        <li>Copiez votre <strong>clé API</strong> et collez-la ci-dessus</li>
      </ol>
      <div style="margin-top:16px;padding:12px;background:#fff;border:1px solid rgba(0,180,100,0.15);">
        <p style="margin:0;font-size:0.88rem;color:#00b464;">
          ✅ Une fois la clé ajoutée, les clients paieront automatiquement via Wave sans intervention manuelle.
        </p>
      </div>
    </div>
  </div>
</div>

<!-- BOUTON SAVE -->
<div style="margin-top:28px;display:flex;justify-content:flex-end;gap:12px;">
  <button type="submit" class="btn-admin btn-gold" style="padding:14px 40px;font-size:1rem;">
    ✓ Enregistrer tous les paramètres
  </button>
</div>

</form>
</div>

<!-- ═══ CRÉER UN COMPTE ADMIN ═══ -->
<?php
$adminList = $db->query("SELECT id, username, email, created_at FROM admins ORDER BY id")->fetchAll();
?>
<div class="admin-card" style="margin-top:32px;">
    <div class="admin-card-header">
        <div class="admin-card-title">👥 Comptes administrateurs (<?= count($adminList) ?>)</div>
    </div>

    <?php if ($msg === 'success_new_admin'): ?>
        <div class="alert alert-success">✓ Nouveau compte admin créé avec succès.</div>
    <?php elseif ($msg === 'error_new_admin_empty'): ?>
        <div class="alert alert-error">⚠ Identifiant et mot de passe requis.</div>
    <?php elseif ($msg === 'error_new_admin_short'): ?>
        <div class="alert alert-error">⚠ Le mot de passe doit contenir au moins 6 caractères.</div>
    <?php elseif ($msg === 'error_new_admin_confirm'): ?>
        <div class="alert alert-error">⚠ Les mots de passe ne correspondent pas.</div>
    <?php elseif ($msg === 'error_new_admin_exists'): ?>
        <div class="alert alert-error">⚠ Un compte avec cet identifiant ou cet email existe déjà.</div>
    <?php endif; ?>

    <!-- Liste des admins -->
    <table class="admin-table" style="margin-bottom:24px;">
        <thead><tr><th>Identifiant</th><th>Email</th><th>Créé le</th></tr></thead>
        <tbody>
            <?php foreach ($adminList as $a): ?>
            <tr>
                <td><strong><?= htmlspecialchars($a['username']) ?></strong>
                    <?php if ($a['id'] == $_SESSION['admin_id']): ?>
                    <span style="background:#e8f5e9;color:#2e7d32;font-size:0.7rem;padding:2px 8px;border-radius:4px;margin-left:8px;">Vous</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--muted);"><?= htmlspecialchars($a['email'] ?? '—') ?></td>
                <td style="color:var(--muted);"><?= $a['created_at'] ? date('d/m/Y', strtotime($a['created_at'])) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Formulaire création -->
    <p style="font-size:0.78rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);margin-bottom:16px;">Créer un nouveau compte</p>
    <form method="POST" class="admin-form">
        <div class="form-row">
            <div>
                <label>Identifiant *</label>
                <input type="text" name="new_admin_username" placeholder="admin2" required>
            </div>
            <div>
                <label>Email (optionnel)</label>
                <input type="email" name="new_admin_email" placeholder="admin2@exemple.com">
            </div>
        </div>
        <div class="form-row">
            <div>
                <label>Mot de passe *</label>
                <input type="password" name="new_admin_password" placeholder="Min. 6 caractères" required minlength="6">
            </div>
            <div>
                <label>Confirmer le mot de passe *</label>
                <input type="password" name="new_admin_password_confirm" placeholder="Répétez" required minlength="6">
            </div>
        </div>
        <button type="submit" class="btn-admin btn-dark">+ Créer le compte</button>
    </form>
</div>

<!-- ═══ COMPTE ADMIN ═══ -->
<div class="admin-card" style="margin-top:32px;">
    <div class="admin-card-header">
        <div class="admin-card-title">🔐 Compte administrateur</div>
    </div>

    <?php if ($msg === 'success_admin'): ?>
        <div class="alert alert-success">✓ Compte administrateur mis à jour avec succès.</div>
    <?php elseif ($msg === 'error_admin_pw'): ?>
        <div class="alert alert-error">⚠ Mot de passe actuel incorrect.</div>
    <?php elseif ($msg === 'error_admin_short'): ?>
        <div class="alert alert-error">⚠ Le nouveau mot de passe doit contenir au moins 6 caractères.</div>
    <?php elseif ($msg === 'error_admin_confirm'): ?>
        <div class="alert alert-error">⚠ Les nouveaux mots de passe ne correspondent pas.</div>
    <?php endif; ?>

    <form method="POST" class="admin-form">
        <div class="form-row">
            <div>
                <label>Nom d'utilisateur</label>
                <input type="text" name="admin_username"
                       value="<?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>"
                       placeholder="admin">
            </div>
            <div>
                <label>Mot de passe actuel *</label>
                <input type="password" name="admin_current_password" placeholder="••••••••" required>
            </div>
        </div>
        <div class="form-row">
            <div>
                <label>Nouveau mot de passe *</label>
                <input type="password" name="admin_new_password" placeholder="Min. 6 caractères" required minlength="6">
            </div>
            <div>
                <label>Confirmer le nouveau mot de passe *</label>
                <input type="password" name="admin_new_password_confirm" placeholder="Répétez le mot de passe" required minlength="6">
            </div>
        </div>
        <button type="submit" class="btn-admin btn-gold">Mettre à jour le compte</button>
    </form>
</div>

<script>
// Pas de JS Stripe nécessaire
</script>

<?php require_once 'includes/admin_footer.php'; ?>
