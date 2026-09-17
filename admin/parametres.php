<?php
require_once 'includes/auth.php';
$db  = getDB();
$msg = '';

// --- DIAGNOSTIC PAYPAL TEMPORAIRE (?diagpaypal=1) — A RETIRER APRES USAGE ---
// Reserve aux admins connectes (auth.php ci-dessus). N'affiche jamais les cles.
if (isset($_GET['diagpaypal'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $s = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_group='paypal'")->fetchAll(PDO::FETCH_KEY_PAIR);

    echo "=== REGLAGES PAYPAL EN BASE ===\n";
    if (!$s) { echo "AUCUN reglage avec setting_group='paypal' !\n"; }
    foreach ($s as $k => $v) {
        if ($k === 'paypal_client_id' || $k === 'paypal_secret') {
            echo sprintf("%-22s : %d caracteres\n", $k, strlen($v));
        } else {
            echo sprintf("%-22s : [%s]\n", $k, $v);
        }
    }

    $cid  = $s['paypal_client_id'] ?? '';
    $sec  = $s['paypal_secret'] ?? '';
    $mode = $s['paypal_mode'] ?? 'sandbox';
    $base = $mode === 'live' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    echo "\nAPI utilisee : " . $base . "\n";

    echo "\n=== ETAPE 1 : AUTHENTIFICATION ===\n";
    $ch = curl_init($base . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $cid . ':' . $sec);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $r1  = curl_exec($ch);
    $c1  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $e1  = curl_error($ch);
    curl_close($ch);
    echo "HTTP " . $c1 . "\n";
    if ($e1 !== '') { echo "Erreur reseau : " . $e1 . "\n"; }
    $tok = json_decode($r1, true);
    if (empty($tok['access_token'])) { echo "Reponse PayPal :\n" . $r1 . "\n"; exit; }
    echo "OK, token obtenu.\n";

    echo "\n=== ETAPE 2 : CREATION ORDRE TEST 1.00 EUR ===\n";
    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => 'DIAG-TEST',
            'description'  => 'Test diagnostic',
            'amount'       => ['currency_code' => 'EUR', 'value' => '1.00'],
        ]],
        'application_context' => [
            'brand_name'  => 'AfroStyle78',
            'locale'      => 'fr-FR',
            'user_action' => 'PAY_NOW',
            'return_url'  => SITE_URL . '/paypal-success.php?order=DIAG',
            'cancel_url'  => SITE_URL . '/confirmation.php?order=DIAG',
        ],
    ];
    $ch = curl_init($base . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $tok['access_token'],
        'Content-Type: application/json',
    ]);
    $r2 = curl_exec($ch);
    $c2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $e2 = curl_error($ch);
    curl_close($ch);
    echo "HTTP " . $c2 . "\n";
    if ($e2 !== '') { echo "Erreur reseau : " . $e2 . "\n"; }
    echo "Reponse PayPal :\n" . $r2 . "\n";
    exit;
}
// --- FIN DIAGNOSTIC ---

// Sauvegarder
$allowedKeys = [
    'site_name','site_phone','site_email','site_address',
    'wave_number','wave_owner_name','orange_money_number','om_owner_name','bank_name','bank_iban','bank_owner',
    'wave_api_key',
    'stripe_public_key','stripe_secret_key','stripe_currency','stripe_fcfa_to_eur',
    'paydunya_master_key','paydunya_private_key','paydunya_token','paydunya_public_key',
    'moneyfusion_api_url',
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
            try {
                $hash = password_hash($newPw, PASSWORD_DEFAULT);
                // La colonne email est NOT NULL + UNIQUE : NULL etait refuse par
                // MySQL (erreur 1048) et la page renvoyait un 500 sans rien creer.
                // Email vide => adresse interne unique, jamais affichee au client.
                $emailToStore = $newEmail !== '' ? $newEmail : $newUser . '@local.invalid';
                $db->prepare("INSERT INTO admins (username, email, password) VALUES (?, ?, ?)")
                   ->execute([$newUser, $emailToStore, $hash]);
                $msg = 'success_new_admin';
            } catch (PDOException $e) {
                error_log('parametres.php : creation compte admin impossible — ' . $e->getMessage());
                $msg = 'error_new_admin_db';
            }
        }
    }

    // ── Envoi d'un email de test ─────────────────────────────────────────────
    // Verifie la configuration SMTP sans attendre une vraie commande : l'echec
    // d'envoi etait jusqu'ici totalement silencieux cote site.
    if (!empty($_POST['test_mail_to'])) {
        $testTo = trim($_POST['test_mail_to']);
        if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $msg = 'error_testmail_adresse';
        } else {
            require_once __DIR__ . '/../config/mailer.php';
            $corpsTest = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>'
                . '<body style="font-family:Georgia,serif;background:#f5f0e8;padding:32px;">'
                . '<div style="max-width:520px;margin:0 auto;background:#fff;padding:32px;">'
                . '<h2 style="color:#1a1008;font-weight:400;margin:0 0 16px;">Test d\'envoi réussi</h2>'
                . '<p style="color:#555;line-height:1.7;margin:0 0 12px;">'
                . 'Si vous lisez ce message, la configuration SMTP d\'AfroStyle78 fonctionne : '
                . 'les emails de commande, de création de compte et de réinitialisation de mot de passe '
                . 'peuvent être envoyés.</p>'
                . '<p style="color:#8a7a62;font-size:13px;margin:20px 0 0;">Envoyé le '
                . date('d/m/Y à H:i') . ' depuis l\'administration.</p>'
                . '</div></body></html>';

            $envoye = sendMail($testTo, 'Test AfroStyle', 'Test de configuration email — AfroStyle78', $corpsTest);
            $msg = $envoye ? 'success_testmail' : 'error_testmail_envoi';
            if (!$envoye) {
                // Le detail (refus Gmail, port bloque...) part dans error_log via sendMail().
                error_log('parametres.php : echec du mail de test vers ' . $testTo);
            }
        }
    }

    // ── Modifier un compte admin (nom, email, mot de passe) ──────────────────
    if (!empty($_POST['edit_admin_id'])) {
        $targetId = (int)$_POST['edit_admin_id'];
        $editUser = trim($_POST['edit_admin_username'] ?? '');
        $editMail = trim($_POST['edit_admin_email'] ?? '');
        $editPw   = $_POST['edit_admin_password'] ?? '';
        $editPwCf = $_POST['edit_admin_password_confirm'] ?? '';

        // Unicite : un autre compte porte-t-il deja cet identifiant / cet email ?
        $dup = $db->prepare("SELECT id FROM admins WHERE (username = ? OR (email = ? AND email <> '')) AND id <> ?");
        $dup->execute([$editUser, $editMail, $targetId]);

        if (!$editUser) {
            $msg = 'error_edit_admin_empty';
        } elseif ($editPw !== '' && strlen($editPw) < 6) {
            $msg = 'error_edit_admin_short';
        } elseif ($editPw !== '' && $editPw !== $editPwCf) {
            $msg = 'error_edit_admin_confirm';
        } elseif ($dup->fetch()) {
            $msg = 'error_edit_admin_exists';
        } else {
            try {
                // Email vide : adresse interne unique, la colonne est NOT NULL.
                $mailToStore = $editMail !== '' ? $editMail : $editUser . '@local.invalid';
                if ($editPw !== '') {
                    // Mot de passe reinitialise : il n'est jamais lisible, seulement remplace.
                    $db->prepare("UPDATE admins SET username = ?, email = ?, password = ? WHERE id = ?")
                       ->execute([$editUser, $mailToStore, password_hash($editPw, PASSWORD_DEFAULT), $targetId]);
                } else {
                    $db->prepare("UPDATE admins SET username = ?, email = ? WHERE id = ?")
                       ->execute([$editUser, $mailToStore, $targetId]);
                }
                // Si l'admin a modifie son propre nom, la session doit suivre.
                if ($targetId === (int)$_SESSION['admin_id']) {
                    $_SESSION['admin_username'] = $editUser;
                }
                $msg = 'success_edit_admin';
            } catch (PDOException $e) {
                error_log('parametres.php : modification compte admin impossible — ' . $e->getMessage());
                $msg = 'error_edit_admin_db';
            }
        }
    }

    // ── Supprimer un compte admin ────────────────────────────────────────────
    if (!empty($_POST['delete_admin_id'])) {
        $targetId = (int)$_POST['delete_admin_id'];
        $nbAdmins = (int)$db->query("SELECT COUNT(*) FROM admins")->fetchColumn();

        if ($targetId === (int)$_SESSION['admin_id']) {
            // Se supprimer soi-meme deconnecterait l'utilisateur en pleine action.
            $msg = 'error_delete_admin_self';
        } elseif ($nbAdmins <= 1) {
            // Dernier compte : le supprimer rendrait l'administration inaccessible.
            $msg = 'error_delete_admin_last';
        } else {
            try {
                $db->prepare("DELETE FROM admins WHERE id = ?")->execute([$targetId]);
                $msg = 'success_delete_admin';
            } catch (PDOException $e) {
                error_log('parametres.php : suppression compte admin impossible — ' . $e->getMessage());
                $msg = 'error_delete_admin_db';
            }
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

    // Sauvegarder config email.
    // Le mot de passe d'application va dans config/secrets.php (ignore par Git),
    // JAMAIS dans config/mail.php qui est versionne.
    // Le port et le chiffrement ne sont plus reecrits ici : IONOS bloque le 587,
    // la prod tourne en 465/ssl et un ecrasement casserait l'envoi d'emails.
    if (!empty($_POST['mail_username'])) {
        $mailUsername = trim($_POST['mail_username']);
        // Google affiche le mot de passe d'application par groupes de 4 ("abcd efgh ijkl mnop").
        // Colle tel quel, il ferait 19 caracteres et Gmail refuserait l'authentification :
        // on retire tous les espaces (y compris insecables) pour retomber sur les 16 attendus.
        $mailPassword = preg_replace('/[\s\x{00A0}]+/u', '', $_POST['mail_password'] ?? '');
        $mailFromName = trim($_POST['mail_from_name']) ?: 'AfroStyle Atelier';

        // mail.php : uniquement des valeurs non sensibles.
        $mailContent  = "<?php\n";
        $mailContent .= "// Configuration SMTP — mise a jour depuis l'admin.\n";
        $mailContent .= "// AUCUN secret ici : le mot de passe vit dans config/secrets.php.\n";
        $mailContent .= "if (file_exists(__DIR__ . '/secrets.php')) {\n";
        $mailContent .= "    require_once __DIR__ . '/secrets.php';\n";
        $mailContent .= "}\n";
        $mailContent .= "define('MAIL_HOST',       'smtp.gmail.com');\n";
        $mailContent .= "define('MAIL_PORT',       " . (defined('MAIL_PORT') ? (int)MAIL_PORT : 465) . ");\n";
        $mailContent .= "define('MAIL_USERNAME',   " . var_export($mailUsername, true) . ");\n";
        $mailContent .= "define('MAIL_PASSWORD',   defined('MAIL_APP_PASSWORD') ? MAIL_APP_PASSWORD : '');\n";
        $mailContent .= "define('MAIL_FROM_EMAIL', " . var_export($mailUsername, true) . ");\n";
        $mailContent .= "define('MAIL_FROM_NAME',  " . var_export($mailFromName, true) . ");\n";
        $mailContent .= "define('MAIL_ENCRYPTION', " . var_export(defined('MAIL_ENCRYPTION') ? MAIL_ENCRYPTION : 'ssl', true) . ");\n";
        file_put_contents(__DIR__ . '/../config/mail.php', $mailContent);

        // secrets.php : mis a jour seulement si un nouveau mot de passe est saisi.
        if ($mailPassword !== '') {
            $secretsFile = __DIR__ . '/../config/secrets.php';
            $secrets     = file_exists($secretsFile) ? file_get_contents($secretsFile) : "<?php\n";
            $newLine     = "define('MAIL_APP_PASSWORD', " . var_export($mailPassword, true) . ");";
            if (preg_match("/^\s*define\(\s*'MAIL_APP_PASSWORD'.*$/m", $secrets)) {
                $secrets = preg_replace("/^\s*define\(\s*'MAIL_APP_PASSWORD'.*$/m", $newLine, $secrets, 1);
            } else {
                $secrets = rtrim($secrets) . "\n" . $newLine . "\n";
            }
            // Hebergement mutualise : le fichier contient un secret, il ne doit
            // jamais etre lisible par les autres comptes de la machine.
            // umask avant l'ecriture = le fichier n'existe a aucun instant en
            // lecture publique (un chmod apres coup laisserait une fenetre).
            $oldUmask = umask(0077);
            file_put_contents($secretsFile, $secrets);
            umask($oldUmask);
            @chmod($secretsFile, 0600);
        }
    }

    // INSERT ... ON DUPLICATE KEY UPDATE et non un simple UPDATE : un reglage
    // nouvellement ajoute au code n'a pas encore de ligne en base, et l'UPDATE
    // ne modifiait alors rien — la saisie etait perdue sans aucun message.
    foreach ($allowedKeys as $key) {
        if (isset($_POST[$key])) {
            $stmt = $db->prepare(
                "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
            );
            $stmt->execute([$key, trim($_POST[$key])]);
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
<?= csrfField() ?>

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
            <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener noreferrer"
               style="color:#c8921a;font-weight:700;text-decoration:underline;">
              🔑 Générer un mot de passe d'application Google →
            </a>
            <span style="display:block;margin-top:4px;">
              Le lien ouvre directement la page. Il faut être connecté au compte
              <strong><?= htmlspecialchars($mailCfg['MAIL_USERNAME'] ?? 'Gmail', ENT_QUOTES) ?></strong>
              et avoir activé la validation en deux étapes, sans laquelle Google
              ne propose pas les mots de passe d'application.
            </span>
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
          <?php
            // Le mot de passe vit dans secrets.php, hors du depot. S'il manque,
            // aucun email ne part et rien ne le signalait jusqu'ici.
            // On lit la constante reellement definie plutot que le texte du
            // fichier : une valeur vide ('') doit compter comme absente, et
            // mail.php a deja charge secrets.php a ce stade.
            $motDePasseOk = defined('MAIL_APP_PASSWORD') && trim((string)MAIL_APP_PASSWORD) !== '';
            if (!$motDePasseOk && defined('MAIL_PASSWORD')) {
                // Ancienne configuration : le mot de passe vivait dans mail.php.
                $motDePasseOk = trim((string)MAIL_PASSWORD) !== '';
            }
          ?>
          <div style="margin-top:8px;">
            🔑 Mot de passe d'application :
            <strong style="color:<?= $motDePasseOk ? '#276749' : '#c53030' ?>;">
              <?= $motDePasseOk ? 'enregistré' : 'ABSENT — aucun email ne peut partir' ?>
            </strong>
          </div>
        </div>
        <!-- Le formulaire de test vit plus bas dans la page : il ne peut pas
             etre place ici, un <form> imbrique dans un autre etant ignore par
             les navigateurs. Ce raccourci y mene et met le bloc en evidence. -->
        <div style="margin-top:16px;background:#f0f4ff;border:1px solid #c3d4f7;padding:14px 16px;font-size:0.9rem;color:#2c4a8c;">
          ✉️ <strong>Vérifier que les emails partent :</strong>
          <a href="#test-email" onclick="var b=document.getElementById('test-email');b.scrollIntoView({behavior:'smooth'});b.style.outline='2px solid #c8921a';setTimeout(function(){b.style.outline='';},2000);"
             style="color:#c8921a;font-weight:700;text-decoration:underline;">
            aller au test d'envoi →
          </a>
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

    <!-- MONEYFUSION -->
    <div class="admin-card">
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #f0ebe0;">
        <div style="background:#1d3f8f;color:#fff;padding:8px 14px;font-size:1.1rem;font-weight:700;border-radius:4px;">MF</div>
        <div>
          <div style="font-size:1.1rem;font-weight:700;color:var(--dark);">MoneyFusion</div>
          <div style="font-size:0.88rem;color:var(--muted);">Orange Money, MTN, Wave — 26 pays africains</div>
        </div>
        <div style="margin-left:auto;">
          <?php $mfUrl = $settings['moneyfusion_api_url']['setting_value'] ?? ''; ?>
          <span style="padding:4px 14px;font-size:0.82rem;font-weight:700;border-radius:20px;
            <?= $mfUrl ? 'background:rgba(29,63,143,0.1);color:#1d3f8f;' : 'background:rgba(200,200,200,0.2);color:#999;' ?>">
            <?= $mfUrl ? '🟢 ACTIF' : '⚪ NON CONFIGURÉ' ?>
          </span>
        </div>
      </div>
      <div class="admin-form">
        <div style="margin-bottom:16px;">
          <label>URL de l'API MoneyFusion</label>
          <input type="url" name="moneyfusion_api_url"
                 value="<?= htmlspecialchars($mfUrl, ENT_QUOTES) ?>"
                 placeholder="https://api.moneyfusion.net/api/...">
          <small style="color:var(--muted);font-size:0.85rem;display:block;margin-top:6px;">
            <strong>L'adresse que MoneyFusion vous a générée</strong>, à copier depuis
            <a href="https://moneyfusion.net/dashboard" target="_blank" rel="noopener noreferrer" style="color:#c8921a;font-weight:700;">votre tableau de bord</a>
            → API de Paiement → colonne « Lien ». Elle commence par
            <code style="background:#f2ede3;padding:1px 5px;">https://pay.moneyfusion.net/</code>
            et doit être copiée en entier.
            Ce n'est pas l'adresse de notification ci-dessous, qui va dans l'autre sens.
            Le moyen de paiement apparaît sur le site dès qu'elle est renseignée.
          </small>
        </div>
        <div style="background:#f0f4ff;border:1px solid #c3d4f7;padding:14px 16px;font-size:0.88rem;color:#2c4a8c;">
          🔗 URL de notification à déclarer chez MoneyFusion :<br>
          <strong style="word-break:break-all;"><?= SITE_URL ?>/moneyfusion-webhook.php</strong>
        </div>
      </div>
    </div>

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

<!-- ═══ TEST D'ENVOI EMAIL ═══ -->
<!-- Formulaire distinct : un <form> imbrique dans un autre est ignore par les
     navigateurs. Il envoie un vrai message pour verifier la configuration SMTP
     sans attendre une commande client. -->
<div class="admin-card" id="test-email" style="margin-top:32px;transition:outline 0.3s;">
  <div class="admin-card-header">
    <div class="admin-card-title">✉️ Tester l'envoi d'emails</div>
  </div>

  <?php if ($msg === 'success_testmail'): ?>
    <div class="alert alert-success">✓ Email de test envoyé. Vérifiez la boîte de réception (et les spams) de l'adresse saisie.</div>
  <?php elseif ($msg === 'error_testmail_envoi'): ?>
    <div class="alert alert-error">
      ⚠ L'envoi a échoué. La configuration SMTP est refusée par Gmail.<br>
      <span style="font-size:0.9rem;">Causes fréquentes : mot de passe d'application révoqué ou expiré, validation en deux étapes désactivée sur le compte Google, ou port SMTP bloqué par l'hébergeur. Le détail exact figure dans le fichier <strong>error_log</strong> du serveur, ligne « Mailer error ».</span>
    </div>
  <?php elseif ($msg === 'error_testmail_adresse'): ?>
    <div class="alert alert-error">⚠ Adresse email invalide.</div>
  <?php endif; ?>

  <p style="color:var(--muted);font-size:0.92rem;margin-bottom:16px;">
    Envoie un message réel à l'adresse indiquée, en utilisant la configuration
    enregistrée ci-dessus. Si le test échoue, aucun email du site ne part :
    ni confirmation de commande, ni création de compte, ni réinitialisation de
    mot de passe.
  </p>

  <form method="POST" class="admin-form">
    <?= csrfField() ?>
    <div class="form-row">
      <div>
        <label>Envoyer un test à</label>
        <input type="email" name="test_mail_to" required
               placeholder="votre@email.com"
               value="<?= htmlspecialchars($mailCfg['MAIL_FROM_EMAIL'] ?? '', ENT_QUOTES) ?>">
      </div>
      <div style="display:flex;align-items:flex-end;">
        <button type="submit" class="btn-admin btn-dark">Envoyer l'email de test</button>
      </div>
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
    <?php elseif ($msg === 'error_new_admin_db'): ?>
        <div class="alert alert-error">⚠ Création impossible : erreur base de données. Le détail est dans les logs du serveur.</div>
    <?php elseif ($msg === 'error_new_admin_exists'): ?>
        <div class="alert alert-error">⚠ Un compte avec cet identifiant ou cet email existe déjà.</div>
    <?php elseif ($msg === 'success_edit_admin'): ?>
        <div class="alert alert-success">✓ Compte modifié avec succès.</div>
    <?php elseif ($msg === 'error_edit_admin_empty'): ?>
        <div class="alert alert-error">⚠ L'identifiant est obligatoire.</div>
    <?php elseif ($msg === 'error_edit_admin_short'): ?>
        <div class="alert alert-error">⚠ Le nouveau mot de passe doit contenir au moins 6 caractères.</div>
    <?php elseif ($msg === 'error_edit_admin_confirm'): ?>
        <div class="alert alert-error">⚠ Les nouveaux mots de passe ne correspondent pas.</div>
    <?php elseif ($msg === 'error_edit_admin_exists'): ?>
        <div class="alert alert-error">⚠ Un autre compte utilise déjà cet identifiant ou cet email.</div>
    <?php elseif ($msg === 'error_edit_admin_db'): ?>
        <div class="alert alert-error">⚠ Modification impossible : erreur base de données. Le détail est dans les logs du serveur.</div>
    <?php elseif ($msg === 'success_delete_admin'): ?>
        <div class="alert alert-success">✓ Compte supprimé.</div>
    <?php elseif ($msg === 'error_delete_admin_self'): ?>
        <div class="alert alert-error">⚠ Vous ne pouvez pas supprimer votre propre compte.</div>
    <?php elseif ($msg === 'error_delete_admin_last'): ?>
        <div class="alert alert-error">⚠ Impossible de supprimer le dernier compte administrateur : plus personne ne pourrait se connecter.</div>
    <?php elseif ($msg === 'error_delete_admin_db'): ?>
        <div class="alert alert-error">⚠ Suppression impossible : erreur base de données. Le détail est dans les logs du serveur.</div>
    <?php endif; ?>

    <!-- Liste des admins -->
    <table class="admin-table" style="margin-bottom:24px;">
        <thead><tr><th>Identifiant</th><th>Email</th><th>Créé le</th><th style="text-align:right;">Actions</th></tr></thead>
        <tbody>
            <?php foreach ($adminList as $a):
                $isSelf = ($a['id'] == $_SESSION['admin_id']);
                // Adresse interne generee faute d'email saisi : ne pas l'afficher
                // comme une vraie adresse, elle ne mene nulle part.
                $mailShown = (str_ends_with($a['email'] ?? '', '@local.invalid')) ? '' : ($a['email'] ?? '');
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($a['username']) ?></strong>
                    <?php if ($isSelf): ?>
                    <span style="background:#e8f5e9;color:#2e7d32;font-size:0.7rem;padding:2px 8px;border-radius:4px;margin-left:8px;">Vous</span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--muted);">
                    <?= $mailShown !== '' ? htmlspecialchars($mailShown) : '<em style="opacity:0.6;">non renseigné</em>' ?>
                </td>
                <td style="color:var(--muted);"><?= $a['created_at'] ? date('d/m/Y', strtotime($a['created_at'])) : '—' ?></td>
                <td style="text-align:right; white-space:nowrap;">
                    <button type="button" class="btn-admin btn-outline btn-sm"
                            onclick="toggleEditAdmin(<?= (int)$a['id'] ?>)">Modifier</button>
                    <?php if (!$isSelf && count($adminList) > 1): ?>
                    <form method="POST" style="display:inline;"
                          onsubmit="return confirm('Supprimer définitivement le compte <?= htmlspecialchars($a['username'], ENT_QUOTES) ?> ?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="delete_admin_id" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="btn-admin btn-danger btn-sm">Supprimer</button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <!-- Formulaire de modification, masqué par défaut -->
            <tr id="edit-admin-<?= (int)$a['id'] ?>" hidden>
                <td colspan="4" style="background:rgba(0,0,0,0.03);">
                    <form method="POST" class="admin-form" style="padding:8px 0;">
                        <?= csrfField() ?>
                        <input type="hidden" name="edit_admin_id" value="<?= (int)$a['id'] ?>">
                        <div class="form-row">
                            <div>
                                <label>Identifiant *</label>
                                <input type="text" name="edit_admin_username"
                                       value="<?= htmlspecialchars($a['username'], ENT_QUOTES) ?>" required>
                            </div>
                            <div>
                                <label>Email</label>
                                <input type="email" name="edit_admin_email"
                                       value="<?= htmlspecialchars($mailShown, ENT_QUOTES) ?>"
                                       placeholder="adresse@exemple.com">
                            </div>
                        </div>
                        <div class="form-row">
                            <div>
                                <label>Nouveau mot de passe</label>
                                <input type="password" name="edit_admin_password"
                                       placeholder="Laisser vide pour ne pas changer" minlength="6">
                            </div>
                            <div>
                                <label>Confirmer le mot de passe</label>
                                <input type="password" name="edit_admin_password_confirm"
                                       placeholder="Répétez" minlength="6">
                            </div>
                        </div>
                        <p style="font-size:0.75rem;color:var(--muted);margin:4px 0 12px;">
                            Les mots de passe sont chiffrés à sens unique : ils ne peuvent pas être affichés,
                            seulement remplacés.
                        </p>
                        <button type="submit" class="btn-admin btn-dark">Enregistrer</button>
                        <button type="button" class="btn-admin btn-outline"
                                onclick="toggleEditAdmin(<?= (int)$a['id'] ?>)">Annuler</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <script>
    function toggleEditAdmin(id) {
        var row = document.getElementById('edit-admin-' + id);
        if (row) row.hidden = !row.hidden;
    }
    </script>

    <!-- Formulaire création -->
    <p style="font-size:0.78rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);margin-bottom:16px;">Créer un nouveau compte</p>
    <form method="POST" class="admin-form">
<?= csrfField() ?>
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
<?= csrfField() ?>
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
