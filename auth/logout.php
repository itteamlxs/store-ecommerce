<?php
session_start();

// Logging logout activity
require_once __DIR__ . '/../config/db.php';
if (isset($_SESSION['user_id'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $browser = $_SERVER['HTTP_USER_AGENT'];
    $country = 'Unknown'; // Placeholder: Use GeoIP service for real implementation
    $stmt = $db->prepare("INSERT INTO user_logs (user_id, ip_address, browser, country) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $_SESSION['user_id'], $ip, $browser, $country);
    $stmt->execute();
}

// Destroying session
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>