<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use PayPalCheckoutSdk\Core\PayPalHttpClient;
use PayPalCheckoutSdk\Core\SandboxEnvironment;
use PayPalCheckoutSdk\Orders\OrdersCreateRequest;

// Logging function for debugging
function logError($message) {
    file_put_contents(__DIR__ . '/../logs/payment_errors.log', date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL, FILE_APPEND);
}

// Ensuring cart and total are set
if (!isset($_SESSION['cart']) || empty($_SESSION['cart']) || !isset($_SESSION['payment_total'])) {
    logError('PayPal: Cart or total not set');
    header('Location: ../cart/view.php');
    exit;
}

// Ensuring guest email is set if not logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION['guest_email'])) {
    logError('PayPal: Guest email not set');
    header('Location: checkout.php');
    exit;
}

// Loading environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Setting up PayPal client
try {
    $environment = new SandboxEnvironment($_ENV['PAYPAL_CLIENT_ID'], $_ENV['PAYPAL_SECRET']);
    $client = new PayPalHttpClient($environment);
} catch (Exception $e) {
    logError('PayPal: Failed to set up client - ' . $e->getMessage());
    $error = 'Payment configuration error. Please try again later.';
}

// Fetching cart items
$cart_items = [];
$total = 0;
$product_ids = array_keys($_SESSION['cart']);
$placeholders = implode(',', array_fill(0, count($product_ids), '?'));
$stmt = $db->prepare("SELECT product_id, name, price FROM products WHERE product_id IN ($placeholders)");
$stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
if (!$stmt->execute()) {
    logError('PayPal: Database query failed - ' . $db->error);
    $error = 'Database error. Please try again.';
} else {
    $result = $stmt->get_result();
    while ($product = $result->fetch_assoc()) {
        $cart_items[$product['product_id']] = [
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $_SESSION['cart'][$product['product_id']],
        ];
        $total += $product['price'] * $_SESSION['cart'][$product['product_id']];
    }
}

// Creating PayPal order
if (!isset($error)) {
    try {
        $request = new OrdersCreateRequest();
        $request->prefer('return=representation');
        $request->body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => 'USD',
                        'value' => sprintf('%.2f', $total), // Ensure no commas
                        'breakdown' => [
                            'item_total' => [
                                'currency_code' => 'USD',
                                'value' => sprintf('%.2f', $total), // Ensure no commas
                            ],
                        ],
                    ],
                    'items' => array_map(function ($item, $product_id) {
                        return [
                            'name' => $item['name'],
                            'unit_amount' => [
                                'currency_code' => 'USD',
                                'value' => sprintf('%.2f', $item['price']), // Ensure no commas
                            ],
                            'quantity' => $item['quantity'],
                        ];
                    }, $cart_items, array_keys($cart_items)),
                ],
            ],
            'application_context' => [
                'return_url' => 'http://localhost/ecommerce/payment/process.php?method=paypal',
                'cancel_url' => 'http://localhost/ecommerce/payment/checkout.php',
            ],
        ];

        $response = $client->execute($request);
        $approve_url = null;
        foreach ($response->result->links as $link) {
            if ($link->rel === 'approve') {
                $approve_url = $link->href;
                break;
            }
        }
        if ($approve_url) {
            $_SESSION['paypal_order_id'] = $response->result->id;
            header("Location: $approve_url");
            exit;
        } else {
            logError('PayPal: No approve URL found in response');
            $error = 'Failed to initiate PayPal payment.';
        }
    } catch (Exception $e) {
        logError('PayPal: Failed to create order - ' . $e->getMessage());
        $error = 'Failed to initiate PayPal payment: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPal Checkout</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>PayPal Checkout</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <a href="checkout.php" class="btn btn-secondary">Back to Checkout</a>
        <?php else: ?>
            <p>Redirecting to PayPal...</p>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>