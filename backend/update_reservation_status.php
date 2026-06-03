<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$reservation_id = (int)($_POST['reservation_id'] ?? 0);
$status         = $_POST['status'] ?? '';

if (!$reservation_id || !in_array($status, ['active', 'completed'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ? AND status IN ('approved','active')");
$stmt->execute([$reservation_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    http_response_code(404);
    echo json_encode(['error' => 'Reservation not found or invalid transition']);
    exit;
}

if ($status === 'active' && $reservation['status'] !== 'approved') {
    http_response_code(400);
    echo json_encode(['error' => 'Only approved reservations can be marked active']);
    exit;
}

$pdo->prepare("UPDATE reservations SET status = ? WHERE id = ?")->execute([$status, $reservation_id]);

$label = $status === 'active' ? 'is now active (equipment delivered)' : 'has been completed';
$pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'reservation')")
    ->execute([$reservation['customer_id'], "Your event \"{$reservation['event_name']}\" {$label}."]);

echo json_encode(['success' => true, 'status' => $status]);
