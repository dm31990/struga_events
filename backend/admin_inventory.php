<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("
        SELECT ri.*, c.name AS category_name
        FROM rental_items ri
        JOIN categories c ON ri.category_id = c.id
        ORDER BY c.name, ri.name
    ");
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id           = (int)($_POST['id'] ?? 0);
    $available    = isset($_POST['quantity_available']) ? (int)$_POST['quantity_available'] : -1;
    $daily_price  = $_POST['daily_price'] ?? '';
    $status       = $_POST['status'] ?? '';
    $name         = trim($_POST['name'] ?? '');
    $category_id  = (int)($_POST['category_id'] ?? 0);

    if ($_POST['action'] === 'create' && $name && $category_id) {
        $price = (float)($_POST['daily_price'] ?? 0);
        $qty   = max(1, (int)($_POST['quantity_total'] ?? 1));
        $dep   = (float)($_POST['deposit_amount'] ?? 0);
        $pdo->prepare("INSERT INTO rental_items (name, category_id, description, daily_price, deposit_amount, quantity_total, quantity_available, status)
                       VALUES (?, ?, ?, ?, ?, ?, ?, 'available')")
            ->execute([$name, $category_id, $_POST['description'] ?? '', $price, $dep, $qty, $qty]);
        echo json_encode(['success' => true]);
        exit;
    }

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Item id required']);
        exit;
    }

    if ($available >= 0) {
        $pdo->prepare("UPDATE rental_items SET quantity_available = ?, quantity_total = GREATEST(quantity_total, ?) WHERE id = ?")
            ->execute([$available, $available, $id]);
    }
    if ($daily_price !== '' && is_numeric($daily_price)) {
        $pdo->prepare("UPDATE rental_items SET daily_price = ? WHERE id = ?")->execute([(float)$daily_price, $id]);
    }
    if (in_array($status, ['available', 'unavailable'])) {
        $pdo->prepare("UPDATE rental_items SET status = ? WHERE id = ?")->execute([$status, $id]);
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
