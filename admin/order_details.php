<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Ensuring user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}
$stmt = $db->prepare('SELECT is_admin FROM users WHERE user_id = ?');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user || !$user['is_admin']) {
    header('Location: ../products/list.php');
    exit;
}

// Fetching order details
$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    header('Location: orders.php');
    exit;
}

$stmt = $db->prepare('
    SELECT o.order_id, o.user_id, o.email, o.total_amount, o.status, o.created_at, o.address, o.phone_number,
           u.first_name, u.last_name
    FROM orders o
    LEFT JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ?
');
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) {
    header('Location: orders.php');
    exit;
}

// Fetching order items
$stmt = $db->prepare('
    SELECT p.name, oi.quantity, oi.unit_price
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
');
$stmt->bind_param('i', $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles del Pedido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Detalles del Pedido</h2>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Pedido #<?php echo $order['order_id']; ?></h5>
                <p><strong>Cliente:</strong> <?php echo $order['user_id'] ? htmlspecialchars($order['first_name'] . ' ' . $order['last_name'] . ' (' . $order['email'] . ')') : htmlspecialchars($order['email'] ?? 'Invitado'); ?></p>
                <p><strong>Monto Pagado:</strong> €<?php echo number_format($order['total_amount'], 2); ?></p>
                <p><strong>Estado:</strong> <?php echo htmlspecialchars($order['status'] === 'pending' ? 'Pendiente' : ($order['status'] === 'completed' ? 'Completado' : 'Cancelado')); ?></p>
                <p><strong>Fecha:</strong> <?php echo $order['created_at']; ?></p>
                <p><strong>Dirección:</strong> <?php echo htmlspecialchars($order['address'] ?? 'No proporcionada'); ?></p>
                <p><strong>Número de Teléfono:</strong> <?php echo htmlspecialchars($order['phone_number'] ?? 'No proporcionado'); ?></p>
                
                <h5 class="mt-4">Artículos del Pedido</h5>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>€<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>€<?php echo number_format($item['unit_price'] * $item['quantity'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <a href="orders.php" class="btn btn-secondary">Volver a Pedidos</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>