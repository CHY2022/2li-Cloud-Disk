<?php
// ============================================
// 待审核列表API
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

$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

try {
    $db = get_db();
    
    $where = "status = 'pending'";
    $params = [];
    
    if (!empty($keyword)) {
        $where .= " AND (name LIKE ? OR url LIKE ? OR description LIKE ? OR submitted_email LIKE ? OR submitted_by LIKE ?)";
        $params[] = '%' . $keyword . '%';
        $params[] = '%' . $keyword . '%';
        $params[] = '%' . $keyword . '%';
        $params[] = '%' . $keyword . '%';
        $params[] = '%' . $keyword . '%';
    }
    
    $stmt = $db->prepare("SELECT * FROM files WHERE $where ORDER BY submitted_at DESC, created_at DESC");
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'items' => $items]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => '数据库错误: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => '系统错误: ' . $e->getMessage()]);
}
?>