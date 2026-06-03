<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$from = trim($_GET['from'] ?? date('Y-m-01'));
$to   = trim($_GET['to'] ?? date('Y-m-t'));

$sql = "SELECT es.*, r.status AS reservation_status, r.customer_id
        FROM event_schedule es
        JOIN reservations r ON es.reservation_id = r.id
        WHERE DATE(es.start_datetime) >= ? AND DATE(es.end_datetime) <= ?";

$params = [$from, $to];

if ($_SESSION['role'] === 'customer') {
    $sql .= " AND r.customer_id = ?";
    $params[] = $_SESSION['user_id'];
}

$sql .= " ORDER BY es.start_datetime ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll());
