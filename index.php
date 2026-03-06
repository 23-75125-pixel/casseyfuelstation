<?php
// index.php - Root redirect
session_start();
if (!empty($_SESSION['profile'])) {
    $role = $_SESSION['profile']['role'] ?? 'cashier';
    header('Location: ' . ($role === 'cashier' ? '/pos.php' : '/dashboard.php'));
} else {
    header('Location: /login.php');
}
exit;
