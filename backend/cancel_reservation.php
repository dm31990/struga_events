<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$reservation_id = (int)($_POST['reservation_id'] ?? 0);
if (!$reservation_id) {
    http_response_code(400);
    echo json_encode(['error' => 'reservation_id required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$reservation_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    http_response_code(404);
    echo json_encode(['error' => 'Reservation not found']);
    exit;
}

$is_owner = (int)$reservation['customer_id'] === (int)$_SESSION['user_id'];
$is_staff = in_array($_SESSION['role'], ['admin', 'employee']);

if (!$is_owner && !$is_staff) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($reservation['status'] !== 'pending' && !($is_staff && in_array($reservation['status'], ['approved', 'active']))) {
    http_response_code(400);
    echo json_encode(['error' => 'Only pending reservations can be cancelled by customers']);
    exit;
}

$pdo->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?")->execute([$reservation_id]);

$msg = "Reservation \"{$reservation['event_name']}\" has been cancelled.";
$pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'reservation')")
    ->execute([$reservation['customer_id'], $msg]);

echo json_encode(['success' => true, 'status' => 'cancelled']);
