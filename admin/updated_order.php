<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

// Logging function
function logError($message) {
    $log_dir = __DIR__ . '/../logs';
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0777, true);
    }
    file_put_contents("$log_dir/admin_errors.log", date('Y-m-d H:i:s') . ': ' . $message . PHP_EOL, FILE_APPEND);
}

// Manejar actualización del estado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    if ($order_id && in_array($status, ['completed', 'shipped', 'cancelled'])) {
        $stmt = $db->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
        $stmt->bind_param('si', $status, $order_id);
        if (!$stmt->execute()) {
            logError('Update Order: Failed to update status for order_id ' . $order_id . ' - ' . $db->error);
            header('Location: index.php?error=Error al actualizar el estado');
            exit;
        }
        logError('Update Order: Status updated for order_id ' . $order_id . ' to ' . $status);
        header('Location: index.php?success=Estado actualizado correctamente');
        exit;
    }
    logError('Update Order: Invalid order_id or status - Order ID: ' . $order_id . ', Status: ' . $status);
    header('Location: index.php?error=ID o estado no válido');
    exit;
}
?>