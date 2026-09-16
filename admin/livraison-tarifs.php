<?php
require_once '../config/config.php';
require_once '../config/database.php';

if (empty($_SESSION['admin_id'])) { header('Location: login.php'); exit; }

$db  = getDB();
$msg = '';

// Ajouter
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_zone'])) {
    $stmt = $db->prepare("INSERT INTO shipping_zones (name, zone_type, countries, method, method_code, price, surcharge_3_5, surcharge_6_plus, delay, description, active, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        trim($_POST['name']),
        $_POST['zone_type'],
        trim($_POST['countries']) ?: null,
        trim($_POST['method']),
        trim($_POST['method_code']),
        (float)$_POST['price'],
        (float)($_POST['surcharge_3_5'] ?? 50),
        (float)($_POST['surcharge_6_plus'] ?? 100),
        trim($_POST['delay']) ?: null,
        trim($_POST['description']) ?: null,
        isset($_POST['active']) ? 1 : 0,
        (int)($_POST['sort_order'] ?? 0),
    ]);
    $msg = 'success_add';
}

// Modifier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_zone'])) {
    $stmt = $db->prepare("UPDATE shipping_zones SET name=?, zone_type=?, countries=?, method=?, method_code=?, price=?, surcharge_3_5=?, surcharge_6_plus=?, delay=?, description=?, active=?, sort_order=? WHERE id=?");
    $stmt->execute([
        trim($_POST['name']),
        $_POST['zone_type'],
        trim($_POST['countries']) ?: null,
        trim($_POST['method']),
        trim($_POST['method_code']),
        (float)$_POST['price'],
        (float)($_POST['surcharge_3_5'] ?? 50),
        (float)($_POST['surcharge_6_plus'] ?? 100),
        trim($_POST['delay']) ?: null,
        trim($_POST['description']) ?: null,
        isset($_POST['active']) ? 1 : 0,
        (int)($_POST['sort_order'] ?? 0),
        (int)$_POST['zone_id'],
    ]);
    $msg = 'success_edit';
}

// Supprimer
if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM shipping_zones WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: livraison-tarifs.php?deleted=1'); exit;
}

$zones   = $db->query("SELECT * FROM shipping_zones ORDER BY sort_order, id")->fetchAll();
$editZone = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM shipping_zones WHERE id=?");
    $s->execute([(int)$_GET['edit']]);
    $editZone = $s->fetch();
}

$zoneTypeLabels = ['local' => '🇸🇳 Local', 'national' => '📦 National', 'international' => '✈️ International'];
$pageTitle = 'Tarifs de livraison';
require_once 'includes/admin_header.php';
?>

<div class="admin-content">

<?php if ($msg === 'success_add'): ?>
<div style="background:#f0fff4;border:1px solid #9ae6b4;color:#276749;padding:12px 20px;margin-bottom:20px;font-size:1rem;">✓ Zone ajoutée avec succès.</div>
<?php elseif ($msg === 'success_edit'): ?>
<div style="background:#f0fff4;border:1px solid #9ae6b4;color:#276749;padding:12px 20px;margin-bottom:20px;font-size:1rem;">✓ Zone mise à jour.</div>
<?php elseif (isset($_GET['deleted'])): ?>
<div style="background:#fff5f5;border:1px solid #fed7d7;color:#c53030;padding:12px 20px;margin-bottom:20px;font-size:1rem;">Zone supprimée.</div>
<?php endif; ?>

<div style="display:flex; flex-direction:column; gap:32px; width:100%;">

  <!-- TABLEAU DES ZONES -->
  <div style="width:100%; overflow-x:auto;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <h2 style="font-family:'Cormorant Garamond',serif; font-size:1.6rem; font-weight:600; margin:0;">Zones de livraison</h2>
      <span style="font-size:0.9rem; color:var(--muted);"><?= count($zones) ?> zone<?= count($zones)>1?'s':'' ?></span>
    </div>

    <!-- INFO PALIERS -->
    <div style="background:#fffbf0;border:1px solid rgba(200,146,26,0.3);padding:14px 20px;margin-bottom:20px;font-size:0.95rem;display:flex;gap:24px;align-items:center;">
      <span style="color:var(--gold);font-weight:700;">✦ Paliers de quantité</span>
      <span>1–2 articles → Prix de base</span>
      <span style="color:var(--muted)">|</span>
      <span>3–5 articles → Prix + supplément %</span>
      <span style="color:var(--muted)">|</span>
      <span>6+ articles → Prix + supplément %</span>
    </div>

    <table class="admin-table">
      <thead>
        <tr>
          <th>Zone</th>
          <th>Type</th>
          <th>Méthode</th>
          <th>1–2 art.</th>
          <th>3–5 art.</th>
          <th>6+ art.</th>
          <th>Délai</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($zones as $z):
          $p = (float)$z['price'];
          $p35  = $p > 0 ? round($p * (1 + $z['surcharge_3_5']/100)) : 0;
          $p6   = $p > 0 ? round($p * (1 + $z['surcharge_6_plus']/100)) : 0;
        ?>
        <tr>
          <td>
            <strong style="font-size:1rem;"><?= htmlspecialchars($z['name']) ?></strong>
            <?php if ($z['description']): ?>
            <div style="font-size:0.85rem; color:var(--muted); margin-top:2px;"><?= htmlspecialchars($z['description']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:0.9rem;"><?= $zoneTypeLabels[$z['zone_type']] ?? $z['zone_type'] ?></td>
          <td style="font-size:0.95rem;"><?= htmlspecialchars($z['method']) ?></td>
          <td><strong style="color:var(--gold);"><?= $p > 0 ? number_format($p,2,',',' ').' €' : '<span style="color:#38a169;">Gratuit</span>' ?></strong></td>
          <td>
            <strong style="color:#c8921a;"><?= $p35 > 0 ? number_format($p35,2,',',' ').' €' : '<span style="color:#38a169;">Gratuit</span>' ?></strong>
            <?php if($p > 0): ?><div style="font-size:0.8rem;color:var(--muted);">+<?= $z['surcharge_3_5'] ?>%</div><?php endif; ?>
          </td>
          <td>
            <strong style="color:#9b6f10;"><?= $p6 > 0 ? number_format($p6,2,',',' ').' €' : '<span style="color:#38a169;">Gratuit</span>' ?></strong>
            <?php if($p > 0): ?><div style="font-size:0.8rem;color:var(--muted);">+<?= $z['surcharge_6_plus'] ?>%</div><?php endif; ?>
          </td>
          <td style="font-size:0.9rem; color:var(--muted);"><?= $z['delay'] ? htmlspecialchars($z['delay']) : '—' ?></td>
          <td>
            <span style="display:inline-block; padding:3px 10px; font-size:0.8rem; font-weight:700; <?= $z['active'] ? 'background:rgba(56,161,105,0.1);color:#276749;' : 'background:rgba(229,62,62,0.1);color:#c53030;' ?>">
              <?= $z['active'] ? 'Actif' : 'Inactif' ?>
            </span>
          </td>
          <td style="white-space:nowrap;">
            <a href="?edit=<?= $z['id'] ?>" class="btn-admin btn-dark" style="font-size:0.85rem; padding:7px 14px;">Modifier</a>
            <a href="?delete=<?= $z['id'] ?>" class="btn-admin" style="background:#e53e3e;color:#fff;font-size:0.85rem;padding:7px 14px;" onclick="return confirm('Supprimer cette zone ?')">Supprimer</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- FORMULAIRE -->
  <div style="background:#fff; border:1px solid #E8E0D8; padding:28px; width:100%;">
    <h3 style="font-family:'Cormorant Garamond',serif; font-size:1.3rem; font-weight:600; margin:0 0 24px; border-bottom:1px solid #f0ebe0; padding-bottom:12px;">
      <?= $editZone ? '✏️ Modifier la zone' : '+ Nouvelle zone' ?>
    </h3>

    <form method="POST" class="admin-form">
<?= csrfField() ?>
      <?php if ($editZone): ?>
      <input type="hidden" name="zone_id" value="<?= $editZone['id'] ?>">
      <?php endif; ?>

      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px;">
        <div>
          <label>Nom de la zone *</label>
          <input type="text" name="name" value="<?= htmlspecialchars($editZone['name'] ?? '') ?>" placeholder="Ex: Livraison Dakar" required>
        </div>
        <div>
          <label>Type *</label>
          <select name="zone_type">
            <?php foreach(['local'=>'🇸🇳 Local (Dakar)','national'=>'📦 National (Sénégal)','international'=>'✈️ International'] as $val=>$label): ?>
            <option value="<?= $val ?>" <?= ($editZone['zone_type'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Pays (codes ISO, ex: FR,BE,CH)</label>
          <input type="text" name="countries" value="<?= htmlspecialchars($editZone['countries'] ?? '') ?>" placeholder="Vide = tous les pays">
          <small style="color:var(--muted);font-size:0.82rem;">SN = Sénégal, FR = France, etc.</small>
        </div>
        <div>
          <label>Nom de la méthode *</label>
          <input type="text" name="method" value="<?= htmlspecialchars($editZone['method'] ?? '') ?>" placeholder="Ex: DHL Express" required>
        </div>
        <div>
          <label>Code méthode *</label>
          <input type="text" name="method_code" value="<?= htmlspecialchars($editZone['method_code'] ?? '') ?>" placeholder="Ex: dhl" required>
          <small style="color:var(--muted);font-size:0.82rem;">Sans espaces ni accents</small>
        </div>
        <div>
          <label>Prix de base (€) — 1 à 2 articles *</label>
          <input type="number" name="price" value="<?= $editZone['price'] ?? 0 ?>" min="0" step="0.01" required>
          <small style="color:var(--muted);font-size:0.82rem;">Mettre 0 pour Gratuit</small>
        </div>
        <div>
          <label>3–5 articles (supplément %)</label>
          <input type="number" name="surcharge_3_5" value="<?= $editZone['surcharge_3_5'] ?? 50 ?>" min="0" max="500" step="5">
          <small style="color:var(--muted);font-size:0.82rem;">Ex: 50 = +50%</small>
        </div>
        <div>
          <label>6+ articles (supplément %)</label>
          <input type="number" name="surcharge_6_plus" value="<?= $editZone['surcharge_6_plus'] ?? 100 ?>" min="0" max="500" step="5">
          <small style="color:var(--muted);font-size:0.82rem;">Ex: 100 = +100%</small>
        </div>
        <div>
          <label>Délai estimé</label>
          <input type="text" name="delay" value="<?= htmlspecialchars($editZone['delay'] ?? '') ?>" placeholder="Ex: 5–7 jours">
        </div>
        <div>
          <label>Description</label>
          <input type="text" name="description" value="<?= htmlspecialchars($editZone['description'] ?? '') ?>" placeholder="Ex: France & Europe">
        </div>
        <div>
          <label>Ordre d'affichage</label>
          <input type="number" name="sort_order" value="<?= $editZone['sort_order'] ?? 0 ?>" min="0">
        </div>
        <div style="display:flex;align-items:center;padding-top:24px;">
          <label style="display:flex;align-items:center;gap:10px;text-transform:none;letter-spacing:0;font-size:1rem;font-weight:500;color:var(--text);cursor:pointer;">
            <input type="checkbox" name="active" <?= ($editZone['active'] ?? 1) ? 'checked' : '' ?>> Zone active
          </label>
        </div>
      </div>

      <?php if ($editZone): ?>
      <div style="display:flex;gap:10px;">
        <button type="submit" name="edit_zone" class="btn-admin btn-gold" style="flex:1;justify-content:center;">✓ Enregistrer</button>
        <a href="livraison-tarifs.php" class="btn-admin btn-dark" style="justify-content:center;">Annuler</a>
      </div>
      <?php else: ?>
      <button type="submit" name="add_zone" class="btn-admin btn-gold" style="width:100%;justify-content:center;">+ Ajouter la zone</button>
      <?php endif; ?>
    </form>
  </div>

</div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
