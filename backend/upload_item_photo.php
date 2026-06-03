<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$item_id = (int)($_POST['item_id'] ?? 0);
if (!$item_id || empty($_FILES['photo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'item_id and photo required']);
    exit;
}

$file = $_FILES['photo'];
$allowed = ['image/jpeg', 'image/png', 'image/jpg'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Only JPEG and PNG images are allowed']);
    exit;
}

if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'File must be under 5MB']);
    exit;
}

$ext = $mime === 'image/png' ? 'png' : 'jpg';
$filename = 'item_' . $item_id . '_' . time() . '.' . $ext;
$dir = dirname(__DIR__) . '/uploads/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$path = $dir . $filename;

if (!move_uploaded_file($file['tmp_name'], $path)) {
    http_response_code(500);
    echo json_encode(['error' => 'Upload failed']);
    exit;
}

$relative = $filename;
$pdo->prepare("UPDATE rental_items SET image_path = ? WHERE id = ?")->execute([$relative, $item_id]);

echo json_encode(['success' => true, 'image_path' => $relative]);
