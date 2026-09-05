<?php
// ============================================
// 备案编号管理API
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

$action = isset($_POST['action']) ? $_POST['action'] : '';

// ========================================
// 生成编号
// ========================================
if ($action === 'generate') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    
    if (!$id) {
        echo json_encode(['success' => false, 'error' => 'ID无效']);
        exit;
    }
    
    $db = get_db();
    
    $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$file) {
        echo json_encode(['success' => false, 'error' => '网站不存在']);
        exit;
    }
    
    if (!empty($file['record_number'])) {
        echo json_encode([
            'success' => true,
            'record_number' => $file['record_number'],
            'message' => '该网站已有编号'
        ]);
        exit;
    }
    
    $prefix = get_setting('record_prefix', '网AN');
    $year = date('Y');
    $province = '鲁';
    
    $serial = get_next_serial($province, $year);
    $record_number = $prefix . $province . '备' . $year . str_pad($serial, 4, '0', STR_PAD_LEFT) . '号';
    
    $stmt = $db->prepare("
        UPDATE files SET 
            record_number = ?,
            record_year = ?,
            record_province = ?,
            record_serial = ?
        WHERE id = ?
    ");
    $stmt->execute([$record_number, $year, $province, $serial, $id]);
    
    echo json_encode([
        'success' => true,
        'record_number' => $record_number,
        'province' => $province,
        'serial' => $serial,
        'year' => $year
    ]);
    exit;
}

// ========================================
// 批量生成编号
// ========================================
if ($action === 'batch_generate') {
    $db = get_db();
    
    $stmt = $db->query("SELECT * FROM files WHERE record_number IS NULL OR record_number = ''");
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $generated = 0;
    $prefix = get_setting('record_prefix', '网AN');
    $year = date('Y');
    $province = '鲁';
    
    foreach ($files as $file) {
        try {
            $serial = get_next_serial($province, $year);
            $record_number = $prefix . $province . '备' . $year . str_pad($serial, 4, '0', STR_PAD_LEFT) . '号';
            
            $stmt2 = $db->prepare("
                UPDATE files SET 
                    record_number = ?,
                    record_year = ?,
                    record_province = ?,
                    record_serial = ?
                WHERE id = ?
            ");
            $stmt2->execute([$record_number, $year, $province, $serial, $file['id']]);
            $generated++;
        } catch (Exception $e) {}
    }
    
    echo json_encode([
        'success' => true,
        'generated' => $generated,
        'total' => count($files)
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => '无效的操作']);
?>