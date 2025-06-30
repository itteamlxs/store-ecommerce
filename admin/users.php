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

// Fetching users
$stmt = $db->prepare('SELECT user_id, email, first_name, last_name, country, is_admin FROM users');
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handling user role update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token');
    }
    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $is_admin = filter_input(INPUT_POST, 'is_admin', FILTER_VALIDATE_INT) ? 1 : 0;
    if ($user_id && $user_id !== $_SESSION['user_id']) { // Prevent self-role change
        $stmt = $db->prepare('UPDATE users SET is_admin = ? WHERE user_id = ?');
        $stmt->bind_param('ii', $is_admin, $user_id);
        if ($stmt->execute()) {
            header('Location: users.php');
            exit;
        } else {
            $error = 'Failed to update user role.';
        }
    } else {
        $error = 'Invalid user or cannot modify own role.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>Manage Users</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- User List -->
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Name</th>
                    <th>Country</th>
                    <th>Admin</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['country']); ?></td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                <input type="checkbox" name="is_admin" value="1" 
                                       <?php echo $user['is_admin'] ? 'checked' : ''; ?> 
                                       onchange="this.form.submit()" <?php echo $user['user_id'] === $_SESSION['user_id'] ? 'disabled' : ''; ?>>
                            </form>
                        </td>
                        <td>
                            <a href="users.php?delete=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-danger" 
                               onclick="return confirm('Are you sure?');">Delete</a>
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