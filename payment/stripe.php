<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Stripe\Stripe;
use Stripe\Checkout\Session;

// Verificar que el carrito y el total estén establecidos
if (!isset($_SESSION['cart']) || empty($_SESSION['cart']) || !isset($_SESSION['payment_total'])) {
    header('Location: ../cart/view.php');
    exit;
}

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

// Crear ítems para Stripe Checkout
$line_items = [];
foreach ($_SESSION['cart'] as $product_id => $quantity) {
    $stmt = $db->prepare('SELECT name, price FROM products WHERE product_id = ?');
    $stmt->bind_param('i', $product_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $product = $result->fetch_assoc();
    if ($product) {
        $line_items[] = [
            'price_data' => [
                'currency' => 'eur',
                'product_data' => [
                    'name' => $product['name'],
                ],
                'unit_amount' => $product['price'] * 100, // En centavos
            ],
            'quantity' => $quantity,
        ];
    }
}

// Crear sesión de Stripe Checkout con recolección de dirección
try {
    $session = Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $line_items,
        'mode' => 'payment',
        'success_url' => 'http://localhost/ecommerce/payment/process.php?session_id={CHECKOUT_SESSION_ID}&method=stripe',
        'cancel_url' => 'http://localhost/ecommerce/payment/checkout.php?error=Pago cancelado',
        'billing_address_collection' => 'required', // Requerir dirección de facturación
    ]);
    header('Location: ' . $session->url);
    exit;
} catch (Exception $e) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    file_put_contents("$log_dir/payment_errors.log", date('Y-m-d H:i:s') . ': Stripe: ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
    header('Location: checkout.php?error=Error al iniciar el pago con Stripe: ' . $e->getMessage());
    exit;
}
?>