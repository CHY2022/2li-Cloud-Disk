<?php
// ============================================
// 举报列表API
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

$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$db = get_db();

$where = $status !== 'all' ? "WHERE r.status = '$status'" : "";
$stmt = $db->prepare("
    SELECT r.*, f.name as file_name, f.share_code 
    FROM reports r
    LEFT JOIN files f ON r.file_id = f.id
    $where
    ORDER BY r.created_at DESC
");
$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'items' => $items]);
?>