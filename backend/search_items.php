<?php
require_once 'db.php';
header('Content-Type: application/json');

$name       = trim($_GET['name'] ?? '');
$category   = trim($_GET['category_id'] ?? '');
$max_price  = trim($_GET['max_price'] ?? '');

$sql = "SELECT ri.*, c.name AS category_name
        FROM rental_items ri
        JOIN categories c ON ri.category_id = c.id
        WHERE ri.status = 'available'";
$params = [];

if ($name) {
    $sql .= " AND (ri.name LIKE ? OR ri.description LIKE ?)";
    $params[] = "%$name%";
    $params[] = "%$name%";
}
if ($category) {
    $sql .= " AND ri.category_id = ?";
    $params[] = $category;
}
if ($max_price !== '' && is_numeric($max_price)) {
    $sql .= " AND ri.daily_price <= ?";
    $params[] = $max_price;
}

$sql .= " ORDER BY ri.name ASC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll());
