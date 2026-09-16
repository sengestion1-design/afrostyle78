<?php
require_once 'includes/auth.php';
$adminTitle = 'Catégories';
$db  = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Mise à jour photo "Tout voir" ──────────────────────────────────
    if (isset($_POST['save_all_image'])) {
        if (isset($_POST['delete_cat_all_image'])) {
            $db->prepare("UPDATE settings SET setting_value='' WHERE setting_key='cat_all_image'")->execute();
        } elseif (isset($_FILES['cat_all_image']) && $_FILES['cat_all_image']['size'] > 0 && $_FILES['cat_all_image']['error'] === UPLOAD_ERR_OK) {
            $ext  = strtolower(pathinfo($_FILES['cat_all_image']['name'], PATHINFO_EXTENSION));
            $name = uniqid('cat_all_', true) . '.' . $ext;
            if (move_uploaded_file($_FILES['cat_all_image']['tmp_name'], UPLOADS_DIR . $name)) {
                $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key='cat_all_image'")->execute([$name]);
            }
        }
        $msg = '<div class="alert alert-success">Photo "Tout voir" mise à jour.</div>';
        goto end_post;
    }

    // ── Catégorie normale ──────────────────────────────────────────────
    $name = trim($_POST['name'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $id   = (int)($_POST['id'] ?? 0);

    // Upload image catégorie
    $imageName = $_POST['existing_image'] ?? '';
    if (isset($_FILES['cat_image']) && $_FILES['cat_image']['size'] > 0 && $_FILES['cat_image']['error'] === UPLOAD_ERR_OK) {
        $ext       = strtolower(pathinfo($_FILES['cat_image']['name'], PATHINFO_EXTENSION));
        $imageName = uniqid('cat_', true) . '.' . $ext;
        if (!move_uploaded_file($_FILES['cat_image']['tmp_name'], UPLOADS_DIR . $imageName)) {
            $imageName = $_POST['existing_image'] ?? '';
        }
    }
    if (isset($_POST['delete_image'])) $imageName = '';

    if ($id) {
        $db->prepare("UPDATE categories SET name=?, slug=?, description=?, image=? WHERE id=?")
           ->execute([$name, $slug, $desc, $imageName ?: null, $id]);
        $msg = '<div class="alert alert-success">Catégorie mise à jour.</div>';
    } else {
        $db->prepare("INSERT INTO categories (name, slug, description, image) VALUES (?,?,?,?)")
           ->execute([$name, $slug, $desc, $imageName ?: null]);
        $msg = '<div class="alert alert-success">Catégorie ajoutée.</div>';
    }
    end_post:;
}

if (isset($_GET['delete'])) {
    $db->prepare("DELETE FROM categories WHERE id=?")->execute([(int)$_GET['delete']]);
    header('Location: categories.php'); exit;
}

// Photo "Tout voir"
$catAllImage = $db->query("SELECT setting_value FROM settings WHERE setting_key='cat_all_image'")->fetchColumn();

$cats = $db->query("SELECT cat.*, COUNT(p.id) as prod_count FROM categories cat LEFT JOIN products p ON p.category_id=cat.id AND p.active=1 GROUP BY cat.id ORDER BY cat.name")->fetchAll();
$editCat = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM categories WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editCat = $stmt->fetch();
}
require_once 'includes/admin_header.php';
?>

<?= $msg ?>

<div style="display:grid; grid-template-columns:1fr 400px; gap:24px; align-items:start;">

  <!-- TABLEAU -->
  <div class="admin-card">
    <div class="admin-card-header">
      <div class="admin-card-title">Catégories (<?= count($cats) ?>)</div>
    </div>
    <table class="admin-table">
      <thead>
        <tr>
          <th>Photo</th>
          <th>Nom</th>
          <th>Slug</th>
          <th>Produits</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($cats as $c): ?>
        <tr>
          <td>
            <?php if ($c['image']): ?>
            <img src="<?= UPLOADS_URL . htmlspecialchars($c['image']) ?>"
                 style="width:56px;height:64px;object-fit:cover;border:1px solid #e0d8ce;">
            <?php else: ?>
            <div style="width:56px;height:64px;background:var(--cream-2);display:flex;align-items:center;justify-content:center;font-size:1.5rem;border:1px solid #e0d8ce;">👗</div>
            <?php endif; ?>
          </td>
          <td><strong style="font-size:1rem;"><?= htmlspecialchars($c['name']) ?></strong></td>
          <td style="color:var(--muted);font-size:0.9rem;"><?= htmlspecialchars($c['slug']) ?></td>
          <td style="font-size:1rem;"><?= $c['prod_count'] ?></td>
          <td style="white-space:nowrap;">
            <a href="categories.php?edit=<?= $c['id'] ?>" class="btn-admin btn-dark" style="font-size:0.85rem;padding:7px 14px;">Modifier</a>
            <a href="categories.php?delete=<?= $c['id'] ?>" class="btn-admin" style="background:#e53e3e;color:#fff;font-size:0.85rem;padding:7px 14px;margin-left:4px;" onclick="return confirm('Supprimer cette catégorie ?')">Supprimer</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- FORMULAIRE -->
  <div class="admin-card">
    <div class="admin-card-header">
      <div class="admin-card-title"><?= $editCat ? '✏️ Modifier la catégorie' : '+ Nouvelle catégorie' ?></div>
    </div>
    <form method="POST" enctype="multipart/form-data" class="admin-form">
<?= csrfField() ?>
      <?php if($editCat): ?>
      <input type="hidden" name="id" value="<?= $editCat['id'] ?>">
      <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editCat['image'] ?? '') ?>">
      <?php endif; ?>

      <div style="margin-bottom:18px;">
        <label>Nom *</label>
        <input type="text" name="name" value="<?= htmlspecialchars($editCat['name'] ?? '') ?>" required>
      </div>

      <div style="margin-bottom:18px;">
        <label>Description</label>
        <textarea name="description" rows="3"><?= htmlspecialchars($editCat['description'] ?? '') ?></textarea>
      </div>

      <div style="margin-bottom:20px;">
        <label>Photo de la catégorie</label>
        <input type="file" name="cat_image" accept="image/*" style="margin-top:6px;">

        <?php if (!empty($editCat['image'])): ?>
        <div style="margin-top:12px;display:flex;align-items:center;gap:12px;padding:12px;background:#f9f6f0;border:1px solid #e0d8ce;">
          <img src="<?= UPLOADS_URL . htmlspecialchars($editCat['image']) ?>"
               style="width:72px;height:88px;object-fit:cover;border:1px solid #e0d8ce;">
          <div>
            <div style="font-size:0.88rem;color:var(--muted);margin-bottom:4px;">Photo actuelle</div>
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:0.92rem;font-weight:600;color:#e53e3e;text-transform:none;letter-spacing:0;">
              <input type="checkbox" name="delete_image" value="1" onchange="toggleDeleteImg(this)">
              Supprimer la photo
            </label>
          </div>
        </div>
        <?php endif; ?>

        <small style="color:var(--muted);font-size:0.85rem;display:block;margin-top:6px;">
          Recommandé : format portrait 3/4 (ex: 600×800px)
        </small>
      </div>

      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-admin btn-gold" style="flex:1;justify-content:center;">
          <?= $editCat ? '✓ Enregistrer' : '+ Ajouter' ?>
        </button>
        <?php if($editCat): ?>
        <a href="categories.php" class="btn-admin btn-dark">Annuler</a>
        <?php endif; ?>
      </div>
    </form>
  </div>

</div>

<!-- CARTE TOUT VOIR -->
<div style="margin-top:28px;">
  <div class="admin-card">
    <div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;padding-bottom:14px;border-bottom:1px solid #f0ebe0;">
      <div style="background:var(--dark);color:var(--gold);padding:8px 14px;font-size:1.2rem;font-weight:700;">✦</div>
      <div>
        <div style="font-size:1.1rem;font-weight:700;color:var(--dark);">Carte "Tout voir — Toutes collections"</div>
        <div style="font-size:0.88rem;color:var(--muted);">Photo de fond pour la carte spéciale en début de liste</div>
      </div>
    </div>

    <form method="POST" enctype="multipart/form-data" style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start;">
<?= csrfField() ?>
      <input type="hidden" name="save_all_image" value="1">

      <!-- PREVIEW -->
      <div>
        <?php if ($catAllImage): ?>
        <div style="position:relative;margin-bottom:12px;">
          <img src="<?= UPLOADS_URL . htmlspecialchars($catAllImage) ?>"
               style="width:100%;height:200px;object-fit:cover;border:1px solid #e0d8ce;">
          <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(14,10,6,0.85));padding:16px 12px 12px;">
            <div style="color:var(--gold);font-size:0.75rem;letter-spacing:0.15em;font-weight:700;">✦ TOUT VOIR</div>
            <div style="color:#fff;font-size:0.85rem;">Toutes collections</div>
          </div>
        </div>
        <?php else: ?>
        <div style="width:100%;height:200px;background:var(--dark-2,#1a1208);border:2px dashed rgba(200,146,26,0.3);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;margin-bottom:12px;">
          <span style="font-size:2rem;opacity:0.3;">🖼️</span>
          <span style="color:rgba(200,146,26,0.5);font-size:0.85rem;">Aucune photo</span>
        </div>
        <?php endif; ?>
      </div>

      <!-- UPLOAD -->
      <div class="admin-form">
        <div style="margin-bottom:16px;">
          <label><?= $catAllImage ? 'Remplacer la photo' : 'Ajouter une photo' ?></label>
          <input type="file" name="cat_all_image" accept="image/*">
          <small style="color:var(--muted);font-size:0.85rem;display:block;margin-top:6px;">Format recommandé : portrait (ex: 600×800px)</small>
        </div>

        <?php if ($catAllImage): ?>
        <div style="margin-bottom:16px;">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;text-transform:none;letter-spacing:0;font-size:0.95rem;font-weight:600;color:#e53e3e;">
            <input type="checkbox" name="delete_cat_all_image" value="1">
            Supprimer la photo actuelle
          </label>
        </div>
        <?php endif; ?>

        <button type="submit" class="btn-admin btn-gold" style="width:100%;justify-content:center;">
          ✓ Enregistrer
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function toggleDeleteImg(cb) {
    const hiddenImg = document.querySelector('input[name=existing_image]');
    if (cb.checked && hiddenImg) hiddenImg.value = '';
}
</script>

<?php require_once 'includes/admin_footer.php'; ?>
