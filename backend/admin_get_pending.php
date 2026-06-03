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
    SELECT r.*, CONCAT(u.first_name, ' ', u.last_name) AS customer_name, u.email AS customer_email, u.phone
    FROM reservations r
    JOIN users u ON r.customer_id = u.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at ASC
");
$list = $stmt->fetchAll();

foreach ($list as &$res) {
    $istmt = $pdo->prepare("
        SELECT ri.*, i.name AS item_name
        FROM reservation_items ri
        JOIN rental_items i ON ri.rental_item_id = i.id
        WHERE ri.reservation_id = ?
    ");
    $istmt->execute([$res['id']]);
    $res['items'] = $istmt->fetchAll();
}
unset($res);

echo json_encode($list);
