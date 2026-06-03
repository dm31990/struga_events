<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$pending   = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")->fetchColumn();
$approved  = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status IN ('approved','active')")->fetchColumn();
$revenue   = $pdo->query("SELECT COALESCE(SUM(total_with_tax),0) FROM invoices")->fetchColumn();
$items     = $pdo->query("SELECT COUNT(*) FROM rental_items WHERE status = 'available'")->fetchColumn();
$customers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer' AND status = 'active'")->fetchColumn();
$low_stock = $pdo->query("SELECT COUNT(*) FROM rental_items WHERE quantity_available <= 2 AND status = 'available'")->fetchColumn();

echo json_encode([
    'pending'   => (int)$pending,
    'approved'  => (int)$approved,
    'revenue'   => (float)$revenue,
    'items'     => (int)$items,
    'customers' => (int)$customers,
    'low_stock' => (int)$low_stock
]);
