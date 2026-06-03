<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'You must be signed in to make a reservation']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$event_name        = trim($_POST['event_name'] ?? '');
$delivery_address  = trim($_POST['delivery_address'] ?? $_POST['event_location'] ?? '');
$event_date        = trim($_POST['event_date'] ?? '');
$end_date          = trim($_POST['end_date'] ?? '');
$notes             = trim($_POST['notes'] ?? '');
$items_json        = $_POST['items'] ?? '[]';

if (!$event_name || !$event_date || !$end_date) {
    http_response_code(400);
    echo json_encode(['error' => 'Event name and dates are required']);
    exit;
}

if ($end_date < $event_date) {
    http_response_code(400);
    echo json_encode(['error' => 'End date cannot be before start date']);
    exit;
}

$items = json_decode($items_json, true);
if (!is_array($items) || count($items) === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Select at least one rental item']);
    exit;
}

$days = max(1, (int)((strtotime($end_date) - strtotime($event_date)) / 86400) + 1);
$customer_id = $_SESSION['user_id'];

try {
    $pdo->beginTransaction();

    foreach ($items as $line) {
        $item_id = (int)($line['item_id'] ?? 0);
        $qty     = max(1, (int)($line['quantity'] ?? 1));

        $stmt = $pdo->prepare("SELECT quantity_total FROM rental_items WHERE id = ? AND status = 'available'");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        if (!$item) {
            throw new Exception('Invalid or unavailable rental item');
        }

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(ri.quantity), 0)
            FROM reservation_items ri
            JOIN reservations r ON ri.reservation_id = r.id
            WHERE ri.rental_item_id = ?
              AND r.status IN ('pending', 'approved', 'active')
              AND r.event_date <= ? AND r.end_date >= ?
        ");
        $stmt->execute([$item_id, $end_date, $event_date]);
        $reserved = (int)$stmt->fetchColumn();
        $free = (int)$item['quantity_total'] - $reserved;
        if ($free < $qty) {
            throw new Exception('Not enough stock for one or more items on selected dates');
        }
    }

    $stmt = $pdo->prepare("INSERT INTO reservations (customer_id, event_name, delivery_address, event_date, end_date, notes, status, total_price)
                           VALUES (?, ?, ?, ?, ?, ?, 'pending', 0)");
    $stmt->execute([$customer_id, $event_name, $delivery_address, $event_date, $end_date, $notes]);
    $reservation_id = (int)$pdo->lastInsertId();

    $total = 0;
    $deposit_total = 0;
    foreach ($items as $line) {
        $item_id = (int)$line['item_id'];
        $qty     = max(1, (int)$line['quantity']);

        $stmt = $pdo->prepare("SELECT daily_price, deposit_amount, name FROM rental_items WHERE id = ?");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch();
        if (!$item) {
            throw new Exception('Item not found');
        }

        $unit = (float)$item['daily_price'];
        $subtotal = $unit * $qty * $days;
        $total += $subtotal;
        $deposit_total += (float)$item['deposit_amount'] * $qty;

        $stmt = $pdo->prepare("INSERT INTO reservation_items (reservation_id, rental_item_id, quantity, unit_price, subtotal)
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$reservation_id, $item_id, $qty, $unit, $subtotal]);
    }

    $stmt = $pdo->prepare("UPDATE reservations SET total_price = ? WHERE id = ?");
    $stmt->execute([$total, $reservation_id]);

    $msg = "New reservation request \"{$event_name}\" submitted for review.";
    $admins = $pdo->query("SELECT id FROM users WHERE role IN ('admin','employee') AND status = 'active'")->fetchAll();
    $nstmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'reservation')");
    foreach ($admins as $a) {
        $nstmt->execute([$a['id'], $msg]);
    }

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'reservation_id' => $reservation_id,
        'total_price' => $total,
        'deposit_estimate' => $deposit_total
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
