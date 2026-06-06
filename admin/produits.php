<?php
require_once 'includes/auth.php';
$adminTitle = 'Produits';
$db = getDB();

$msg = '';
$action = $_GET['action'] ?? '';
$editId = (int)($_GET['id'] ?? 0);

// DELETE
if ($action === 'delete' && $editId) {
    $db->prepare("UPDATE products SET active=0 WHERE id=?")->execute([$editId]);
    header('Location: produits.php?msg=deleted'); exit;
}

// SAVE (add or edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');

    // Bloquer si le nom ressemble à un nom de fichier
    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $name)) {
        $name = '';
    }
    if (!$name) {
        $msg = '<div class="alert alert-error">⚠ Le nom du produit est requis.</div>';
        goto end_save;
    }

    $desc = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $promoPrice = !empty($_POST['promo_price']) ? (float)$_POST['promo_price'] : null;
    $catId = (int)($_POST['category_id'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $featured = isset($_POST['featured']) ? 1 : 0;
    $allowCustom = isset($_POST['allow_custom_measure']) ? 1 : 0;
    $sizesInput  = $_POST['sizes'] ?? [];
    $sizes       = json_encode(array_values(array_filter($sizesInput)));
    $colorsInput = $_POST['colors'] ?? [];
    // Couleurs : tableau de {name, hex}
    $colors = [];
    foreach (($_POST['color_name'] ?? []) as $i => $cname) {
        $chex = trim($_POST['color_hex'][$i] ?? '');
        $cname = trim($cname);
        if ($cname && $chex) $colors[] = ['name' => $cname, 'hex' => $chex];
    }
    $colorsJson = json_encode($colors, JSON_UNESCAPED_UNICODE);

    // Slug
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
    $slug = trim($slug, '-');

    // Handle image principale upload
    $imageName = $_POST['existing_image'] ?? '';
    if (isset($_FILES['image']) && $_FILES['image']['size'] > 0 && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $allowedExt  = ['jpg','jpeg','png','webp','gif'];
        $allowedMime = ['image/jpeg','image/png','image/webp','image/gif'];
        $ext  = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($_FILES['image']['tmp_name']);
        if (in_array($ext, $allowedExt, true) && in_array($mime, $allowedMime, true) && getimagesize($_FILES['image']['tmp_name']) !== false) {
            $imageName = uniqid('img_', true) . '.' . $ext;
            if (!move_uploaded_file($_FILES['image']['tmp_name'], UPLOADS_DIR . $imageName)) {
                $imageName = $_POST['existing_image'] ?? '';
            }
        } else {
            $msg = '<div class="alert alert-error">⚠ Format d\'image non autorisé. Utilisez JPG, PNG ou WebP.</div>';
            $imageName = $_POST['existing_image'] ?? '';
        }
    }

    // Handle photos supplémentaires
    $existingImages = json_decode($_POST['existing_images'] ?? '[]', true) ?: [];
    // Supprimer les images cochées à effacer
    $deleteImages = $_POST['delete_images'] ?? [];
    $existingImages = array_values(array_filter($existingImages, fn($img) => !in_array($img, $deleteImages)));

    $newImages  = [];
    $allowedExt  = ['jpg','jpeg','png','webp','gif'];
    $allowedMime = ['image/jpeg','image/png','image/webp','image/gif'];
    if (!empty($_FILES['images']['name'][0])) {
        foreach ($_FILES['images']['error'] as $i => $err) {
            if ($err === UPLOAD_ERR_OK && $_FILES['images']['size'][$i] > 0) {
                $ext  = strtolower(pathinfo($_FILES['images']['name'][$i], PATHINFO_EXTENSION));
                $mime = mime_content_type($_FILES['images']['tmp_name'][$i]);
                if (!in_array($ext, $allowedExt, true) || !in_array($mime, $allowedMime, true) || getimagesize($_FILES['images']['tmp_name'][$i]) === false) {
                    continue; // fichier invalide — on ignore
                }
                $name = uniqid('img_', true) . '.' . $ext;
                if (move_uploaded_file($_FILES['images']['tmp_name'][$i], UPLOADS_DIR . $name)) {
                    $newImages[] = $name;
                }
            }
        }
    }
    $allImages    = array_merge($existingImages, $newImages);
    $imagesJson   = json_encode($allImages);

    if ($editId) {
        $db->prepare("UPDATE products SET name=?,slug=?,description=?,price=?,promo_price=?,category_id=?,stock=?,featured=?,allow_custom_measure=?,available_sizes=?,available_colors=?,image=?,images=? WHERE id=?")
           ->execute([$name,$slug,$desc,$price,$promoPrice,$catId,$stock,$featured,$allowCustom,$sizes,$colorsJson,$imageName,$imagesJson,$editId]);
        $msg = '<div class="alert alert-success">Produit mis à jour.</div>';
    } else {
        $db->prepare("INSERT INTO products (name,slug,description,price,promo_price,category_id,stock,featured,allow_custom_measure,available_sizes,available_colors,image,images) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$name,$slug,$desc,$price,$promoPrice,$catId,$stock,$featured,$allowCustom,$sizes,$colorsJson,$imageName,$imagesJson]);
        $msg = '<div class="alert alert-success">Produit ajouté avec succès.</div>';
        $action = '';
    }
    end_save:;
}

$editProduct = null;
if (($action === 'edit' || $editId) && $editId) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id=?");
    $stmt->execute([$editId]);
    $editProduct = $stmt->fetch();
}

$products = $db->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.active=1 ORDER BY p.created_at DESC")->fetchAll();
$cats = $db->query("SELECT * FROM categories ORDER BY name")->fetchAll();
$allSizes = ['XS','S','M','L','XL','XXL','3XL'];

if(isset($_GET['msg']) && $_GET['msg']==='deleted') $msg = '<div class="alert alert-info">Produit désactivé.</div>';

require_once 'includes/admin_header.php';
?>

<?= $msg ?>

<?php if($action === 'add' || $editProduct): ?>
<!-- FORM -->
<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title"><?= $editProduct ? 'Modifier le produit' : 'Ajouter un produit' ?></div>
        <a href="produits.php" class="btn-admin btn-outline btn-sm">← Retour</a>
    </div>
    <form method="POST" enctype="multipart/form-data" class="admin-form">
        <input type="hidden" name="existing_image" value="<?= htmlspecialchars($editProduct['image'] ?? '') ?>">
        <div class="form-row">
            <div><label>Nom du produit *</label><input type="text" name="name" value="<?= htmlspecialchars($editProduct['name'] ?? '') ?>" required></div>
            <div><label>Catégorie</label>
                <select name="category_id">
                    <option value="">— Sans catégorie —</option>
                    <?php foreach($cats as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ($editProduct['category_id'] ?? '') == $c['id'] ? 'selected':'' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-row full"><div><label>Description</label><textarea name="description" rows="3"><?= htmlspecialchars($editProduct['description'] ?? '') ?></textarea></div></div>
        <div class="form-row">
            <div><label>Prix (€) *</label><input type="number" name="price" value="<?= $editProduct['price'] ?? '' ?>" step="0.01" min="0" required></div>
            <div><label>Prix promo (€)</label><input type="number" name="promo_price" value="<?= $editProduct['promo_price'] ?? '' ?>" step="0.01" min="0" placeholder="Vide si pas de promo"></div>
        </div>
        <div class="form-row">
            <div><label>Stock</label><input type="number" name="stock" value="<?= $editProduct['stock'] ?? 0 ?>"></div>
            <div>
                <label>Photo principale</label>
                <input type="file" name="image" accept="image/*">
                <?php if(!empty($editProduct['image'])): ?>
                <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
                    <img src="<?= UPLOADS_URL . htmlspecialchars($editProduct['image']) ?>" style="width:60px;height:72px;object-fit:cover;border:1px solid #e0d8ce;">
                    <span style="font-size:0.88rem;color:var(--muted);">Photo actuelle</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- PHOTOS SUPPLÉMENTAIRES -->
        <div class="form-row full">
            <div>
                <label>Photos supplémentaires <span style="font-weight:400;font-size:0.9rem;color:var(--muted);">(galerie — plusieurs photos)</span></label>
                <input type="file" name="images[]" accept="image/*" multiple style="margin-top:8px;">
                <input type="hidden" name="existing_images" value="<?= htmlspecialchars($editProduct['images'] ?? '[]') ?>">

                <?php
                $existingImgs = json_decode($editProduct['images'] ?? '[]', true) ?: [];
                if (!empty($existingImgs)):
                ?>
                <div style="margin-top:14px;">
                    <p style="font-size:0.82rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--muted);margin-bottom:10px;">Photos actuelles — cocher pour supprimer</p>
                    <div style="display:flex;flex-wrap:wrap;gap:12px;">
                        <?php foreach ($existingImgs as $img): ?>
                        <div style="position:relative;width:90px;">
                            <img src="<?= UPLOADS_URL . htmlspecialchars($img) ?>" style="width:90px;height:110px;object-fit:cover;border:1.5px solid #e0d8ce;">
                            <label style="position:absolute;top:4px;right:4px;background:rgba(229,62,62,0.9);color:#fff;width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:0.8rem;" title="Supprimer">
                                <input type="checkbox" name="delete_images[]" value="<?= htmlspecialchars($img) ?>" style="display:none;">
                                ✕
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <small style="color:var(--muted);font-size:0.85rem;display:block;margin-top:8px;">Maintenez Ctrl/Cmd pour sélectionner plusieurs fichiers</small>
            </div>
        </div>
        <div class="form-row full">
            <div>
                <label>Tailles disponibles</label>
                <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:8px;">
                    <?php
                    $existingSizes = json_decode($editProduct['available_sizes'] ?? '[]', true);
                    foreach($allSizes as $sz):
                    ?>
                    <label style="display:flex; align-items:center; gap:6px; font-size:1.15rem; text-transform:none; letter-spacing:0; color:var(--text); font-weight:500; cursor:pointer;">
                        <input type="checkbox" name="sizes[]" value="<?= $sz ?>" <?= in_array($sz, $existingSizes) ? 'checked':'' ?>>
                        <?= $sz ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- COULEURS -->
        <div class="form-row full">
            <div>
                <label>Couleurs disponibles <span style="font-weight:400;font-size:0.9rem;color:var(--muted);">(laisser vide si pas de choix de couleur)</span></label>
                <div id="colors-list" style="display:flex;flex-direction:column;gap:10px;margin-top:12px;">
                <?php
                $existingColors = json_decode($editProduct['available_colors'] ?? '[]', true) ?: [];
                if (empty($existingColors)) $existingColors[] = ['name'=>'','hex'=>'#c8921a'];
                foreach ($existingColors as $ci => $col): ?>
                    <div class="color-row" style="display:flex;align-items:center;gap:12px;">
                        <input type="color" name="color_hex[]" value="<?= htmlspecialchars($col['hex'] ?? '#c8921a') ?>"
                               style="width:48px;height:42px;padding:2px;border:1.5px solid #e0d8ce;cursor:pointer;background:#fff;">
                        <input type="text" name="color_name[]" value="<?= htmlspecialchars($col['name'] ?? '') ?>"
                               placeholder="Nom de la couleur (ex: Bleu roi, Blanc cassé...)"
                               style="flex:1;padding:11px 14px;border:1.5px solid #e0d8ce;font-family:inherit;font-size:1rem;">
                        <button type="button" onclick="removeColor(this)"
                                style="background:#e53e3e;color:#fff;border:none;padding:10px 16px;cursor:pointer;font-size:1rem;">✕</button>
                    </div>
                <?php endforeach; ?>
                </div>
                <button type="button" onclick="addColor()"
                        style="margin-top:12px;background:var(--dark);color:var(--gold);border:none;padding:10px 20px;cursor:pointer;font-size:0.95rem;font-weight:700;letter-spacing:0.05em;">
                    + Ajouter une couleur
                </button>
            </div>
        </div>

        <script>
        function addColor() {
            const list = document.getElementById('colors-list');
            const row  = document.createElement('div');
            row.className = 'color-row';
            row.style.cssText = 'display:flex;align-items:center;gap:12px;';
            row.innerHTML = `
                <input type="color" name="color_hex[]" value="#c8921a"
                       style="width:48px;height:42px;padding:2px;border:1.5px solid #e0d8ce;cursor:pointer;background:#fff;">
                <input type="text" name="color_name[]" placeholder="Nom de la couleur (ex: Bleu roi, Blanc cassé...)"
                       style="flex:1;padding:11px 14px;border:1.5px solid #e0d8ce;font-family:inherit;font-size:1rem;">
                <button type="button" onclick="removeColor(this)"
                        style="background:#e53e3e;color:#fff;border:none;padding:10px 16px;cursor:pointer;font-size:1rem;">✕</button>
            `;
            list.appendChild(row);
        }
        function removeColor(btn) {
            const rows = document.querySelectorAll('.color-row');
            if (rows.length > 1) btn.closest('.color-row').remove();
        }
        </script>
        <div class="form-row">
            <div style="display:flex; gap:24px; align-items:center;">
                <label style="display:flex; align-items:center; gap:8px; text-transform:none; letter-spacing:0; color:var(--text); font-weight:500; cursor:pointer; font-size:1.15rem;">
                    <input type="checkbox" name="featured" <?= !empty($editProduct['featured']) ? 'checked':'' ?>> Coup de cœur (mis en avant)
                </label>
                <label style="display:flex; align-items:center; gap:8px; text-transform:none; letter-spacing:0; color:var(--text); font-weight:500; cursor:pointer; font-size:1.15rem;">
                    <input type="checkbox" name="allow_custom_measure" <?= !empty($editProduct['allow_custom_measure']) ? 'checked':'' ?>> Autoriser sur-mesure
                </label>
            </div>
        </div>
        <div style="margin-top:8px;">
            <button type="submit" class="btn-admin btn-gold"><?= $editProduct ? '✓ Enregistrer' : '+ Ajouter le produit' ?></button>
        </div>
    </form>
</div>

<?php else: ?>
<!-- PRODUCTS LIST -->
<div style="display:flex; justify-content:flex-end; margin-bottom:16px;">
    <a href="produits.php?action=add" class="btn-admin btn-gold">+ Ajouter un produit</a>
</div>
<div class="admin-card">
    <table class="admin-table">
        <thead><tr><th>Photo</th><th>Produit</th><th>Catégorie</th><th>Prix</th><th>Stock</th><th>Mis en avant</th><th>Sur-mesure</th><th>Actions</th></tr></thead>
        <tbody>
            <?php foreach($products as $p): ?>
            <tr>
                <td style="width:60px;">
                    <?php if($p['image']): ?>
                    <img src="<?= UPLOADS_URL . htmlspecialchars($p['image']) ?>" style="width:52px; height:64px; object-fit:cover;">
                    <?php else: ?>
                    <div style="width:52px; height:64px; background:#F0EBE3; display:flex; align-items:center; justify-content:center; font-size:1.5rem;">👗</div>
                    <?php endif; ?>
                </td>
                <td><strong style="font-size:1.2rem;"><?= htmlspecialchars($p['name']) ?></strong><div style="font-size:0.92rem; color:var(--muted); margin-top:4px;"><?= htmlspecialchars($p['slug']) ?></div></td>
                <td style="font-size:1.15rem;"><?= htmlspecialchars($p['cat_name'] ?? '—') ?></td>
                <td style="font-size:1.15rem;">
                    <?php if($p['promo_price']): ?>
                    <span style="color:var(--gold); font-weight:700;"><?= number_format($p['promo_price'],0,',',' ') ?></span>
                    <span style="text-decoration:line-through; color:var(--muted); font-size:0.95rem; margin-left:6px;"><?= number_format($p['price'],0,',',' ') ?></span>
                    <?php else: ?>
                    <?= number_format($p['price'],0,',',' ') ?> €
                    <?php endif; ?>
                </td>
                <td style="font-size:1.15rem;"><?= $p['stock'] ?></td>
                <td style="font-size:1.3rem;"><?= $p['featured'] ? '⭐' : '—' ?></td>
                <td style="font-size:1.15rem;"><?= $p['allow_custom_measure'] ? '✂️ Oui' : '—' ?></td>
                <td>
                    <a href="produits.php?action=edit&id=<?= $p['id'] ?>" class="btn-admin btn-outline btn-sm">Modifier</a>
                    <a href="produits.php?action=delete&id=<?= $p['id'] ?>" class="btn-admin btn-danger btn-sm" onclick="return confirm('Désactiver ce produit ?')" style="margin-left:4px;">Supprimer</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once 'includes/admin_footer.php'; ?>
