<?php
require_once '../config/config.php';
require_once '../config/database.php';
$db = getDB();

$products = $db->query("SELECT p.*, c.name as cat_name FROM products p LEFT JOIN categories c ON p.category_id=c.id WHERE p.active=1 ORDER BY p.id DESC")->fetchAll();

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . "\n";
echo '<channel>' . "\n";
echo '<title>AfroStyle78 — Mode Africaine Sur-Mesure</title>' . "\n";
echo '<link>https://afrostyle78.com</link>' . "\n";
echo '<description>Catalogue AfroStyle78</description>' . "\n";

foreach ($products as $p) {
    $price    = $p['promo_price'] ?: $p['price'];
    $image    = !empty($p['image']) ? 'https://afrostyle78.com/uploads/products/' . $p['image'] : 'https://afrostyle78.com/logo.jpg';
    $url      = 'https://afrostyle78.com/produit?slug=' . urlencode($p['slug']);
    $desc     = !empty($p['description']) ? htmlspecialchars(strip_tags($p['description'])) : htmlspecialchars($p['name']);
    $category = !empty($p['cat_name']) ? htmlspecialchars($p['cat_name']) : 'Mode Africaine';
    $stock    = $p['stock'] > 0 ? 'in stock' : 'out of stock';

    echo '<item>' . "\n";
    echo '  <g:id>' . $p['id'] . '</g:id>' . "\n";
    echo '  <g:title>' . htmlspecialchars($p['name']) . '</g:title>' . "\n";
    echo '  <g:description>' . $desc . '</g:description>' . "\n";
    echo '  <g:link>' . $url . '</g:link>' . "\n";
    echo '  <g:image_link>' . $image . '</g:image_link>' . "\n";
    echo '  <g:price>' . number_format($price, 2, '.', '') . ' EUR</g:price>' . "\n";
    echo '  <g:availability>' . $stock . '</g:availability>' . "\n";
    echo '  <g:condition>new</g:condition>' . "\n";
    echo '  <g:brand>AfroStyle78</g:brand>' . "\n";
    echo '  <g:google_product_category>Apparel &amp; Accessories &gt; Clothing</g:google_product_category>' . "\n";
    echo '  <g:product_type>' . $category . '</g:product_type>' . "\n";
    echo '</item>' . "\n";
}

echo '</channel>' . "\n";
echo '</rss>' . "\n";
