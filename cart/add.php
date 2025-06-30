<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Validating product ID
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
if (!$product_id) {
    header('Location: ../products/list.php');
    exit;
}

// Verifying product exists
$stmt = $db->prepare('SELECT product_id FROM products WHERE product_id = ?');
$stmt->bind_param('i', $product_id);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    header('Location: ../products/list.php');
    exit;
}

// Initializing cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Adding or updating product in cart
$_SESSION['cart'][$product_id] = ($_SESSION['cart'][$product_id] ?? 0) + 1;

// Redirecting to cart view
header('Location: view.php');
exit;
?>