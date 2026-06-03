<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$action         = $_POST['action'] ?? '';
$reservation_id = (int)($_POST['reservation_id'] ?? 0);

if (!$reservation_id || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

$status = $action === 'approve' ? 'approved' : 'cancelled';

$stmt = $pdo->prepare("SELECT * FROM reservations WHERE id = ?");
$stmt->execute([$reservation_id]);
$reservation = $stmt->fetch();

if (!$reservation) {
    http_response_code(404);
    echo json_encode(['error' => 'Reservation not found']);
    exit;
}

$pdo->beginTransaction();

$stmt = $pdo->prepare("UPDATE reservations SET status = ?, reviewed_by = ? WHERE id = ?");
$stmt->execute([$status, $_SESSION['user_id'], $reservation_id]);

if ($action === 'approve') {
    $title = $reservation['event_name'];
    $start = $reservation['event_date'] . ' 09:00:00';
    $end   = $reservation['end_date'] . ' 23:00:00';
    $loc   = $reservation['delivery_address'];

    $pdo->prepare("INSERT INTO event_schedule (reservation_id, title, start_datetime, end_datetime, location)
                   VALUES (?, ?, ?, ?, ?)")
        ->execute([$reservation_id, $title, $start, $end, $loc]);

    $tax_rate = 18.00;
    $amount   = (float)$reservation['total_price'];
    $tax      = round($amount * ($tax_rate / 100), 2);
    $total    = $amount + $tax;
    $inv_num  = 'INV-' . date('Y') . '-' . str_pad($reservation_id, 5, '0', STR_PAD_LEFT);
    $issue    = date('Y-m-d');
    $due      = date('Y-m-d', strtotime('+14 days'));

    $deposit = 0;
    $dstmt = $pdo->prepare("
        SELECT SUM(ri.quantity * i.deposit_amount)
        FROM reservation_items ri
        JOIN rental_items i ON ri.rental_item_id = i.id
        WHERE ri.reservation_id = ?
    ");
    $dstmt->execute([$reservation_id]);
    $deposit = (float)$dstmt->fetchColumn();

    $pdf_path = "invoices/{$inv_num}.html";

    $stmt = $pdo->prepare("INSERT INTO invoices (reservation_id, invoice_number, total_amount, deposit_amount, tax_rate, tax_amount, total_with_tax, issue_date, due_date, payment_status, pdf_file_path)
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', ?)");
    $stmt->execute([$reservation_id, $inv_num, $amount, $deposit, $tax_rate, $tax, $total, $issue, $due, $pdf_path]);
    $invoice_id = (int)$pdo->lastInsertId();

    $items = $pdo->prepare("
        SELECT i.name, ri.quantity, ri.unit_price, ri.subtotal
        FROM reservation_items ri
        JOIN rental_items i ON ri.rental_item_id = i.id
        WHERE ri.reservation_id = ?
    ");
    $items->execute([$reservation_id]);
    $istmt = $pdo->prepare("INSERT INTO invoice_line_items (invoice_id, description, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
    foreach ($items->fetchAll() as $line) {
        $istmt->execute([$invoice_id, $line['name'], $line['quantity'], $line['unit_price'], $line['subtotal']]);
    }
}

$msg = $action === 'approve'
    ? "Your reservation \"{$reservation['event_name']}\" has been approved. Invoice {$inv_num} is ready to download."
    : "Your reservation \"{$reservation['event_name']}\" was not approved. Please contact us for alternatives.";

$pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'reservation')")
    ->execute([$reservation['customer_id'], $msg]);

$pdo->commit();
echo json_encode(['success' => true, 'status' => $status, 'invoice_number' => $action === 'approve' ? $inv_num : null]);
