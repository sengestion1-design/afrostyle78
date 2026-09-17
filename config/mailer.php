<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/mail.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendMail(string $toEmail, string $toName, string $subject, string $htmlBody, array $embeds = []): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);
        $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        // Images embarquées (cid)
        foreach ($embeds as $cid => $path) {
            if (file_exists($path)) {
                $mail->addEmbeddedImage($path, $cid);
            }
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '</p>'], "\n", $htmlBody));

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('Mailer error: ' . $mail->ErrorInfo . ' | ' . $e->getMessage());
        return false;
    }
}

function emailWelcome(string $email, string $firstName, string $lastName): bool {
    $fullName  = htmlspecialchars($firstName . ' ' . $lastName);
    $subject   = '✦ Bienvenue chez AfroStyle, ' . $firstName . ' !';
    $logoUrl = 'https://afrostyle78.com/logo.jpg';

    $html = '<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Bienvenue chez AfroStyle78</title>
</head>
<body style="margin:0;padding:0;background:#f5f0e8;font-family:Georgia,serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <!-- HEADER -->
  <tr>
    <td style="background:#1a1008;padding:32px 48px;text-align:center;border-bottom:2px solid #c8921a;">
      <img src="' . $logoUrl . '" alt="AfroStyle78" style="height:100px;width:100px;object-fit:contain;border-radius:50%;display:block;margin:0 auto 14px;">
      <h1 style="margin:0 0 4px;color:#f5f0e8;font-family:Georgia,serif;font-size:28px;font-weight:400;letter-spacing:2px;">AfroStyle78</h1>
      <p style="margin:0;color:rgba(245,240,232,0.5);font-size:11px;letter-spacing:3px;">✦ GUYANCOURT, YVELINES (78) ✦</p>
    </td>
  </tr>

  <!-- BODY -->
  <tr>
    <td style="background:#ffffff;padding:40px 48px;">
      <p style="margin:0 0 8px;color:#c8921a;font-size:12px;letter-spacing:3px;text-transform:uppercase;">Bienvenue</p>
      <h2 style="margin:0 0 20px;color:#1a1008;font-family:Georgia,serif;font-size:26px;font-weight:400;">Bonjour ' . $fullName . ',</h2>
      <p style="margin:0 0 16px;color:#555;font-size:15px;line-height:1.8;">
        Nous sommes ravis de vous accueillir chez <strong style="color:#1a1008;">AfroStyle78</strong> —
        spécialiste de la mode africaine sur-mesure, basé à Guyancourt (78).
      </p>
      <p style="margin:0 0 28px;color:#555;font-size:15px;line-height:1.8;">
        Votre compte est actif. Explorez nos collections, passez des commandes sur-mesure et suivez vos créations en temps réel.
      </p>

      <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
        <tr><td style="padding:10px 0;border-bottom:1px solid #f0ebe0;"><span style="color:#c8921a;margin-right:10px;">✦</span><span style="color:#333;font-size:14px;">Collections exclusives — Bazin, Kente, Wax premium</span></td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #f0ebe0;"><span style="color:#c8921a;margin-right:10px;">✦</span><span style="color:#333;font-size:14px;">Confection sur-mesure disponible sur tous les articles</span></td></tr>
        <tr><td style="padding:10px 0;border-bottom:1px solid #f0ebe0;"><span style="color:#c8921a;margin-right:10px;">✦</span><span style="color:#333;font-size:14px;">Suivi de commande en temps réel</span></td></tr>
        <tr><td style="padding:10px 0;"><span style="color:#c8921a;margin-right:10px;">✦</span><span style="color:#333;font-size:14px;">Livraison France & International</span></td></tr>
      </table>

      <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
        <a href="' . SITE_URL . '/boutique"
           style="display:inline-block;background:#c8921a;color:#1a1008;text-decoration:none;
                  font-size:13px;font-weight:bold;letter-spacing:3px;text-transform:uppercase;padding:16px 48px;">
          Explorer les collections
        </a>
      </td></tr></table>
    </td>
  </tr>

  <!-- FOOTER -->
  <tr>
    <td style="background:#1a1008;padding:24px 48px;text-align:center;">
      <p style="margin:0 0 4px;color:#c8921a;font-size:12px;letter-spacing:2px;">AfroStyle78</p>
      <p style="margin:0 0 4px;color:rgba(245,240,232,0.5);font-size:11px;">Guyancourt, Yvelines (78) &nbsp;|&nbsp; +33 6 44 72 87 30</p>
      <p style="margin:0;color:rgba(245,240,232,0.3);font-size:10px;">&copy; ' . date('Y') . ' AfroStyle78 — Tous droits réservés</p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';

    return sendMail($email, $firstName, $subject, $html);
}

// ─── EMAIL CONFIRMATION DE COMMANDE ───────────────────────────────────────────
function emailOrderConfirmation(string $email, string $firstName, array $order, array $items): bool {
    $logoPath = __DIR__ . '/../logo.jpg';
    $logoTag  = file_exists($logoPath)
        ? '<img src="cid:afrostyle_logo" alt="AfroStyle" style="height:100px;width:100px;object-fit:contain;border-radius:50%;">'
        : '<span style="color:#c8921a;font-size:24px;font-weight:bold;">AfroStyle</span>';

    $subject  = '✦ Votre commande ' . $order['order_number'] . ' est confirmée !';

    $itemsHtml = '';
    foreach ($items as $item) {
        $itemsHtml .= '
        <tr>
          <td style="padding:12px 0;border-bottom:1px solid #f0ebe0;">
            <strong style="color:#1a1008;font-size:15px;">' . htmlspecialchars($item['product_name']) . '</strong>
            <div style="color:#7a6248;font-size:13px;margin-top:2px;">Taille: ' . htmlspecialchars($item['size']) . ' &nbsp;|&nbsp; Qté: ' . $item['quantity'] . '</div>
          </td>
          <td style="padding:12px 0;border-bottom:1px solid #f0ebe0;text-align:right;font-weight:700;color:#1a1008;">
            ' . number_format($item['unit_price'] * $item['quantity'], 0, ',', ' ') . ' €
          </td>
        </tr>';
    }

    $paymentLabels = [
        'cash' => '💵 Paiement à la livraison',
        'wave' => '📱 Wave',
        'orange_money' => '📱 Orange Money',
        'virement' => '🏦 Virement bancaire',
        'carte' => '💳 Carte bancaire',
    ];
    $paymentLabel = $paymentLabels[$order['payment_method']] ?? $order['payment_method'];
    $deliveryFee  = (float)$order['delivery_fee'] > 0
        ? number_format($order['delivery_fee'], 0, ',', ' ') . ' €'
        : '<span style="color:#38a169;">Gratuit</span>';

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5f0e8;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <tr><td style="background:#1a1008;padding:36px 48px 28px;text-align:center;border-bottom:2px solid #c8921a;">
    <div style="margin-bottom:14px;">' . $logoTag . '</div>
    <h1 style="margin:0 0 4px;color:#f5f0e8;font-family:Georgia,serif;font-size:28px;font-weight:400;letter-spacing:2px;">AfroStyle</h1>
    <p style="margin:0;color:rgba(245,240,232,0.5);font-size:12px;letter-spacing:3px;">✦ GUYANCOURT (78) ✦</p>
  </td></tr>

  <tr><td style="background:#fff;padding:40px 48px;">
    <p style="margin:0 0 6px;color:#c8921a;font-size:12px;letter-spacing:3px;text-transform:uppercase;">Commande confirmée</p>
    <h2 style="margin:0 0 6px;color:#1a1008;font-family:Georgia,serif;font-size:26px;font-weight:400;">Merci ' . htmlspecialchars($firstName) . ' !</h2>
    <p style="margin:0 0 28px;color:#7a6248;font-size:15px;">Votre commande <strong style="color:#1a1008;">' . $order['order_number'] . '</strong> a bien été reçue.</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
      ' . $itemsHtml . '
      <tr>
        <td style="padding:10px 0;color:#7a6248;font-size:14px;">Livraison</td>
        <td style="padding:10px 0;text-align:right;font-size:14px;">' . $deliveryFee . '</td>
      </tr>
      <tr>
        <td style="padding:14px 0 0;font-weight:700;font-size:16px;color:#1a1008;border-top:2px solid #1a1008;">Total</td>
        <td style="padding:14px 0 0;text-align:right;font-weight:700;font-size:18px;color:#c8921a;border-top:2px solid #1a1008;">' . number_format($order['total_amount'], 0, ',', ' ') . ' €</td>
      </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="background:#fffbf0;border:1px solid rgba(200,146,26,0.2);padding:16px;margin-bottom:28px;">
      <tr>
        <td style="padding:6px 16px;font-size:14px;color:#7a6248;">Adresse</td>
        <td style="padding:6px 16px;font-size:14px;font-weight:600;color:#1a1008;">' . htmlspecialchars($order['delivery_address'] ?? '—') . '</td>
      </tr>
      <tr>
        <td style="padding:6px 16px;font-size:14px;color:#7a6248;">Ville</td>
        <td style="padding:6px 16px;font-size:14px;font-weight:600;color:#1a1008;">' . htmlspecialchars($order['delivery_city'] ?? '—') . '</td>
      </tr>
      <tr>
        <td style="padding:6px 16px;font-size:14px;color:#7a6248;">Paiement</td>
        <td style="padding:6px 16px;font-size:14px;font-weight:600;color:#1a1008;">' . $paymentLabel . '</td>
      </tr>
      ' . (!empty($order['sender_phone']) ? '
      <tr>
        <td style="padding:6px 16px;font-size:14px;color:#7a6248;">N° ayant effectué le paiement</td>
        <td style="padding:6px 16px;font-size:14px;font-weight:600;color:#1a1008;">' . htmlspecialchars($order['sender_phone']) . '</td>
      </tr>' : '') . '
    </table>

    <p style="margin:0 0 20px;color:#555;font-size:15px;line-height:1.7;">Vous serez contacté par téléphone sous 24h pour confirmer et organiser la livraison.</p>

    <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
      <a href="' . SITE_URL . '/suivi.php?ref=' . $order['order_number'] . '" style="display:inline-block;background:#c8921a;color:#1a1008;text-decoration:none;font-size:13px;font-weight:bold;letter-spacing:3px;text-transform:uppercase;padding:16px 48px;">
        Suivre ma commande
      </a>
    </td></tr></table>
  </td></tr>

  <tr><td style="background:#1a1008;padding:28px 48px;text-align:center;">
    <p style="margin:0 0 4px;color:#c8921a;font-size:12px;letter-spacing:2px;">AfroStyle Atelier</p>
    <p style="margin:0 0 4px;color:rgba(245,240,232,0.5);font-size:12px;">📍 Guyancourt, Yvelines (78) &nbsp;|&nbsp; 📞 +33 6 44 72 87 30</p>
    <p style="margin:0;color:rgba(245,240,232,0.3);font-size:11px;">&copy; ' . date('Y') . ' AfroStyle — Tous droits réservés</p>
  </td></tr>

</table></td></tr></table></body></html>';

    $embeds = file_exists($logoPath) ? ['afrostyle_logo' => $logoPath] : [];
    return sendMail($email, $firstName, $subject, $html, $embeds);
}

// ─── EMAIL CHANGEMENT DE STATUT ───────────────────────────────────────────────
function emailStatusUpdate(string $email, string $firstName, array $order, string $newStatus, string $note = '', string $trackingNumber = '', string $carrier = '', string $photoFilename = ''): bool {
    $logoPath = __DIR__ . '/../logo.jpg';
    $logoTag  = file_exists($logoPath)
        ? '<img src="cid:afrostyle_logo" alt="AfroStyle" style="height:80px;width:80px;object-fit:contain;border-radius:50%;">'
        : '<span style="color:#c8921a;font-size:22px;font-weight:bold;">AfroStyle</span>';

    $statusLabels = [
        'pending'       => ['label' => 'En attente',     'icon' => '⏳', 'color' => '#f6ad55', 'msg' => 'Votre commande est en attente de validation.'],
        'confirmed'     => ['label' => 'Confirmée',      'icon' => '✅', 'color' => '#48bb78', 'msg' => 'Votre commande a été confirmée ! Nous allons commencer la préparation.'],
        'in_production' => ['label' => 'En confection',  'icon' => '🪡', 'color' => '#63b3ed', 'msg' => 'Vos articles sont en cours de confection par nos artisans. Nous vous tiendrons informé.'],
        'shipped'       => ['label' => 'Expédiée',       'icon' => '🚚', 'color' => '#9f7aea', 'msg' => 'Votre commande a été expédiée ! Vous la recevrez très bientôt.'],
        'delivered'     => ['label' => 'Livrée',         'icon' => '🎉', 'color' => '#38a169', 'msg' => 'Votre commande a été livrée. Nous espérons que vous êtes satisfait(e) !'],
        'cancelled'     => ['label' => 'Annulée',        'icon' => '❌', 'color' => '#e53e3e', 'msg' => 'Votre commande a été annulée. Contactez-nous pour plus d\'informations.'],
    ];

    $st      = $statusLabels[$newStatus] ?? ['label' => $newStatus, 'icon' => '📦', 'color' => '#c8921a', 'msg' => ''];
    $subject = $st['icon'] . ' Commande ' . $order['order_number'] . ' — ' . $st['label'];

    // Build tracking section for shipped status
    $trackingSection = '';
    if ($newStatus === 'shipped' && $trackingNumber !== '') {
        $trackUrls = [
            'Chronopost'    => 'https://www.chronopost.fr/tracking-no-cms/suivi-page?listeNumerosLT=' . urlencode($trackingNumber),
            'Colissimo'     => 'https://www.laposte.fr/outils/suivre-vos-envois?code=' . urlencode($trackingNumber),
            'DHL Express'   => 'https://www.dhl.com/fr-fr/home/tracking.html?tracking-id=' . urlencode($trackingNumber),
            'Mondial Relay' => 'https://www.mondialrelay.fr/suivi-de-colis/?NumColis=' . urlencode($trackingNumber),
            'La Poste'      => 'https://www.laposte.fr/outils/suivre-vos-envois?code=' . urlencode($trackingNumber),
            'UPS'           => 'https://www.ups.com/track?tracknum=' . urlencode($trackingNumber),
            'FedEx'         => 'https://www.fedex.com/fedextrack/?trknbr=' . urlencode($trackingNumber),
        ];
        $trackUrl = $trackUrls[$carrier] ?? '';
        $carrierLabel = $carrier ?: 'Transporteur';
        $trackBtn = $trackUrl
            ? '<a href="' . $trackUrl . '" style="display:inline-block;background:#1a1008;color:#c8921a;text-decoration:none;font-size:13px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;padding:12px 32px;border:2px solid #c8921a;">'
              . '📦 Suivre avec ' . htmlspecialchars($carrierLabel) . '</a>'
            : '<span style="font-size:14px;color:#1a1008;font-weight:bold;">' . htmlspecialchars($trackingNumber) . '</span>';

        $photoHtml = '';
        if ($photoFilename !== '') {
            $photoHtml = '<div style="margin-top:16px;text-align:center;"><img src="cid:colis_photo_cid" alt="Photo du colis" style="max-width:100%;border-radius:4px;border:1px solid #e0d8cc;"></div>';
        }

        $trackingSection = '
    <div style="background:#fffbf0;border:1px solid rgba(200,146,26,0.3);padding:20px 24px;margin:20px 0;text-align:center;">
      <p style="margin:0 0 12px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#7a6248;">Numéro de suivi</p>
      <p style="margin:0 0 14px;font-size:18px;font-weight:bold;color:#1a1008;letter-spacing:1px;">' . htmlspecialchars($trackingNumber) . '</p>
      ' . $trackBtn . '
      ' . $photoHtml . '
    </div>';
    }

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f5f0e8;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <tr><td style="background:#1a1008;padding:28px 48px;text-align:center;border-bottom:2px solid #c8921a;">
    <div style="margin-bottom:10px;">' . $logoTag . '</div>
    <h1 style="margin:0;color:#f5f0e8;font-family:Georgia,serif;font-size:24px;font-weight:400;letter-spacing:2px;">AfroStyle</h1>
  </td></tr>

  <tr><td style="background:#fff;padding:40px 48px;">

    <div style="text-align:center;margin-bottom:28px;">
      <div style="font-size:3rem;margin-bottom:12px;">' . $st['icon'] . '</div>
      <p style="margin:0 0 6px;font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#c8921a;">Mise à jour de commande</p>
      <h2 style="margin:0;font-family:Georgia,serif;font-size:24px;font-weight:400;color:#1a1008;">' . $st['label'] . '</h2>
    </div>

    <div style="background:' . $st['color'] . '18;border-left:4px solid ' . $st['color'] . ';padding:16px 20px;margin-bottom:24px;">
      <p style="margin:0;font-size:15px;color:#1a1008;line-height:1.6;">' . $st['msg'] . '</p>
    </div>

    <p style="margin:0 0 8px;color:#7a6248;font-size:14px;">Bonjour <strong style="color:#1a1008;">' . htmlspecialchars($firstName) . '</strong>,</p>
    <p style="margin:0 0 8px;color:#7a6248;font-size:14px;">Commande : <strong style="color:#1a1008;">' . $order['order_number'] . '</strong></p>
    ' . ($note ? '<p style="margin:12px 0;color:#555;font-size:14px;background:#f9f6f0;padding:12px 16px;border-radius:2px;">📝 ' . htmlspecialchars($note) . '</p>' : '') . '
    ' . $trackingSection . '

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:28px;"><tr><td align="center">
      <a href="' . SITE_URL . '/suivi.php?ref=' . $order['order_number'] . '" style="display:inline-block;background:#c8921a;color:#1a1008;text-decoration:none;font-size:13px;font-weight:bold;letter-spacing:3px;text-transform:uppercase;padding:14px 40px;">
        Voir le suivi
      </a>
    </td></tr></table>

  </td></tr>

  <tr><td style="background:#1a1008;padding:24px 48px;text-align:center;">
    <p style="margin:0 0 4px;color:#c8921a;font-size:12px;letter-spacing:2px;">AfroStyle Atelier</p>
    <p style="margin:0;color:rgba(245,240,232,0.4);font-size:11px;">📍 Guyancourt (78) &nbsp;|&nbsp; +33 6 44 72 87 30 &nbsp;|&nbsp; ' . date('Y') . ' AfroStyle</p>
  </td></tr>

</table></td></tr></table></body></html>';

    $embeds = file_exists($logoPath) ? ['afrostyle_logo' => $logoPath] : [];
    if ($photoFilename !== '') {
        $colisPhotoPath = __DIR__ . '/../uploads/colis/' . $photoFilename;
        if (file_exists($colisPhotoPath)) {
            $embeds['colis_photo_cid'] = $colisPhotoPath;
        }
    }
    return sendMail($email, $firstName, $subject, $html, $embeds);
}

// ─── EMAIL CONFIRMATION PAIEMENT + FACTURE PDF ────────────────────────────────
function emailPaymentConfirmedWithInvoice(string $email, string $firstName, array $order, array $items, string $pdfString): bool {
    $logoPath = __DIR__ . '/../logo.jpg';
    $logoTag  = file_exists($logoPath)
        ? '<img src="cid:afrostyle_logo" alt="AfroStyle" style="height:80px;width:80px;object-fit:contain;border-radius:50%;">'
        : '<span style="color:#c8921a;font-size:22px;font-weight:bold;">AfroStyle</span>';

    $subject = '✅ Paiement confirmé — Commande ' . $order['order_number'];

    $itemsHtml = '';
    foreach ($items as $item) {
        $lineTotal = (float)($item['unit_price'] ?? 0) * (int)($item['quantity'] ?? 1);
        $itemsHtml .= '
        <tr>
          <td style="padding:10px 0;border-bottom:1px solid #f0ebe0;">
            <strong style="color:#1a1008;font-size:14px;">' . htmlspecialchars($item['product_name'] ?? '') . '</strong>
            <div style="color:#7a6248;font-size:12px;margin-top:2px;">Taille : ' . htmlspecialchars($item['size'] ?? '—') . ' &nbsp;|&nbsp; Qté : ' . (int)($item['quantity'] ?? 1) . '</div>
          </td>
          <td style="padding:10px 0;border-bottom:1px solid #f0ebe0;text-align:right;font-weight:bold;color:#1a1008;">
            ' . number_format($lineTotal, 2, ',', ' ') . ' €
          </td>
        </tr>';
    }

    $deliveryFee = (float)($order['delivery_fee'] ?? 0);
    $deliveryStr = $deliveryFee > 0
        ? number_format($deliveryFee, 2, ',', ' ') . ' €'
        : '<span style="color:#38a169;">Gratuit</span>';

    $html = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f5f0e8;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f0e8;padding:40px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

  <tr><td style="background:#1a1008;padding:28px 48px;text-align:center;border-bottom:2px solid #c8921a;">
    <div style="margin-bottom:10px;">' . $logoTag . '</div>
    <h1 style="margin:0;color:#f5f0e8;font-family:Georgia,serif;font-size:24px;font-weight:400;letter-spacing:2px;">AfroStyle78</h1>
    <p style="margin:4px 0 0;color:rgba(245,240,232,0.5);font-size:11px;letter-spacing:3px;">✦ GUYANCOURT, YVELINES (78) ✦</p>
  </td></tr>

  <tr><td style="background:#fff;padding:40px 48px;">

    <div style="text-align:center;margin-bottom:28px;">
      <div style="font-size:3rem;margin-bottom:12px;">✅</div>
      <p style="margin:0 0 6px;font-size:12px;letter-spacing:3px;text-transform:uppercase;color:#c8921a;">Paiement confirmé</p>
      <h2 style="margin:0;font-family:Georgia,serif;font-size:24px;font-weight:400;color:#1a1008;">Merci ' . htmlspecialchars($firstName) . ' !</h2>
    </div>

    <div style="background:#38a16918;border-left:4px solid #38a169;padding:16px 20px;margin-bottom:24px;">
      <p style="margin:0;font-size:15px;color:#1a1008;line-height:1.6;">
        Votre paiement pour la commande <strong>' . htmlspecialchars($order['order_number']) . '</strong> a été reçu et confirmé.
        Nous allons maintenant préparer votre commande avec soin.
      </p>
    </div>

    <p style="margin:0 0 16px;color:#7a6248;font-size:14px;">Voici le récapitulatif de votre commande :</p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
      ' . $itemsHtml . '
      <tr>
        <td style="padding:10px 0;color:#7a6248;font-size:13px;">Livraison</td>
        <td style="padding:10px 0;text-align:right;font-size:13px;">' . $deliveryStr . '</td>
      </tr>
      <tr>
        <td style="padding:14px 0 0;font-weight:bold;font-size:15px;color:#1a1008;border-top:2px solid #1a1008;">Total TTC</td>
        <td style="padding:14px 0 0;text-align:right;font-weight:bold;font-size:17px;color:#c8921a;border-top:2px solid #1a1008;">' . number_format((float)($order['total_amount'] ?? 0), 2, ',', ' ') . ' €</td>
      </tr>
    </table>

    <p style="margin:0 0 24px;color:#555;font-size:14px;line-height:1.7;">
      Votre facture est jointe à cet email en PDF. Conservez-la pour vos archives.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
      <a href="' . SITE_URL . '/suivi.php?ref=' . $order['order_number'] . '"
         style="display:inline-block;background:#c8921a;color:#1a1008;text-decoration:none;
                font-size:13px;font-weight:bold;letter-spacing:3px;text-transform:uppercase;padding:14px 40px;">
        Suivre ma commande
      </a>
    </td></tr></table>

  </td></tr>

  <tr><td style="background:#1a1008;padding:24px 48px;text-align:center;">
    <p style="margin:0 0 4px;color:#c8921a;font-size:12px;letter-spacing:2px;">AfroStyle78</p>
    <p style="margin:0;color:rgba(245,240,232,0.4);font-size:11px;">Guyancourt, Yvelines (78) &nbsp;|&nbsp; &copy; ' . date('Y') . ' AfroStyle78</p>
  </td></tr>

</table></td></tr></table></body></html>';

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = MAIL_ENCRYPTION;
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($email, $firstName);
        $mail->addReplyTo(MAIL_FROM_EMAIL, MAIL_FROM_NAME);

        // Logo embarqué
        if (file_exists($logoPath)) {
            $mail->addEmbeddedImage($logoPath, 'afrostyle_logo');
        }

        // Facture PDF en pièce jointe
        $mail->addStringAttachment($pdfString, 'Facture-' . $order['order_number'] . '.pdf', 'base64', 'application/pdf');

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = 'Votre paiement pour la commande ' . $order['order_number'] . ' a été confirmé. Votre facture est en pièce jointe.';

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer invoice error: ' . $mail->ErrorInfo);
        return false;
    }
}
