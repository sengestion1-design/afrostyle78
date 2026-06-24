<?php
require_once 'config/config.php';
session_name(SESSION_NAME);
session_start();
$_SESSION['cart'] = [];
echo '<p style="font-size:2rem;text-align:center;margin-top:100px;">✓ Panier vidé. <a href="/boutique">Retour boutique</a></p>';
