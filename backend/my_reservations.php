<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'customer';

if ($role === 'admin' || $role === 'employee') {
    $sql = "SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) AS customer_name, u.email AS customer_email
            FROM reservations r
            JOIN users u ON r.customer_id = u.id
            ORDER BY r.created_at DESC LIMIT 200";
    $stmt = $pdo->query($sql);
} else {
    $stmt = $pdo->prepare("SELECT * FROM reservations WHERE customer_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
}

$reservations = $stmt->fetchAll();

foreach ($reservations as &$res) {
    $istmt = $pdo->prepare("
        SELECT ri.quantity, ri.unit_price, ri.subtotal, i.name AS item_name
        FROM reservation_items ri
        JOIN rental_items i ON ri.rental_item_id = i.id
        WHERE ri.reservation_id = ?
    ");
    $istmt->execute([$res['id']]);
    $res['items'] = $istmt->fetchAll();
}
unset($res);

echo json_encode($reservations);
