<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/xml; charset=utf-8');

$db = getDB();
$products = $db->query("
    SELECT p.*, c.name as cat_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.active = 1 AND p.stock > 0
    ORDER BY p.id DESC
")->fetchAll();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . "\n";
echo '<channel>' . "\n";
echo '<title>AfroStyle78 — Mode Africaine Sur-Mesure</title>' . "\n";
echo '<link>https://afrostyle78.com</link>' . "\n";
echo '<description>Boutique de mode africaine sur-mesure à Guyancourt</description>' . "\n";

foreach ($products as $p) {
    $price = $p['promo_price'] ?: $p['price'];
    $image = !empty($p['image']) ? UPLOADS_URL . $p['image'] : SITE_URL . '/logo.jpg';
    $url   = SITE_URL . '/produit?slug=' . urlencode($p['slug']);
    $desc  = strip_tags($p['description'] ?? '');
    if (empty(trim($desc))) {
        $desc = $p['name'] . ' — ' . ($p['cat_name'] ?? 'Mode Africaine') . '. Création africaine sur-mesure par AfroStyle78, spécialiste mode africaine à Guyancourt (78). Livraison France & international.';
    }
    $desc  = htmlspecialchars(mb_substr($desc, 0, 500));
    $name  = htmlspecialchars($p['name']);
    $cat   = htmlspecialchars($p['cat_name'] ?? 'Mode Africaine');
    $id    = (int)$p['id'];

    echo "<item>\n";
    echo "  <g:id>{$id}</g:id>\n";
    echo "  <g:title>{$name}</g:title>\n";
    echo "  <g:description>{$desc}</g:description>\n";
    echo "  <g:link>{$url}</g:link>\n";
    echo "  <g:image_link>" . htmlspecialchars($image) . "</g:image_link>\n";
    echo "  <g:price>" . number_format($price, 2, '.', '') . " EUR</g:price>\n";
    echo "  <g:availability>in stock</g:availability>\n";
    echo "  <g:condition>new</g:condition>\n";
    echo "  <g:brand>AfroStyle78</g:brand>\n";
    echo "  <g:google_product_category>Apparel &amp; Accessories &gt; Clothing</g:google_product_category>\n";
    echo "  <g:product_type>" . $cat . "</g:product_type>\n";

    // Images supplémentaires
    $images = json_decode($p['images'] ?? '[]', true);
    foreach (array_slice($images, 0, 9) as $img) {
        echo "  <g:additional_image_link>" . htmlspecialchars(UPLOADS_URL . $img) . "</g:additional_image_link>\n";
    }

    // Tailles disponibles
    $sizes = json_decode($p['available_sizes'] ?? '[]', true);
    if (!empty($sizes)) {
        echo "  <g:size>" . htmlspecialchars(implode(', ', $sizes)) . "</g:size>\n";
    }

    echo "</item>\n";
}

echo '</channel>' . "\n";
echo '</rss>' . "\n";
