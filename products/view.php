<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Validating product ID
$product_id = filter_input(INPUT_GET, 'product_id', FILTER_VALIDATE_INT);
if (!$product_id) {
    header('Location: list.php');
    exit;
}

// Fetching product details
$stmt = $db->prepare('SELECT p.product_id, p.name, p.price, p.image_url, c.name AS category_name 
                      FROM products p 
                      JOIN categories c ON p.category_id = c.category_id 
                      WHERE p.product_id = ?');
$stmt->bind_param('i', $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    header('Location: list.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2><?php echo htmlspecialchars($product['name']); ?></h2>
        <div class="row">
            <div class="col-md-6">
                <?php if ($product['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                         class="img-fluid" alt="<?php echo htmlspecialchars($product['name']); ?>">
                <?php else: ?>
                    <p class="text-muted">No image available.</p>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category_name']); ?></p>
                <p><strong>Price:</strong> $<?php echo number_format($product['price'], 2); ?></p>
                <a href="list.php" class="btn btn-secondary">Back to Catalog</a>
                <a href="../cart/add.php?product_id=<?php echo $product['product_id']; ?>" 
                   class="btn btn-primary">Add to Cart</a>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>