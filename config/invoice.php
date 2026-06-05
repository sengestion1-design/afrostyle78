<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

/**
 * Génère une facture PDF AfroStyle78 et la retourne comme chaîne de caractères.
 *
 * @param array $order    Données de la commande (order_number, created_at, total_amount, delivery_fee, payment_method, delivery_address, delivery_city)
 * @param array $items    Articles de la commande (product_name, size, quantity, unit_price, product_images)
 * @param array $customer Données du client (first_name, last_name, email, customer_address, customer_city)
 * @return string         Le PDF sous forme de chaîne binaire
 */
function generateInvoicePDF(array $order, array $items, array $customer): string
{
    // Répertoire temporaire pour mPDF
    $tmpDir = '/tmp/mpdf/';
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0777, true);
    }

    $mpdf = new Mpdf([
        'mode'          => 'utf-8',
        'format'        => 'A4',
        'margin_top'    => 0,
        'margin_bottom' => 0,
        'margin_left'   => 0,
        'margin_right'  => 0,
        'tempDir'       => $tmpDir,
    ]);

    $mpdf->SetTitle('Facture ' . $order['order_number']);
    $mpdf->SetAuthor('AfroStyle78');

    // ── Logo ──────────────────────────────────────────────────────────────────
    $logoPath = __DIR__ . '/../logo.jpg';
    $logoTag  = '';
    if (file_exists($logoPath)) {
        $logoData   = base64_encode(file_get_contents($logoPath));
        $logoMime   = 'image/jpeg';
        $logoTag    = '<img src="data:' . $logoMime . ';base64,' . $logoData . '" style="height:80px;width:80px;border-radius:50%;object-fit:cover;" />';
    } else {
        $logoTag = '<span style="font-size:28px;font-weight:bold;color:#c8921a;">A</span>';
    }

    // ── Infos commande ────────────────────────────────────────────────────────
    $invoiceNumber = $order['order_number'];
    $invoiceDate   = date('d/m/Y', strtotime($order['created_at']));
    $clientName    = htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
    $clientEmail   = htmlspecialchars($customer['email'] ?? '');
    $clientAddress = htmlspecialchars($customer['customer_address'] ?? ($order['delivery_address'] ?? '—'));
    $clientCity    = htmlspecialchars($customer['customer_city'] ?? ($order['delivery_city'] ?? '—'));

    // ── Lignes articles ───────────────────────────────────────────────────────
    $itemRows   = '';
    $subtotal   = 0.0;
    $rowBg      = ['#ffffff', '#fffbf4'];
    $rowIdx     = 0;

    foreach ($items as $item) {
        $productName  = htmlspecialchars($item['product_name'] ?? '');
        $size         = htmlspecialchars($item['size'] ?? '—');
        $qty          = (int)($item['quantity'] ?? 1);
        $unitPrice    = (float)($item['unit_price'] ?? 0);
        $lineTotal    = $qty * $unitPrice;
        $subtotal    += $lineTotal;
        $bg           = $rowBg[$rowIdx % 2];
        $rowIdx++;

        // Image produit
        $imgHtml = '<div style="width:52px;height:52px;background:#f0ebe0;border:1px solid #e0d8cc;text-align:center;line-height:52px;font-size:10px;color:#7a6248;">Photo</div>';
        $rawImages = $item['product_images'] ?? '';
        if ($rawImages) {
            $imageFiles = json_decode($rawImages, true);
            $firstImage = is_array($imageFiles) ? ($imageFiles[0] ?? '') : $rawImages;
            if ($firstImage) {
                $imgPath = __DIR__ . '/../uploads/products/' . $firstImage;
                if (file_exists($imgPath)) {
                    $ext      = strtolower(pathinfo($imgPath, PATHINFO_EXTENSION));
                    $mimeMap  = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
                    $mime     = $mimeMap[$ext] ?? 'image/jpeg';
                    $imgData  = base64_encode(file_get_contents($imgPath));
                    $imgHtml  = '<img src="data:' . $mime . ';base64,' . $imgData . '" style="width:52px;height:52px;object-fit:cover;border:1px solid #e0d8cc;" />';
                }
            }
        }

        $itemRows .= '
        <tr style="background:' . $bg . ';">
          <td style="padding:10px 12px;vertical-align:middle;border-bottom:1px solid #f0ebe0;">' . $imgHtml . '</td>
          <td style="padding:10px 12px;vertical-align:middle;border-bottom:1px solid #f0ebe0;">
            <div style="font-weight:bold;color:#1a1008;font-size:13px;">' . $productName . '</div>
            <div style="color:#7a6248;font-size:11px;margin-top:2px;">Taille : ' . $size . '</div>
          </td>
          <td style="padding:10px 12px;text-align:center;vertical-align:middle;border-bottom:1px solid #f0ebe0;color:#1a1008;font-size:13px;">' . $qty . '</td>
          <td style="padding:10px 12px;text-align:right;vertical-align:middle;border-bottom:1px solid #f0ebe0;color:#1a1008;font-size:13px;">' . number_format($unitPrice, 2, ',', ' ') . ' €</td>
          <td style="padding:10px 12px;text-align:right;vertical-align:middle;border-bottom:1px solid #f0ebe0;font-weight:bold;color:#1a1008;font-size:13px;">' . number_format($lineTotal, 2, ',', ' ') . ' €</td>
        </tr>';
    }

    // ── Totaux ────────────────────────────────────────────────────────────────
    $deliveryFee = (float)($order['delivery_fee'] ?? 0);
    $totalTTC    = (float)($order['total_amount'] ?? ($subtotal + $deliveryFee));
    $deliveryStr = $deliveryFee > 0 ? number_format($deliveryFee, 2, ',', ' ') . ' €' : 'Gratuit';

    // ── Méthode de paiement ───────────────────────────────────────────────────
    $paymentLabels = [
        'cash'         => 'Paiement à la livraison',
        'wave'         => 'Wave',
        'orange_money' => 'Orange Money',
        'virement'     => 'Virement bancaire',
        'carte'        => 'Carte bancaire',
    ];
    $paymentMethod = $paymentLabels[$order['payment_method'] ?? ''] ?? ($order['payment_method'] ?? '—');

    // ── HTML de la facture ────────────────────────────────────────────────────
    $html = '
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<style>
  * { box-sizing: border-box; }
  body { margin:0; padding:0; font-family: Arial, Helvetica, sans-serif; color: #1a1008; background: #ffffff; font-size:13px; }
</style>
</head>
<body>

<!-- ═══ EN-TÊTE ═══ -->
<table width="100%" cellpadding="0" cellspacing="0" style="background:#1a1008; padding:0;">
  <tr>
    <td style="padding:32px 48px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="vertical-align:middle;">
            ' . $logoTag . '
          </td>
          <td style="vertical-align:middle; padding-left:20px;">
            <div style="color:#c8921a; font-size:26px; font-weight:bold; letter-spacing:2px;">AfroStyle78</div>
            <div style="color:rgba(245,240,232,0.7); font-size:12px; margin-top:4px;">Guyancourt, Yvelines (78)</div>
            <div style="color:rgba(245,240,232,0.6); font-size:11px; margin-top:2px;">sengestion1@gmail.com</div>
          </td>
          <td style="vertical-align:middle; text-align:right;">
            <div style="color:#c8921a; font-size:22px; font-weight:bold; letter-spacing:1px;">FACTURE</div>
            <div style="color:rgba(245,240,232,0.8); font-size:13px; margin-top:6px;">N° ' . htmlspecialchars($invoiceNumber) . '</div>
            <div style="color:rgba(245,240,232,0.6); font-size:12px; margin-top:2px;">Date : ' . $invoiceDate . '</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>
  <!-- Bande or -->
  <tr><td style="height:3px; background:#c8921a;"></td></tr>
</table>

<!-- ═══ INFOS CLIENT ═══ -->
<table width="100%" cellpadding="0" cellspacing="0" style="background:#fffbf4; border-bottom:1px solid #e8dcc8;">
  <tr>
    <td style="padding:24px 48px;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="vertical-align:top; width:50%;">
            <div style="font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#c8921a; margin-bottom:8px;">Facturé à</div>
            <div style="font-size:15px; font-weight:bold; color:#1a1008;">' . $clientName . '</div>
            <div style="font-size:12px; color:#7a6248; margin-top:3px;">' . $clientEmail . '</div>
            <div style="font-size:12px; color:#7a6248; margin-top:2px;">' . $clientAddress . '</div>
            <div style="font-size:12px; color:#7a6248;">' . $clientCity . '</div>
          </td>
          <td style="vertical-align:top; text-align:right; width:50%;">
            <div style="font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#c8921a; margin-bottom:8px;">Paiement</div>
            <div style="font-size:13px; color:#1a1008;">' . htmlspecialchars($paymentMethod) . '</div>
            <div style="font-size:12px; color:#38a169; font-weight:bold; margin-top:4px;">✓ Payé</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<!-- ═══ TABLEAU DES ARTICLES ═══ -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:0;">
  <thead>
    <tr style="background:#c8921a;">
      <th style="padding:10px 12px; text-align:left; font-size:11px; letter-spacing:1px; text-transform:uppercase; color:#1a1008; font-weight:bold; width:60px;">Photo</th>
      <th style="padding:10px 12px; text-align:left; font-size:11px; letter-spacing:1px; text-transform:uppercase; color:#1a1008; font-weight:bold;">Article</th>
      <th style="padding:10px 12px; text-align:center; font-size:11px; letter-spacing:1px; text-transform:uppercase; color:#1a1008; font-weight:bold; width:50px;">Qté</th>
      <th style="padding:10px 12px; text-align:right; font-size:11px; letter-spacing:1px; text-transform:uppercase; color:#1a1008; font-weight:bold; width:90px;">Prix unit.</th>
      <th style="padding:10px 12px; text-align:right; font-size:11px; letter-spacing:1px; text-transform:uppercase; color:#1a1008; font-weight:bold; width:90px;">Total</th>
    </tr>
  </thead>
  <tbody>
    ' . $itemRows . '
  </tbody>
</table>

<!-- ═══ TOTAUX ═══ -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:0;">
  <tr>
    <td style="width:55%;"></td>
    <td style="width:45%; padding:0 48px 0 0;">
      <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e8dcc8;">
        <tr>
          <td style="padding:10px 16px; font-size:13px; color:#7a6248; background:#fffbf4; border-bottom:1px solid #e8dcc8;">Sous-total</td>
          <td style="padding:10px 16px; text-align:right; font-size:13px; color:#1a1008; background:#fffbf4; border-bottom:1px solid #e8dcc8;">' . number_format($subtotal, 2, ',', ' ') . ' €</td>
        </tr>
        <tr>
          <td style="padding:10px 16px; font-size:13px; color:#7a6248; background:#fffbf4; border-bottom:1px solid #e8dcc8;">Livraison</td>
          <td style="padding:10px 16px; text-align:right; font-size:13px; color:#1a1008; background:#fffbf4; border-bottom:1px solid #e8dcc8;">' . $deliveryStr . '</td>
        </tr>
        <tr>
          <td style="padding:14px 16px; font-size:15px; font-weight:bold; color:#1a1008; background:#1a1008;">Total TTC</td>
          <td style="padding:14px 16px; text-align:right; font-size:17px; font-weight:bold; color:#c8921a; background:#1a1008;">' . number_format($totalTTC, 2, ',', ' ') . ' €</td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<!-- ═══ PIED DE PAGE ═══ -->
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top:40px; border-top:2px solid #c8921a;">
  <tr>
    <td style="padding:24px 48px; text-align:center; background:#1a1008;">
      <div style="color:#c8921a; font-size:14px; font-style:italic; letter-spacing:1px;">Merci pour votre confiance — AfroStyle78</div>
      <div style="color:rgba(245,240,232,0.5); font-size:11px; margin-top:8px;">Guyancourt, Yvelines (78) &nbsp;|&nbsp; sengestion1@gmail.com</div>
    </td>
  </tr>
</table>

</body>
</html>';

    $mpdf->WriteHTML($html);
    return $mpdf->Output('', 'S'); // 'S' = retourner comme chaîne
}
