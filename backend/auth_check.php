<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, phone FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        echo json_encode(array_merge($user, ['logged_in' => true]));
    } else {
        session_destroy();
        echo json_encode(['logged_in' => false]);
    }
} else {
    echo json_encode(['logged_in' => false]);
}
