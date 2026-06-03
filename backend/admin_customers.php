<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$stmt = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.created_at,
           COUNT(r.id) AS reservation_count,
           COALESCE(SUM(CASE WHEN r.status IN ('approved','active','completed') THEN r.total_price ELSE 0 END), 0) AS total_spent
    FROM users u
    LEFT JOIN reservations r ON r.customer_id = u.id
    WHERE u.role = 'customer'
    GROUP BY u.id
    ORDER BY u.last_name, u.first_name
");
echo json_encode($stmt->fetchAll());
