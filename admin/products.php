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

// Generating CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetching categories
$stmt = $db->prepare('SELECT category_id, name FROM categories');
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetching products
$stmt = $db->prepare('SELECT p.product_id, p.name, p.price, p.stock, p.image_url, c.name AS category_name 
                      FROM products p JOIN categories c ON p.category_id = c.category_id');
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handling edit request
$edit_product = null;
if (isset($_GET['edit'])) {
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
    if ($edit_id) {
        $stmt = $db->prepare('SELECT product_id, name, price, stock, category_id, image_url 
                              FROM products WHERE product_id = ?');
        $stmt->bind_param('i', $edit_id);
        $stmt->execute();
        $edit_product = $stmt->get_result()->fetch_assoc();
    }
}

// Handling delete request
if (isset($_GET['delete']) && isset($_GET['csrf_token']) && $_GET['csrf_token'] === $_SESSION['csrf_token']) {
    $delete_id = filter_input(INPUT_GET, 'delete', FILTER_VALIDATE_INT);
    if ($delete_id) {
        $stmt = $db->prepare('DELETE FROM products WHERE product_id = ?');
        $stmt->bind_param('i', $delete_id);
        if ($stmt->execute()) {
            header('Location: products.php');
            exit;
        } else {
            $error = 'Failed to delete product.';
        }
    }
}

// Handling product add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $image_url = filter_input(INPUT_POST, 'image_url', FILTER_SANITIZE_URL);

    if ($name && $price > 0 && $stock >= 0 && $category_id) {
        if ($product_id) {
            // Updating existing product
            $stmt = $db->prepare('UPDATE products SET name = ?, price = ?, stock = ?, category_id = ?, image_url = ? WHERE product_id = ?');
            $stmt->bind_param('sdiisi', $name, $price, $stock, $category_id, $image_url, $product_id);
        } else {
            // Adding new product
            $stmt = $db->prepare('INSERT INTO products (name, price, stock, category_id, image_url) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('sdiis', $name, $price, $stock, $category_id, $image_url);
        }
        if ($stmt->execute()) {
            header('Location: products.php');
            exit;
        } else {
            $error = 'Failed to save product.';
        }
    } else {
        $error = 'Please fill in all required fields with valid values.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Manage Products</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Add/Edit Product Form -->
        <form method="POST" class="mb-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="product_id" value="<?php echo $edit_product ? $edit_product['product_id'] : ''; ?>">
            <div class="mb-3">
                <label for="name" class="form-label">Product Name</label>
                <input type="text" class="form-control" id="name" name="name" 
                       value="<?php echo $edit_product ? htmlspecialchars($edit_product['name']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">Price</label>
                <input type="number" class="form-control" id="price" name="price" step="0.01" 
                       value="<?php echo $edit_product ? $edit_product['price'] : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="stock" class="form-label">Stock</label>
                <input type="number" class="form-control" id="stock" name="stock" min="0" 
                       value="<?php echo $edit_product ? $edit_product['stock'] : '0'; ?>" required>
            </div>
            <div class="mb-3">
                <label for="category_id" class="form-label">Category</label>
                <select class="form-select" id="category_id" name="category_id" required>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['category_id']; ?>" 
                                <?php echo $edit_product && $edit_product['category_id'] == $category['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="image_url" class="form-label">Image URL</label>
                <input type="url" class="form-control" id="image_url" name="image_url" 
                       value="<?php echo $edit_product ? htmlspecialchars($edit_product['image_url']) : ''; ?>">
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $edit_product ? 'Update' : 'Add'; ?> Product</button>
            <?php if ($edit_product): ?>
                <a href="products.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>

        <!-- Product List -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Category</th>
                    <th>Image</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo $product['product_id']; ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo $product['stock']; ?></td>
                        <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                        <td><?php echo $product['image_url'] ? '<img src="' . htmlspecialchars($product['image_url']) . '" width="50">' : 'N/A'; ?></td>
                        <td>
                            <a href="products.php?edit=<?php echo $product['product_id']; ?>" class="btn

 btn-sm btn-primary">Edit</a>
                            <a href="products.php?delete=<?php echo $product['product_id']; ?>&csrf_token=<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>" 
                               class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>