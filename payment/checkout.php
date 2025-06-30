<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // Corrected path: one level up from payment/

// Enable error reporting for debugging
ini_set('display_errors', 0); // Hide errors from users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Logging function for debugging
function logError($message) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    file_put_contents("$log_dir/payment_errors.log", date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL, FILE_APPEND);
}

// Ensuring cart is not empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    logError('Checkout: Cart is empty or not set');
    header('Location: ../cart/view.php');
    exit;
}

// Log session data for debugging
logError('Checkout: Initial session data - ' . print_r($_SESSION, true));

// Handling form input for guest and logged-in users
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);
        $phone_number = filter_input(INPUT_POST, 'phone_number', FILTER_SANITIZE_STRING);
        logError("Checkout: Received POST data - Email: '$email', Address: '$address', Phone: '$phone_number'");

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Por favor, ingrese un correo electrónico válido.');
        }
        if (!$address || strlen(trim($address)) < 5) {
            throw new Exception('Por favor, ingrese una dirección de envío válida (mínimo 5 caracteres).');
        }
        if (!$phone_number || !preg_match('/^[\d\s\-\+\(\)]{7,20}$/', $phone_number)) {
            throw new Exception('Por favor, ingrese un número de teléfono válido.');
        }

        $_SESSION['guest_email'] = $email;
        $_SESSION['guest_address'] = trim($address);
        $_SESSION['guest_phone_number'] = $phone_number;
        logError("Checkout: Session data set - Email: '$email', Address: '$address', Phone: '$phone_number'");
    } catch (Exception $e) {
        logError('Checkout: Form validation error - ' . $e->getMessage());
        $error = $e->getMessage();
    }
}

// Fetching cart items for display
try {
    $cart_items = [];
    $total = 0;
    $product_ids = array_filter(array_keys($_SESSION['cart']), 'is_numeric');
    if (empty($product_ids)) {
        throw new Exception('No valid product IDs in cart');
    }

    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $stmt = $db->prepare("SELECT product_id, name, price FROM products WHERE product_id IN ($placeholders)");
    if (!$stmt) {
        throw new Exception('Failed to prepare products query: ' . $db->error);
    }
    $stmt->bind_param(str_repeat('i', count($product_ids)), ...$product_ids);
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute products query: ' . $db->error);
    }
    $result = $stmt->get_result();
    while ($product = $result->fetch_assoc()) {
        $cart_items[$product['product_id']] = [
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $_SESSION['cart'][$product['product_id']],
        ];
        $total += $product['price'] * $_SESSION['cart'][$product['product_id']];
    }
    if (empty($cart_items)) {
        throw new Exception('No products found for cart items');
    }

    // Storing total for payment processing
    $_SESSION['payment_total'] = $total;
    $_SESSION['payment_user_id'] = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
    logError("Checkout: Cart items fetched - Total: $total");
} catch (Exception $e) {
    logError('Checkout: Cart processing error - ' . $e->getMessage());
    $error = 'Error al procesar el carrito: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Pago</h2>
        <?php if (isset($error) || isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error ?? $_GET['error']); ?></div>
        <?php endif; ?>
        <?php if (!isset($_SESSION['guest_email']) || !isset($_SESSION['guest_address']) || !isset($_SESSION['guest_phone_number'])): ?>
            <form method="POST" class="mb-4">
                <div class="mb-3">
                    <label for="email" class="form-label">Correo Electrónico (para confirmación del pedido)</label>
                    <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Dirección de Envío</label>
                    <input type="text" class="form-control" id="address" name="address" required placeholder="Ejemplo: Calle Gran Vía, Madrid, España" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                    <button type="button" class="btn btn-sm btn-info mt-2" onclick="getCurrentLocation()">Usar Ubicación Actual</button>
                </div>
                <div class="mb-3">
                    <label for="phone_number" class="form-label">Número de Teléfono</label>
                    <input type="text" class="form-control" id="phone_number" name="phone_number" required placeholder="Ejemplo: +34 123 456 789" value="<?php echo isset($_POST['phone_number']) ? htmlspecialchars($_POST['phone_number']) : ''; ?>">
                </div>
                <button type="submit" class="btn btn-primary">Continuar</button>
            </form>
        <?php else: ?>
            <h4>Resumen del Pedido</h4>
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart_items as $product_id => $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                            <td>€<?php echo number_format($item['price'], 2); ?></td>
                            <td><?php echo $item['quantity']; ?></td>
                            <td>€<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                        <td>€<?php echo number_format($total, 2); ?></td>
                    </tr>
                </tfoot>
            </table>
            <h4>Seleccionar Método de Pago</h4>
            <div class="row">
                <div class="col-md-6">
                    <a href="stripe.php" class="btn btn-primary w-100 mb-3">Pagar con Stripe</a>
                </div>
                <div class="col-md-6">
                    <a href="paypal.php" class="btn btn-primary w-100 mb-3">Pagar con PayPal</a>
                </div>
            </div>
        <?php endif; ?>
        <a href="../cart/view.php" class="btn btn-secondary">Volver al Carrito</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function getCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    async (position) => {
                        const { latitude, longitude } = position.coords;
                        try {
                            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}`);
                            const data = await response.json();
                            if (data && data.display_name) {
                                document.getElementById('address').value = data.display_name;
                            } else {
                                alert('No se pudo obtener la dirección. Por favor, ingrese manualmente.');
                            }
                        } catch (error) {
                            console.error('Error de geolocalización:', error);
                            alert('Error al obtener la ubicación. Por favor, ingrese la dirección manualmente.');
                        }
                    },
                    (error) => {
                        console.error('Error de geolocalización:', error);
                        alert('Geolocalización no disponible o permiso denegado. Por favor, ingrese la dirección manualmente.');
                    }
                );
            } else {
                alert('La geolocalización no es compatible con su navegador. Por favor, ingrese la dirección manualmente.');
            }
        }
    </script>
</body>
</html>