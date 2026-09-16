<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: ' . ADMIN_URL . '/login.php');
    exit;
}

// Protection CSRF de tout l'admin en un seul point : chaque page passant par
// auth.php voit ses POST verifies, sans risque d'en oublier une.
// Les formulaires doivent inclure csrfField().
csrfCheck();
