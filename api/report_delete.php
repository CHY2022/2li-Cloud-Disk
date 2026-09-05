<?php
// ============================================
// 删除举报API
// ============================================

require_once '../config.php';
require_once '../functions.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '未授权']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['id']) ? intval($data['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID无效']);
    exit;
}

$db = get_db();
$stmt = $db->prepare("DELETE FROM reports WHERE id = ?");
$stmt->execute([$id]);

echo json_encode(['success' => true]);
?>