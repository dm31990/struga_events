<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$reservation_id = (int)($_GET['reservation_id'] ?? 0);
if (!$reservation_id) {
    http_response_code(400);
    echo json_encode(['error' => 'reservation_id required']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT i.*, r.event_name, r.event_date, r.end_date, r.delivery_address,
           CONCAT(u.first_name, ' ', u.last_name) AS customer_name, u.email, u.phone
    FROM invoices i
    JOIN reservations r ON i.reservation_id = r.id
    JOIN users u ON r.customer_id = u.id
    WHERE i.reservation_id = ?
");
$stmt->execute([$reservation_id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    http_response_code(404);
    echo json_encode(['error' => 'Invoice not found']);
    exit;
}

if ($_SESSION['role'] === 'customer') {
    $chk = $pdo->prepare("SELECT id FROM reservations WHERE id = ? AND customer_id = ?");
    $chk->execute([$reservation_id, $_SESSION['user_id']]);
    if (!$chk->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}

$istmt = $pdo->prepare("SELECT description, quantity, unit_price, subtotal FROM invoice_line_items WHERE invoice_id = ?");
$istmt->execute([$invoice['id']]);
$invoice['line_items'] = $istmt->fetchAll();

echo json_encode($invoice);
