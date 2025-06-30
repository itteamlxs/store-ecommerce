<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Validating input
$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

if (!$product_id || !$quantity || $quantity < 1 || !isset($_SESSION['cart'][$product_id])) {
    header('Location: view.php');
    exit;
}

// Verifying product exists
$stmt = $db->prepare('SELECT product_id FROM products WHERE product_id = ?');
$stmt->bind_param('i', $product_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    header('Location: view.php');
    exit;
}

// Updating cart quantity
$_SESSION['cart'][$product_id] = $quantity;

// Redirecting to cart view
header('Location: view.php');
exit;
?>