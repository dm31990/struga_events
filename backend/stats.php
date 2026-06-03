<?php
require_once 'db.php';
header('Content-Type: application/json');

$items        = $pdo->query("SELECT COUNT(*) FROM rental_items WHERE status = 'available'")->fetchColumn();
$reservations = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status IN ('approved','active','completed')")->fetchColumn();
$customers    = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();

echo json_encode([
    'items'        => (int)$items,
    'reservations' => (int)$reservations,
    'customers'    => (int)$customers
]);
