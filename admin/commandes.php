<?php
require_once 'includes/auth.php';
$adminTitle = 'Commandes';
$db = getDB();

// Suppression commande
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order_id'])) {
    $delId = (int)$_POST['delete_order_id'];
    $db->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$delId]);
    $db->prepare("DELETE FROM orders WHERE id=?")->execute([$delId]);
    header('Location: commandes.php?deleted=1');
    exit;
}

// Marquer comme payé
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid_id'])) {
    $paidId = (int)$_POST['mark_paid_id'];
    $db->prepare("UPDATE orders SET payment_status='paid', status='confirmed' WHERE id=?")->execute([$paidId]);
    header('Location: commandes.php?paid=1');
    exit;
}

$filter = $_GET['filter'] ?? '';
$where = '1=1';
$params = [];
if ($filter && $filter !== 'all') { $where .= ' AND o.status = ?'; $params[] = $filter; }

$search = trim($_GET['s'] ?? '');
if ($search) {
    $where .= ' AND (o.order_number LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone LIKE ?)';
    $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}

$stmt = $db->prepare("SELECT o.*, c.first_name, c.last_name, c.email, c.phone FROM orders o JOIN customers c ON o.customer_id=c.id WHERE $where ORDER BY o.created_at DESC");
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statusLabels = ['pending'=>'En attente','confirmed'=>'Confirmée','in_production'=>'En confection','shipped'=>'Expédiée','delivered'=>'Livrée','cancelled'=>'Annulée'];
require_once 'includes/admin_header.php';
?>

<?php if (isset($_GET['deleted'])): ?>
<div style="background:#f0fff4;border:1px solid #9ae6b4;color:#276749;padding:12px 20px;margin-bottom:16px;">✓ Commande supprimée.</div>
<?php endif; ?>
<?php if (isset($_GET['paid'])): ?>
<div style="background:#f0fff4;border:1px solid #9ae6b4;color:#276749;padding:12px 20px;margin-bottom:16px;">✓ Commande marquée comme payée et confirmée.</div>
<?php endif; ?>

<?php
// Commandes en attente de paiement (unpaid + pending_verification)
$unpaidOrders = $db->query("SELECT o.*, c.first_name, c.last_name, c.phone, c.email FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.payment_status IN ('unpaid','pending_verification') ORDER BY o.payment_status DESC, o.created_at DESC")->fetchAll();
$pendingVerifCount = count(array_filter($unpaidOrders, fn($o) => $o['payment_status'] === 'pending_verification'));
if ($unpaidOrders):
?>
<div class="admin-card" style="border:2px solid #f6ad55;margin-bottom:24px;">
    <div class="admin-card-header" style="background:#fff8f0;">
        <div class="admin-card-title" style="color:#c05621;">⏳ En attente de paiement (<?= count($unpaidOrders) ?>)
            <?php if($pendingVerifCount > 0): ?>
            <span style="margin-left:12px;background:#3182ce;color:#fff;font-size:0.78rem;padding:3px 10px;border-radius:10px;">🔍 <?= $pendingVerifCount ?> à vérifier</span>
            <?php endif; ?>
        </div>
    </div>
    <table class="admin-table">
        <thead><tr>
            <th>N° Commande</th><th>Client</th><th>Montant</th><th>Mode paiement</th><th>Date</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach($unpaidOrders as $ord): ?>
        <tr style="background:<?= $ord['payment_status']==='pending_verification' ? '#ebf8ff' : '#fffaf0' ?>;">
            <td>
                <strong><?= htmlspecialchars($ord['order_number']) ?></strong>
                <?php if($ord['payment_status']==='pending_verification'): ?>
                <div style="margin-top:4px;"><span style="background:#3182ce;color:#fff;font-size:0.72rem;padding:2px 8px;border-radius:10px;">🔍 À vérifier</span></div>
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($ord['first_name'].' '.$ord['last_name']) ?><br><small><?= htmlspecialchars($ord['phone']) ?></small></td>
            <td><strong><?= number_format($ord['total_amount'],2,',',' ') ?> €</strong></td>
            <td>
                <?php
                $pmLabels = ['wave'=>'📱 Wave','orange_money'=>'📱 Orange Money','virement'=>'🏦 Virement','cash'=>'💵 Espèces','carte'=>'💳 Carte'];
                echo $pmLabels[$ord['payment_method']] ?? $ord['payment_method'];
                ?>
                <?php if(!empty($ord['sender_phone'])): ?>
                <div style="margin-top:4px;font-size:0.82rem;color:#2b6cb0;font-weight:600;">📱 N° ayant effectué le paiement : <?= htmlspecialchars($ord['sender_phone']) ?></div>
                <?php endif; ?>
            </td>
            <td><?= date('d/m/Y H:i', strtotime($ord['created_at'])) ?></td>
            <td style="display:flex;gap:6px;">
                <form method="POST" onsubmit="return confirm('Confirmer le paiement de cette commande ?');">
<?= csrfField() ?>
                    <input type="hidden" name="mark_paid_id" value="<?= $ord['id'] ?>">
                    <button type="submit" style="background:#38a169;color:#fff;border:none;padding:6px 14px;cursor:pointer;font-weight:700;font-size:0.85rem;">✓ Paiement reçu</button>
                </form>
                <a href="commande-detail.php?id=<?= $ord['id'] ?>" class="btn-admin btn-gold btn-sm">Détail</a>
                <form method="POST" onsubmit="return confirm('Supprimer cette commande ?');">
<?= csrfField() ?>
                    <input type="hidden" name="delete_order_id" value="<?= $ord['id'] ?>">
                    <button type="submit" class="btn-admin btn-sm" style="background:#e53e3e;color:#fff;border:none;cursor:pointer;">Supprimer</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<div class="admin-card">
    <div class="admin-card-header">
        <div class="admin-card-title">Toutes les commandes (<?= count($orders) ?>)</div>
        <form method="GET" style="display:flex; gap:8px;">
            <input type="text" name="s" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>" style="padding:7px 14px; border:1px solid #E8E0D8; font-family:'Syne',sans-serif; font-size:1.05rem; outline:none;">
            <select name="filter" onchange="this.form.submit()" style="padding:7px 14px; border:1px solid #E8E0D8; font-family:'Syne',sans-serif; font-size:1.05rem; outline:none;">
                <option value="">Tous statuts</option>
                <?php foreach($statusLabels as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $filter===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <table class="admin-table">
        <thead>
            <tr>
                <th>N° Commande</th>
                <th>Client</th>
                <th>Contact</th>
                <th>Montant</th>
                <th>Livraison</th>
                <th>Statut</th>
                <th>Date</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($orders as $ord): ?>
            <tr>
                <td><strong style="font-size:1.1rem;"><?= htmlspecialchars($ord['order_number']) ?></strong></td>
                <td><?= htmlspecialchars($ord['first_name'] . ' ' . $ord['last_name']) ?></td>
                <td style="font-size:1.05rem; color:var(--muted);"><?= htmlspecialchars($ord['phone']) ?></td>
                <td>
                    <strong><?= number_format($ord['total_amount'], 0, ',', ' ') ?> €</strong><br>
                    <span style="font-size:0.78rem;padding:2px 8px;border-radius:10px;<?= $ord['payment_status']==='paid' ? 'background:#f0fff4;color:#276749;' : 'background:#fff8f0;color:#c05621;' ?>">
                        <?= $ord['payment_status']==='paid' ? '✓ Payé' : '⏳ Impayé' ?>
                    </span>
                </td>
                <td><span style="font-size:1rem;"><?= $ord['delivery_method'] === 'domicile' ? '🚚 Domicile' : '📦 Retrait' ?></span></td>
                <td><span class="status-badge status-<?= $ord['status'] ?>"><?= $statusLabels[$ord['status']] ?? $ord['status'] ?></span></td>
                <td style="color:var(--muted); font-size:1.05rem;"><?= date('d/m/Y', strtotime($ord['created_at'])) ?></td>
                <td style="display:flex;gap:6px;">
                    <a href="commande-detail.php?id=<?= $ord['id'] ?>" class="btn-admin btn-gold btn-sm">Détail</a>
                    <form method="POST" onsubmit="return confirm('Supprimer la commande <?= htmlspecialchars($ord['order_number'], ENT_QUOTES) ?> ? Cette action est irréversible.');">
                        <?= csrfField() ?>
                        <input type="hidden" name="delete_order_id" value="<?= $ord['id'] ?>">
                        <button type="submit" class="btn-admin btn-sm" style="background:#e53e3e;color:#fff;border:none;cursor:pointer;">Supprimer</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
