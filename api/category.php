<?php
// ============================================
// 分类管理API
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

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
$db = get_db();

// ========================================
// 获取分类列表
// ========================================
if ($action === 'list') {
    $type = isset($_GET['type']) ? $_GET['type'] : '';
    if (empty($type)) {
        echo json_encode(['success' => false, 'error' => '请指定分类类型']);
        exit;
    }
    $stmt = $db->prepare("SELECT * FROM categories WHERE type = ? ORDER BY sort_order ASC, name ASC");
    $stmt->execute([$type]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'items' => $items]);
    exit;
}

// ========================================
// 添加分类
// ========================================
if ($action === 'add') {
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $icon = isset($_POST['icon']) ? trim($_POST['icon']) : '';
    
    if (empty($type) || empty($name)) {
        echo json_encode(['success' => false, 'error' => '请填写完整信息']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT id FROM categories WHERE type = ? AND name = ?");
    $stmt->execute([$type, $name]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => '该分类已存在']);
        exit;
    }
    
    $stmt = $db->prepare("
        INSERT INTO categories (type, name, icon, sort_order) 
        VALUES (?, ?, ?, (SELECT COALESCE(MAX(sort_order), 0) + 1 FROM categories WHERE type = ?))
    ");
    $result = $stmt->execute([$type, $name, $icon, $type]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => '添加成功']);
    } else {
        echo json_encode(['success' => false, 'error' => '添加失败，请重试']);
    }
    exit;
}

// ========================================
// 删除分类
// ========================================
if ($action === 'delete') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if (!$id) {
        echo json_encode(['success' => false, 'error' => '无效的ID']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT type, name FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    $cat = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$cat) {
        echo json_encode(['success' => false, 'error' => '分类不存在']);
        exit;
    }
    
    // 检查是否有关联资源
    if ($cat['type'] === 'nav_type') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM files WHERE category = ?");
        $stmt->execute([$cat['name']]);
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            echo json_encode(['success' => false, 'error' => "该分类下有 {$count} 个网站，无法删除"]);
            exit;
        }
    }
    
    $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode(['success' => true, 'message' => '删除成功']);
    exit;
}

echo json_encode(['success' => false, 'error' => '无效的操作']);
?>