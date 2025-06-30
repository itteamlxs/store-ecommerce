<?php
session_start();

// Validating product ID
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
if (!$product_id || !isset($_SESSION['cart'][$product_id])) {
    header('Location: view.php');
    exit;
}

// Removing product from cart
unset($_SESSION['cart'][$product_id]);

// Redirecting to cart view
header('Location: view.php');
exit;
?>