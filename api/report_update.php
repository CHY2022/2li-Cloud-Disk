<?php
// ============================================
// 更新举报状态API
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
$status = isset($data['status']) ? $data['status'] : '';

if (!$id || !in_array($status, ['pending', 'resolved', 'rejected'])) {
    echo json_encode(['success' => false, 'error' => '参数无效']);
    exit;
}

$db = get_db();
$stmt = $db->prepare("UPDATE reports SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
$stmt->execute([$status, $id]);

echo json_encode(['success' => true]);
?>