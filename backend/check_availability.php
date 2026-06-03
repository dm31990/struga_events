<?php
require_once 'db.php';
header('Content-Type: application/json');

$item_id    = (int)($_GET['item_id'] ?? 0);
$start_date = trim($_GET['start_date'] ?? '');
$end_date   = trim($_GET['end_date'] ?? '');
$quantity   = max(1, (int)($_GET['quantity'] ?? 1));

if (!$item_id || !$start_date || !$end_date) {
    http_response_code(400);
    echo json_encode(['error' => 'item_id, start_date, and end_date are required']);
    exit;
}

$stmt = $pdo->prepare("SELECT quantity_total, quantity_available, name, daily_price, deposit_amount FROM rental_items WHERE id = ? AND status = 'available'");
$stmt->execute([$item_id]);
$item = $stmt->fetch();

if (!$item) {
    http_response_code(404);
    echo json_encode(['error' => 'Item not found']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(ri.quantity), 0) AS reserved_qty
    FROM reservation_items ri
    JOIN reservations r ON ri.reservation_id = r.id
    WHERE ri.rental_item_id = ?
      AND r.status IN ('pending', 'approved', 'active')
      AND r.event_date <= ?
      AND r.end_date >= ?
");
$stmt->execute([$item_id, $end_date, $start_date]);
$reserved = (int)$stmt->fetchColumn();

$available = max(0, (int)$item['quantity_total'] - $reserved);
$can_book = $available >= $quantity;

echo json_encode([
    'item_id'            => $item_id,
    'item_name'          => $item['name'],
    'daily_price'        => (float)$item['daily_price'],
    'deposit_amount'     => (float)$item['deposit_amount'],
    'quantity_requested' => $quantity,
    'quantity_available' => $available,
    'available'          => $can_book
]);
