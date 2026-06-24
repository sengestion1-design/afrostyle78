<?php
require_once 'includes/auth.php';
$db = getDB();
$results = [];

$cols = [
    "ALTER TABLE orders ADD COLUMN paydunya_token VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE orders ADD COLUMN wave_session_id VARCHAR(255) DEFAULT NULL",
    "ALTER TABLE orders ADD COLUMN confirm_token VARCHAR(64) DEFAULT NULL",
    "ALTER TABLE orders ADD COLUMN sender_phone VARCHAR(30) DEFAULT NULL",
];

foreach ($cols as $sql) {
    try {
        $db->exec($sql);
        preg_match('/ADD COLUMN (\w+)/', $sql, $m);
        $results[] = "✅ Colonne <strong>{$m[1]}</strong> ajoutée.";
    } catch (PDOException $e) {
        preg_match('/ADD COLUMN (\w+)/', $sql, $m);
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            $results[] = "ℹ️ Colonne <strong>{$m[1]}</strong> déjà présente.";
        } else {
            $results[] = "❌ Erreur <strong>{$m[1]}</strong> : " . htmlspecialchars($e->getMessage());
        }
    }
}

@unlink(__FILE__);
?>
<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;">
<h2>Migration PayDunya</h2>
<?php foreach ($results as $r) echo "<p>$r</p>"; ?>
<p><a href="parametres.php">← Retour paramètres</a></p>
</body></html>
