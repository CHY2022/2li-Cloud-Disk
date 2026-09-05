<?php
// ============================================
// 后台列表API - 获取网站列表
// ============================================

require_once '../config.php';
require_once '../functions.php';
session_start();

header('Content-Type: application/json');

// 检查登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '未授权']);
    exit;
}

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$category = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

try {
    $db = get_db();

    // 获取单个网站
    if (isset($_GET['id'])) {
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([intval($_GET['id'])]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'item' => $item]);
        exit;
    }

    $offset = ($page - 1) * $per_page;

    $where = [];
    $params = [];

    if ($category !== 'all' && !empty($category)) {
        $where[] = "category = ?";
        $params[] = $category;
    }
    if (!empty($keyword)) {
        $where[] = "(name LIKE ? OR description LIKE ? OR url LIKE ?)";
        $params[] = '%' . $keyword . '%';
        $params[] = '%' . $keyword . '%';
        $params[] = '%' . $keyword . '%';
    }

    $where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

    $count_stmt = $db->prepare("SELECT COUNT(*) FROM files $where_sql");
    $count_stmt->execute($params);
    $total = $count_stmt->fetchColumn();

    $stmt = $db->prepare("SELECT * FROM files $where_sql ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $params[] = $per_page;
    $params[] = $offset;
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page),
        'items' => $items
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => '数据库错误: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => '系统错误: ' . $e->getMessage()]);
}
?>