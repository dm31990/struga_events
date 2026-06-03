<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email, phone, role, status, created_at FROM users ORDER BY role, last_name");
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    $role    = $_POST['role'] ?? '';

    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['error' => 'user_id required']);
        exit;
    }

    if (in_array($status, ['active', 'inactive'])) {
        $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND id != ?")
            ->execute([$status, $user_id, $_SESSION['user_id']]);
    }
    if (in_array($role, ['customer', 'employee', 'admin'])) {
        $pdo->prepare("UPDATE users SET role = ? WHERE id = ? AND id != ?")
            ->execute([$role, $user_id, $_SESSION['user_id']]);
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
