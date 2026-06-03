<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to   = trim($_GET['to'] ?? date('Y-m-d'));

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS reservation_count,
           SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
           SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_count
    FROM reservations
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$stmt->execute([$from, $to]);
$summary = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_with_tax), 0) AS revenue
    FROM invoices
    WHERE issue_date BETWEEN ? AND ?
");
$stmt->execute([$from, $to]);
$revenue = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT ri.name, SUM(ri2.quantity) AS times_rented
    FROM reservation_items ri2
    JOIN rental_items ri ON ri2.rental_item_id = ri.id
    JOIN reservations r ON ri2.reservation_id = r.id
    WHERE r.status IN ('approved','active','completed')
      AND r.event_date BETWEEN ? AND ?
    GROUP BY ri.id
    ORDER BY times_rented DESC
    LIMIT 10
");
$stmt->execute([$from, $to]);

echo json_encode([
    'from' => $from,
    'to' => $to,
    'summary' => $summary,
    'revenue' => $revenue,
    'top_items' => $stmt->fetchAll()
]);
