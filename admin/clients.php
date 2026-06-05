<?php
require_once 'includes/auth.php';
$adminTitle = 'Clients';
$db = getDB();

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $deleteId = (int)$_POST['delete_id'];
    $db->prepare("DELETE FROM customers WHERE id = ?")->execute([$deleteId]);
    header('Location: clients.php?deleted=1');
    exit;
}

$clients = $db->query("SELECT c.*, COUNT(o.id) as order_count, SUM(o.total_amount) as total_spent FROM customers c LEFT JOIN orders o ON o.customer_id=c.id GROUP BY c.id ORDER BY c.created_at DESC")->fetchAll();
require_once 'includes/admin_header.php';
?>
<div class="admin-card">
    <div class="admin-card-header"><div class="admin-card-title">Clients (<?= count($clients) ?>)</div></div>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success" style="margin:16px 24px;">✓ Client supprimé avec succès.</div>
    <?php endif; ?>

    <table class="admin-table">
        <thead><tr><th>Nom</th><th>Email</th><th>Téléphone</th><th>Ville</th><th>Commandes</th><th>Total dépensé</th><th>Inscrit le</th><th>Action</th></tr></thead>
        <tbody>
            <?php foreach($clients as $c): ?>
            <tr>
                <td><strong><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></strong></td>
                <td style="color:var(--muted); font-size:1.1rem;"><?= htmlspecialchars($c['email']) ?></td>
                <td style="font-size:1.1rem;"><?= htmlspecialchars($c['phone']) ?></td>
                <td style="font-size:1.1rem;"><?= htmlspecialchars($c['city']) ?></td>
                <td><strong><?= $c['order_count'] ?></strong></td>
                <td><?= number_format($c['total_spent'] ?? 0, 0, ',', ' ') ?> €</td>
                <td style="font-size:1rem; color:var(--muted);"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('Supprimer <?= htmlspecialchars(addslashes($c['first_name'] . ' ' . $c['last_name'])) ?> ? Cette action est irréversible.')">
                        <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn-status" style="background:#fee2e2;color:#dc2626;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;font-size:1rem;">🗑 Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once 'includes/admin_footer.php'; ?>
