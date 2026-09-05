<?php
// ============================================
// 编辑网站API
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

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID无效']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$url = trim($_POST['url'] ?? '');
$category = trim($_POST['category'] ?? '');
$tags = trim($_POST['tags'] ?? '');
$description = trim($_POST['description'] ?? '');
$icon_url = trim($_POST['icon_url'] ?? '');

if (empty($name)) {
    echo json_encode(['success' => false, 'error' => '请输入网站名称']);
    exit;
}
if (empty($url)) {
    echo json_encode(['success' => false, 'error' => '请输入网站地址']);
    exit;
}
if (empty($category)) {
    echo json_encode(['success' => false, 'error' => '请选择分类']);
    exit;
}

try {
    $db = get_db();
    
    $stmt = $db->prepare("
        UPDATE files SET 
            name = ?,
            url = ?,
            category = ?,
            tags = ?,
            description = ?,
            icon_url = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
    ");
    $stmt->execute([$name, $url, $category, $tags, $description, $icon_url, $id]);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => '数据库错误: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => '系统错误: ' . $e->getMessage()]);
}
?>