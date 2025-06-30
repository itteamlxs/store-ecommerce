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

// Fetching dashboard stats
$stmt = $db->query('SELECT COUNT(*) as total FROM products');
$total_products = $stmt->fetch_assoc()['total'];

$stmt = $db->query('SELECT COUNT(*) as total FROM users');
$total_users = $stmt->fetch_assoc()['total'];

$stmt = $db->query('SELECT COUNT(*) as total FROM orders');
$total_orders = $stmt->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Admin Dashboard</h2>
        <div class="row">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Products</h5>
                        <p class="card-text"><?php echo $total_products; ?></p>
                        <a href="products.php" class="btn btn-primary">Manage Products</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Users</h5>
                        <p class="card-text"><?php echo $total_users; ?></p>
                        <a href="users.php" class="btn btn-primary">Manage Users</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h5 class="card-title">Total Orders</h5>
                        <p class="card-text"><?php echo $total_orders; ?></p>
                        <a href="orders.php" class="btn btn-primary">Manage Orders</a>
                    </div>
                </div>
            </div>
        </div>
        <a href="../auth/logout.php" class="btn btn-secondary mt-4">Logout</a>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>