<?php
session_start();
require_once __DIR__ . '/../config/db.php';
include __DIR__ . '/../public/header.php';

// Fetching categories for filter dropdown
$stmt = $db->prepare('SELECT category_id, name FROM categories');
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetching products (filtered by category if provided)
$category_id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT);
$query = 'SELECT p.product_id, p.name, p.price, p.image_url, c.name AS category_name 
          FROM products p 
          JOIN categories c ON p.category_id = c.category_id';
$params = [];
if ($category_id) {
    $query .= ' WHERE p.category_id = ?';
    $params[] = $category_id;
}
$stmt = $db->prepare($query);
if ($params) {
    $stmt->bind_param('i', $category_id);
}
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Catalog</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Product Catalog</h2>
        
        <!-- Category Filter -->
        <form method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-4">
                    <select name="category_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>" 
                                    <?php echo $category_id == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <a href="search.php" class="btn btn-outline-secondary">Search Products</a>
                </div>
            </div>
        </form>

        <!-- Product Grid -->
        <div class="row">
            <?php if (empty($products)): ?>
                <p class="text-muted">No products found.</p>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <?php if ($product['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php endif; ?>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text">Category: <?php echo htmlspecialchars($product['category_name']); ?></p>
                                <p class="card-text">Price: $<?php echo number_format($product['price'], 2); ?></p>
                                <a href="view.php?product_id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-primary">View Details</a>
                                <a href="../cart/add.php?product_id=<?php echo $product['product_id']; ?>" 
                                   class="btn btn-outline-success">Add to Cart</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>