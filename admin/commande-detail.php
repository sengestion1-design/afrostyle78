<?php
require_once 'includes/auth.php';
require_once '../config/mailer.php';
$db = getDB();

$id = (int)($_GET['id'] ?? 0);
$order = $db->prepare("SELECT o.*, c.first_name, c.last_name, c.email, c.phone AS customer_phone, c.address AS customer_address, c.city AS customer_city FROM orders o JOIN customers c ON o.customer_id=c.id WHERE o.id=?");
$order->execute([$id]);
$order = $order->fetch();
if (!$order) { header('Location: commandes.php'); exit; }

// Update status
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_status'])) {
        $newStatus = $_POST['status'];
        $note      = trim($_POST['tracking_note'] ?? '');
        $location  = trim($_POST['tracking_location'] ?? '');
        $db->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$newStatus, $id]);
        $db->prepare("INSERT INTO delivery_tracking (order_id, status, note, location) VALUES (?,?,?,?)")->execute([$id, $newStatus, $note ?: null, $location ?: null]);

        // Email notification au client
        if ($order['email'] && $newStatus !== $order['status']) {
            emailStatusUpdate($order['email'], $order['first_name'], $order, $newStatus, $note);
        }

        $msg = '<div class="alert alert-success">Statut mis à jour — email envoyé au client.</div>';
        $order['status'] = $newStatus;
    }
    if (isset($_POST['add_tracking'])) {
        $note = trim($_POST['tracking_note'] ?? '');
        $location = trim($_POST['tracking_location'] ?? '');
        if ($note) {
            $db->prepare("INSERT INTO delivery_tracking (order_id, status, note, location) VALUES (?,?,?,?)")->execute([$id, $order['status'], $note, $location ?: null]);
            $msg = '<div class="alert alert-success">Événement de suivi ajouté.</div>';
        }
    }
}

$items = $db->prepare("SELECT oi.*, m.* FROM order_items oi LEFT JOIN measurements m ON m.order_item_id=oi.id WHERE oi.order_id=?");
$items->execute([$id]);
$orderItems = $items->fetchAll();

$tracking = $db->prepare("SELECT * FROM delivery_tracking WHERE order_id=? ORDER BY created_at DESC");
$tracking->execute([$id]);
$trackingEvents = $tracking->fetchAll();

$adminTitle = 'Commande ' . $order['order_number'];
$statusLabels = ['pending'=>'En attente','confirmed'=>'Confirmée','in_production'=>'En confection','shipped'=>'Expédiée','delivered'=>'Livrée','cancelled'=>'Annulée'];
require_once 'includes/admin_header.php';
?>

<?= $msg ?>

<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; flex-wrap:wrap; gap:12px;">
    <div>
        <a href="commandes.php" style="color:var(--muted); text-decoration:none; font-size:1.05rem;">← Retour aux commandes</a>
        <span class="status-badge status-<?= $order['status'] ?>" style="margin-left:16px;"><?= $statusLabels[$order['status']] ?? $order['status'] ?></span>
    </div>
    <div style="font-size:1.05rem; color:var(--muted);">Commande du <?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></div>
</div>

<div style="display:grid; grid-template-columns:1fr 340px; gap:24px;">
    <!-- LEFT -->
    <div>
        <!-- ITEMS -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">Articles commandés</div>
            </div>
            <table class="admin-table">
                <thead><tr><th>Article</th><th>Taille</th><th>Prix unit.</th><th>Qté</th><th>Total</th></tr></thead>
                <tbody>
                    <?php foreach($orderItems as $item): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['product_name']) ?></strong>
                            <?php if($item['is_custom_measure']): ?>
                            <div><span style="color:var(--gold); font-size:1rem;">✂️ Sur-mesure</span></div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($item['size']) ?></strong></td>
                        <td><?= number_format($item['unit_price'], 0, ',', ' ') ?> €</td>
                        <td><?= $item['quantity'] ?></td>
                        <td><strong><?= number_format($item['unit_price'] * $item['quantity'], 0, ',', ' ') ?> €</strong></td>
                    </tr>
                    <?php if($item['is_custom_measure'] && $item['tour_poitrine']): ?>
                    <tr style="background:#FDFAF6;">
                        <td colspan="5" style="font-size:1.05rem; color:var(--muted); padding:12px 16px;">
                            <strong style="color:var(--dark);">Mesures :</strong>
                            <?php
                            $measureFields = [
                                'tour_poitrine' => 'Poitrine',
                                'tour_taille' => 'Taille',
                                'tour_hanches' => 'Hanches',
                                'longueur_epaule' => 'Épaule',
                                'longueur_totale' => 'Longueur',
                                'longueur_manche' => 'Manche',
                                'tour_cou' => 'Cou',
                                'tour_bras' => 'Bras',
                            ];
                            foreach($measureFields as $key => $label):
                                if(!empty($item[$key])):
                            ?>
                            <span style="margin-right:16px;"><?= $label ?>: <strong><?= $item[$key] ?> cm</strong></span>
                            <?php endif; endforeach; ?>
                            <?php if(!empty($item['notes'])): ?>
                            <div style="margin-top:6px;">Notes: <?= htmlspecialchars($item['notes']) ?></div>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="display:flex; justify-content:flex-end; padding:16px; gap:40px; font-size:1.1rem;">
                <div>Livraison: <strong><?= number_format($order['delivery_fee'], 0, ',', ' ') ?> €</strong></div>
                <div style="font-size:1rem;">Total: <strong style="font-size:1.1rem;"><?= number_format($order['total_amount'], 0, ',', ' ') ?> €</strong></div>
            </div>
        </div>

        <!-- UPDATE STATUS -->
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">Mettre à jour le statut</div>
            </div>
            <form method="POST" class="admin-form">
                <div class="form-row">
                    <div>
                        <label>Nouveau statut</label>
                        <select name="status">
                            <?php foreach($statusLabels as $v=>$l): ?>
                            <option value="<?= $v ?>" <?= $order['status']===$v?'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Localisation (optionnel)</label>
                        <input type="text" name="tracking_location" placeholder="ex: Dépôt Dakar Plateau">
                    </div>
                </div>
                <div class="form-row full">
                    <div>
                        <label>Note / Message de suivi</label>
                        <input type="text" name="tracking_note" placeholder="Message visible par le client...">
                    </div>
                </div>
                <button type="submit" name="update_status" class="btn-admin btn-gold">✓ Mettre à jour le statut</button>
                <button type="submit" name="add_tracking" class="btn-admin btn-outline" style="margin-left:8px;">+ Ajouter événement (sans changer statut)</button>
            </form>
        </div>

        <!-- TRACKING HISTORY -->
        <?php if(!empty($trackingEvents)): ?>
        <div class="admin-card">
            <div class="admin-card-header">
                <div class="admin-card-title">Historique de suivi</div>
            </div>
            <?php foreach($trackingEvents as $te): ?>
            <div style="display:flex; gap:16px; padding:12px 0; border-bottom:1px solid #F0EBE3;">
                <div style="font-size:1rem; color:var(--muted); white-space:nowrap; min-width:120px;"><?= date('d/m/Y H:i', strtotime($te['created_at'])) ?></div>
                <div>
                    <span class="status-badge status-<?= $te['status'] ?>" style="margin-bottom:4px;"><?= $statusLabels[$te['status']] ?? $te['status'] ?></span>
                    <?php if($te['note']): ?><div style="font-size:1.05rem; color:var(--muted); margin-top:4px;"><?= htmlspecialchars($te['note']) ?></div><?php endif; ?>
                    <?php if($te['location']): ?><div style="font-size:1rem; color:var(--gold); margin-top:2px;">📍 <?= htmlspecialchars($te['location']) ?></div><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: CLIENT INFO -->
    <div>
        <div class="admin-card">
            <div class="admin-card-header"><div class="admin-card-title">Client</div></div>
            <div style="display:flex; flex-direction:column; gap:10px; font-size:1.1rem;">
                <div><strong><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></strong></div>
                <div style="color:var(--muted);">📧 <?= htmlspecialchars($order['email']) ?></div>
                <div style="color:var(--muted);">📞 <?= htmlspecialchars($order['customer_phone']) ?></div>
                <div style="color:var(--muted);">📍 <?= htmlspecialchars($order['customer_address']) ?>, <?= htmlspecialchars($order['customer_city']) ?></div>
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-header"><div class="admin-card-title">Livraison</div></div>
            <div style="font-size:1.1rem; display:flex; flex-direction:column; gap:8px;">
                <div>Mode: <strong><?= $order['delivery_method'] === 'domicile' ? '🚚 Domicile' : '📦 Point de retrait' ?></strong></div>
                <div style="color:var(--muted);"><?= htmlspecialchars($order['delivery_address']) ?></div>
                <div style="color:var(--muted);"><?= htmlspecialchars($order['delivery_city']) ?></div>
                <?php if($order['notes']): ?>
                <div style="margin-top:8px; padding:10px; background:#F0EBE3; font-size:1.05rem; color:var(--muted);">📝 <?= htmlspecialchars($order['notes']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="admin-card">
            <div class="admin-card-header"><div class="admin-card-title">Paiement</div></div>
            <div style="font-size:1.1rem; display:flex; flex-direction:column; gap:8px;">
                <div style="display:flex; justify-content:space-between;"><span>Méthode</span><strong><?= htmlspecialchars($order['payment_method']) ?></strong></div>
                <div style="display:flex; justify-content:space-between;"><span>Statut</span>
                    <span class="status-badge <?= $order['payment_status']==='paid' ? 'status-delivered' : ($order['payment_status']==='pending_verification' ? 'status-shipped' : 'status-pending') ?>">
                        <?= $order['payment_status'] === 'paid' ? '✓ Payé' : ($order['payment_status'] === 'pending_verification' ? '🔍 À vérifier' : '⏳ En attente') ?>
                    </span>
                </div>
                <?php if (!empty($order['sender_phone'])): ?>
                <div style="display:flex; justify-content:space-between; background:#fffbf0; padding:10px 12px; border:1px solid rgba(200,146,26,0.3);">
                    <span>📱 N° expéditeur</span>
                    <strong style="color:#c8921a;"><?= htmlspecialchars($order['sender_phone']) ?></strong>
                </div>
                <?php endif; ?>
                <?php if($order['payment_status'] === 'unpaid' || $order['payment_status'] === 'pending_verification'): ?>
                <form method="POST" style="margin-top:8px;">
                    <input type="hidden" name="status" value="<?= $order['status'] ?>">
                    <button type="submit" name="add_tracking" class="btn-admin btn-gold" style="width:100%; justify-content:center;"
                        onclick="document.querySelector('[name=tracking_note]').value='Paiement reçu et confirmé.'; <?= '' ?>">
                        Marquer comme payé
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/admin_footer.php'; ?>
