<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Handling search query
$search_term = filter_input(INPUT_GET, 'query', FILTER_SANITIZE_STRING);
$products = [];
$error = null;

if ($search_term) {
    // Validating search term length
    if (strlen(trim($search_term)) < 3) {
        $error = 'Search term must be at least 3 characters long.';
    } else {
        // Searching products by name or category
        $query = 'SELECT p.product_id, p.name, p.price, p.image_url, c.name AS category_name 
                  FROM products p 
                  JOIN categories c ON p.category_id = c.category_id 
                  WHERE p.name LIKE ? OR c.name LIKE ?';
        $search_term = '%' . $search_term . '%';
        $stmt = $db->prepare($query);
        $stmt->bind_param('ss', $search_term, $search_term);
        $stmt->execute();
        $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Search Products</h2>
        
        <!-- Search Form -->
        <form method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" name="query" class="form-control" 
                           placeholder="Search by product or category" 
                           value="<?php echo htmlspecialchars($search_term ?? ''); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
                <div class="col-md-2">
                    <a href="list.php" class="btn btn-secondary">Back to Catalog</a>
                </div>
            </div>
        </form>

        <!-- Displaying Error or Search Results -->
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php elseif ($search_term && empty($products)): ?>
            <p class="text-muted">No products found for "<?php echo htmlspecialchars($search_term); ?>"</p>
        <?php elseif ($search_term): ?>
            <div class="row">
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
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>