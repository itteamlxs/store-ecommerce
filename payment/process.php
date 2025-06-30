<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Stripe\PaymentMethod;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCaptureRequest;

// Logging function for debugging
function logError($message) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    file_put_contents("$log_dir/payment_errors.log", date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL, FILE_APPEND);
}

// Validating session data
$required_session_keys = ['cart', 'payment_total'];
if (!isset($_SESSION['user_id'])) {
    array_push($required_session_keys, 'guest_email', 'guest_address', 'guest_phone_number');
}
foreach ($required_session_keys as $key) {
    if (!isset($_SESSION[$key]) || (in_array($key, ['cart', 'payment_total']) && empty($_SESSION[$key]))) {
        logError("Process: Missing or empty session key - $key");
        header('Location: ../cart/view.php');
        exit;
    }
}

// Loading environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$method = filter_input(INPUT_GET, 'method', FILTER_SANITIZE_STRING);
$success = false;
$error = null;
$card_type = null;
$cardholder_name = null;
$card_last_four = null;
$country = 'Unknown';
$order_id = null;
$address = null;

// Starting database transaction
$db->begin_transaction();

try {
    if ($method === 'stripe') {
        // Handling Stripe payment
        Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
        $session_id = filter_input(INPUT_GET, 'session_id', FILTER_SANITIZE_STRING);
        if (!$session_id) {
            throw new Exception('Invalid Stripe session ID');
        }
        $session = Session::retrieve($session_id);
        if ($session->payment_status !== 'paid') {
            throw new Exception('Stripe payment not completed - Status: ' . $session->payment_status);
        }
        $payment_intent = \Stripe\PaymentIntent::retrieve($session->payment_intent);
        $payment_method = PaymentMethod::retrieve($payment_intent->payment_method);
        $card_type = $payment_method->card->brand ?? 'Unknown';
        $cardholder_name = $payment_method->billing_details->name ?? 'Unknown';
        $card_last_four = $payment_method->card->last4 ?? null;
        $country = $session->customer_details->address->country ?? 'Unknown';

        // Construir la dirección de facturación desde Stripe
        $address_components = $session->customer_details->address ?? null;
        if ($address_components) {
            $address_parts = array_filter([
                $address_components->line1,
                $address_components->line2,
                $address_components->city,
                $address_components->state,
                $address_components->postal_code,
                $address_components->country
            ], function($value) { return !empty($value); });
            $address = implode(', ', $address_parts);
            if (strlen($address) > 255) {
                $address = substr($address, 0, 255); // Asegurar que no exceda el límite de VARCHAR(255)
            }
        }
        if (empty($address)) {
            $address = isset($_SESSION['guest_address']) ? filter_var($_SESSION['guest_address'], FILTER_SANITIZE_STRING) : null;
            if (empty($address) || $address === '0' || strlen(trim($address)) < 5) {
                throw new Exception('Dirección de facturación no válida');
            }
        }
        logError("Stripe: Payment verified successfully, Address: '$address'");
        $success = true;
    } elseif ($method === 'paypal') {
        // Handling PayPal payment
        $environment = new SandboxEnvironment($_ENV['PAYPAL_CLIENT_ID'], $_ENV['PAYPAL_SECRET']);
        $client = new PayPalHttpClient($environment);
        $order_id_paypal = $_SESSION['paypal_order_id'] ?? null;
        if (!$order_id_paypal) {
            throw new Exception('Invalid PayPal order ID');
        }
        $request = new OrdersCaptureRequest($order_id_paypal);
        $response = $client->execute($request);
        if ($response->result->status !== 'COMPLETED') {
            throw new Exception('PayPal payment not completed - Status: ' . $response->result->status);
        }
        $card_type = 'PayPal';
        $cardholder_name = $response->result->payer->name->given_name . ' ' . $response->result->payer->name->surname ?? 'Unknown';
        $card_last_four = null;
        $country = $response->result->payer->address->country_code ?? 'Unknown';
        $address = isset($_SESSION['guest_address']) ? filter_var($_SESSION['guest_address'], FILTER_SANITIZE_STRING) : null;
        if (!isset($_SESSION['user_id']) && (empty($address) || $address === '0' || strlen(trim($address)) < 5)) {
            logError("Process: Invalid or empty guest address for PayPal - Value: '$address'");
            throw new Exception('Dirección no válida para PayPal');
        }
        logError("PayPal: Payment verified successfully, Address: '$address'");
        $success = true;
    } else {
        throw new Exception('Invalid payment method');
    }

    // Creating order
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $email = isset($_SESSION['user_id']) ? null : $_SESSION['guest_email'];
    $phone_number = isset($_SESSION['guest_phone_number']) ? $_SESSION['guest_phone_number'] : null;
    $stmt = $db->prepare('INSERT INTO orders (user_id, email, total_amount, status, address, phone_number, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
    $status = 'completed';
    $stmt->bind_param('isdsis', $user_id, $email, $_SESSION['payment_total'], $status, $address, $phone_number);
    if (!$stmt->execute()) {
        throw new Exception('Failed to create order: ' . $db->error);
    }
    $order_id = $db->insert_id;
    logError("Process: Order created successfully - Order ID: $order_id, Address: '$address'");

    // Inserting order items
    foreach ($_SESSION['cart'] as $product_id => $quantity) {
        $stmt = $db->prepare('SELECT price FROM products WHERE product_id = ?');
        $stmt->bind_param('i', $product_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to fetch product price for product_id ' . $product_id . ': ' . $db->error);
        }
        $result = $stmt->get_result();
        $price_row = $result->fetch_assoc();
        if (!$price_row) {
            throw new Exception('Product not found for product_id ' . $product_id);
        }
        $price = $price_row['price'];
        $stmt = $db->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iiid', $order_id, $product_id, $quantity, $price);
        if (!$stmt->execute()) {
            throw new Exception('Failed to insert order item for product_id ' . $product_id . ': ' . $db->error);
        }
        logError("Process: Order item inserted - Product ID: $product_id, Quantity: $quantity");
    }

    // Inserting payment record
    $stmt = $db->prepare('INSERT INTO payments (order_id, payment_method, card_type, cardholder_name, card_last_four, country, amount, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    $payment_status = 'completed';
    $stmt->bind_param('isssssds', $order_id, $method, $card_type, $cardholder_name, $card_last_four, $country, $_SESSION['payment_total'], $payment_status);
    if (!$stmt->execute()) {
        throw new Exception('Failed to insert payment record: ' . $db->error);
    }
    logError('Process: Payment record inserted successfully');

    // Committing transaction
    $db->commit();
    $success = true;
    logError("Process: Transaction committed successfully for Order ID: $order_id");

    // Clearing session data
    unset($_SESSION['cart'], $_SESSION['payment_total'], $_SESSION['paypal_order_id'], $_SESSION['guest_email'], $_SESSION['guest_address'], $_SESSION['guest_phone_number']);
} catch (Exception $e) {
    // Rolling back transaction on error
    $db->rollback();
    logError('Process: ' . $e->getMessage());
    $error = 'Error al procesar el pago: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resultado del Pago</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Resultado del Pago</h2>
        <?php if ($success && $order_id): ?>
            <div class="alert alert-success">¡Pago completado con éxito! ID del Pedido: <?php echo htmlspecialchars($order_id); ?></div>
            <a href="../products/list.php" class="btn btn-primary">Volver a Productos</a>
        <?php else: ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error ?? 'Ocurrió un error desconocido'); ?></div>
            <a href="checkout.php" class="btn btn-secondary">Volver al Pago</a>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>