<?php
// ============================================
// 举报API
// ============================================

require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

$db = get_db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file_id = isset($_POST['file_id']) ? intval($_POST['file_id']) : 0;
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
    $detail = isset($_POST['detail']) ? trim($_POST['detail']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    
    if (!$file_id) {
        echo json_encode(['success' => false, 'error' => '网站ID无效']);
        exit;
    }
    if (empty($reason)) {
        echo json_encode(['success' => false, 'error' => '请选择举报原因']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT id, name FROM files WHERE id = ?");
    $stmt->execute([$file_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => '网站不存在']);
        exit;
    }
    
    $stmt = $db->prepare("
        INSERT INTO reports (file_id, reason, detail, reporter_email, reporter_name, status, created_at)
        VALUES (?, ?, ?, ?, ?, 'pending', CURRENT_TIMESTAMP)
    ");
    
    try {
        $stmt->execute([$file_id, $reason, $detail, $email, $name]);
        echo json_encode(['success' => true, 'message' => '举报已提交，管理员会尽快处理']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => '提交失败：' . $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => '无效的请求']);
?>