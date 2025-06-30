<?php

session_start();
require_once __DIR__ . '/../config/db.php';

// Logging function
function logError($message) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    file_put_contents("$log_dir/public_errors.log", date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL, FILE_APPEND);
}

// Verificar conexión a la base de datos
if (!$db || $db->connect_error) {
    logError('Index: Database connection failed - ' . ($db ? $db->connect_error : 'No database object'));
    $error = 'Error de conexión a la base de datos';
} else {
    // Obtener productos destacados
    $stmt = $db->prepare('SELECT product_id, name, price FROM products ORDER BY product_id DESC LIMIT 3');
    if (!$stmt) {
        logError('Index: Failed to prepare statement - ' . $db->error);
        $error = 'Error al preparar la consulta de productos';
    } elseif (!$stmt->execute()) {
        logError('Index: Failed to fetch featured products - ' . $stmt->error);
        $error = 'Error al cargar los productos destacados';
    } else {
        $result = $stmt->get_result();
        $products = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tienda Online - Inicio</title>
    <link href="/ecommerce/public/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/header.php'; ?>
    <div class="container mt-5">
        <h2>Bienvenido a Nuestra Tienda Online</h2>
        <p>Explora nuestros productos y disfruta de una experiencia de compra fácil y segura.</p>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <h3>Productos Destacados</h3>
        <?php if (!empty($products)): ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text">Precio: €<?php echo number_format($product['price'], 2); ?></p>
                                <a href="/ecommerce/products/view.php?product_id=<?php echo $product['product_id']; ?>" class="btn btn-primary">Ver Detalles</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No hay productos destacados disponibles.</p>
        <?php endif; ?>
        <a href="/ecommerce/products/list.php" class="btn btn-primary">Ver Todos los Productos</a>
    </div>
    <script src="/ecommerce/public/js/bootstrap.bundle.min.js"></script>
</body>
</html>