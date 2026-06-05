<?php
ob_start();
$pageTitle = 'Suivi de commande';
require_once 'includes/header.php';

$db = getDB();
$order = null;
$tracking = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['order'])) {
    $orderNum = trim($_POST['order_number'] ?? $_GET['order'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($orderNum) {
        $sql = "SELECT o.*, c.first_name, c.last_name, c.email FROM orders o JOIN customers c ON o.customer_id = c.id WHERE o.order_number = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$orderNum]);
        $order = $stmt->fetch();

        if ($order) {
            // Email obligatoire pour accéder au suivi (sauf client connecté)
            $isOwner = !empty($_SESSION['customer_id']) && (int)$_SESSION['customer_id'] === (int)$order['customer_id'];
            if (!$isOwner && strtolower($order['email']) !== strtolower($email)) {
                $order = null;
                $error = 'Email incorrect pour cette commande.';
            } else {
                $stmt2 = $db->prepare("SELECT * FROM delivery_tracking WHERE order_id = ? ORDER BY created_at ASC");
                $stmt2->execute([$order['id']]);
                $tracking = $stmt2->fetchAll();

                $items = $db->prepare("SELECT oi.*, m.tour_poitrine, m.tour_taille FROM order_items oi LEFT JOIN measurements m ON m.order_item_id = oi.id WHERE oi.order_id = ?");
                $items->execute([$order['id']]);
                $orderItems = $items->fetchAll();
            }
        } else {
            $error = 'Commande non trouvée. Vérifiez le numéro.';
        }
    }
}

$statusLabels = [
    'pending' => ['label'=>'En attente', 'color'=>'#C8921A', 'icon'=>'⏳'],
    'confirmed' => ['label'=>'Confirmée', 'color'=>'#2196F3', 'icon'=>'✓'],
    'in_production' => ['label'=>'En confection', 'color'=>'#9C27B0', 'icon'=>'✂️'],
    'shipped' => ['label'=>'Expédiée', 'color'=>'#FF9800', 'icon'=>'🚚'],
    'delivered' => ['label'=>'Livrée', 'color'=>'#1A7A4A', 'icon'=>'✅'],
    'cancelled' => ['label'=>'Annulée', 'color'=>'#C0392B', 'icon'=>'✕'],
];
$allStatuses = ['pending','confirmed','in_production','shipped','delivered'];
?>

<div class="container" style="max-width:800px; padding:clamp(24px,5vw,60px) clamp(16px,4vw,40px);">
    <div class="section-header" style="text-align:left; margin-bottom:48px;">
        <div class="section-eyebrow">Commandes</div>
        <h1 class="section-title">Suivi de <em>commande</em></h1>
    </div>

    <?php if(!$order): ?>
    <div style="background:var(--white); padding:clamp(16px,4vw,40px); border-top:3px solid var(--gold);">
        <?php if($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <p style="color:var(--text-muted); font-size:0.88rem; margin-bottom:28px; line-height:1.7;">
            Entrez votre numéro de commande (format : AFS-XXXXXXXX-XXXXX) pour suivre l'avancement de votre commande.
        </p>
        <form method="POST" action="">
            <div class="form-grid" style="grid-template-columns:1fr; gap:16px;">
                <div class="form-group">
                    <label>Numéro de commande *</label>
                    <input type="text" name="order_number" placeholder="AFS-20240101-XXXXX" value="<?= htmlspecialchars($_POST['order_number'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label>Email (optionnel, pour vérification)</label>
                    <input type="email" name="email" placeholder="votre@email.com">
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:20px;">Rechercher ma commande</button>
        </form>
    </div>

    <?php else: ?>
    <!-- ORDER FOUND -->
    <div style="background:var(--white); padding:clamp(16px,3vw,32px); border-top:3px solid var(--gold); margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px; margin-bottom:24px;">
            <div>
                <div style="font-size:0.7rem; color:var(--gold); letter-spacing:0.15em; text-transform:uppercase; margin-bottom:4px;">Commande</div>
                <div style="font-size:1.2rem; font-weight:700;"><?= htmlspecialchars($order['order_number']) ?></div>
                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:4px;"><?= $order['first_name'] ?> <?= $order['last_name'] ?> · <?= date('d/m/Y', strtotime($order['created_at'])) ?></div>
            </div>
            <div style="text-align:right;">
                <?php $s = $statusLabels[$order['status']] ?? $statusLabels['pending']; ?>
                <div style="background:<?= $s['color'] ?>22; color:<?= $s['color'] ?>; padding:6px 16px; font-size:0.75rem; font-weight:700; letter-spacing:0.1em; text-transform:uppercase;">
                    <?= $s['icon'] ?> <?= $s['label'] ?>
                </div>
                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:8px;">Total: <strong><?= number_format($order['total_amount'], 0, ',', ' ') ?> €</strong></div>
            </div>
        </div>

        <!-- PROGRESS BAR -->
        <?php if($order['status'] !== 'cancelled'): ?>
        <div style="margin:32px 0;">
            <div class="progress-steps" style="display:flex; justify-content:space-between; position:relative;">
                <div style="position:absolute; top:14px; left:0; right:0; height:2px; background:var(--cream-2); z-index:0;"></div>
                <?php
                $currentIdx = array_search($order['status'], $allStatuses);
                foreach($allStatuses as $i => $st):
                    $sInfo = $statusLabels[$st];
                    $done = $i <= $currentIdx;
                    $current = $i === $currentIdx;
                ?>
                <div style="display:flex; flex-direction:column; align-items:center; gap:8px; z-index:1; flex:1;">
                    <div style="width:28px; height:28px; border-radius:50%; background:<?= $done ? $sInfo['color'] : 'var(--cream-2)' ?>; display:flex; align-items:center; justify-content:center; font-size:0.75rem; border:2px solid <?= $done ? $sInfo['color'] : 'var(--cream-2)' ?>; <?= $current ? 'box-shadow: 0 0 0 4px '.str_replace(')', ',0.2)', str_replace('rgb', 'rgba', $sInfo['color'])).'33; ' : '' ?>">
                        <?= $done ? '<span style="color:white; font-size:0.75rem;">'.$sInfo['icon'].'</span>' : '' ?>
                    </div>
                    <div class="step-label" style="font-size:0.65rem; text-align:center; font-weight:<?= $current ? '700' : '400' ?>; color:<?= $done ? 'var(--dark)' : 'var(--text-muted)' ?>;"><?= $sInfo['label'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- TRACKING HISTORY -->
    <?php if(!empty($tracking)): ?>
    <div style="background:var(--white); padding:32px; margin-bottom:24px;">
        <div style="font-size:0.7rem; font-weight:700; letter-spacing:0.15em; text-transform:uppercase; color:var(--gold); margin-bottom:20px;">Historique de suivi</div>
        <?php foreach(array_reverse($tracking) as $t): ?>
        <div style="display:flex; gap:16px; padding:14px 0; border-bottom:1px solid var(--cream-2);">
            <div style="font-size:0.75rem; color:var(--text-muted); white-space:nowrap; flex-shrink:0;"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></div>
            <div style="min-width:0; overflow:hidden; text-overflow:ellipsis;">
                <div style="font-size:0.82rem; font-weight:600;"><?= htmlspecialchars($t['status']) ?></div>
                <?php if($t['note']): ?><div style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;"><?= htmlspecialchars($t['note']) ?></div><?php endif; ?>
                <?php if($t['location']): ?><div style="font-size:0.72rem; color:var(--gold); margin-top:2px;">📍 <?= htmlspecialchars($t['location']) ?></div><?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ITEMS -->
    <div style="background:var(--white); padding:32px;">
        <div style="font-size:0.7rem; font-weight:700; letter-spacing:0.15em; text-transform:uppercase; color:var(--gold); margin-bottom:20px;">Articles commandés</div>
        <?php foreach($orderItems as $oi): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid var(--cream-2); flex-wrap:wrap; gap:8px;">
            <div>
                <div style="font-weight:600; font-size:0.88rem;"><?= htmlspecialchars($oi['product_name']) ?></div>
                <div style="font-size:0.72rem; color:var(--text-muted);">Taille: <?= htmlspecialchars($oi['size']) ?> · Qté: <?= $oi['quantity'] ?><?= $oi['is_custom_measure'] ? ' · <span style="color:var(--gold);">Sur-mesure</span>' : '' ?></div>
            </div>
            <div style="font-weight:700; font-size:0.9rem;"><?= number_format($oi['unit_price'] * $oi['quantity'], 0, ',', ' ') ?> €</div>
        </div>
        <?php endforeach; ?>
    </div>

    <div style="margin-top:24px; text-align:center;">
        <a href="suivi.php" class="btn btn-dark btn-sm">← Nouvelle recherche</a>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
