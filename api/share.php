<?php
// ============================================
// 分享管理API
// ============================================

require_once '../config.php';
require_once '../functions.php';
session_start();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

// 创建分享
if (isset($data['action']) && $data['action'] === 'create') {
    $file_id = intval($data['file_id']);
    $expire_days = isset($data['expire_days']) ? intval($data['expire_days']) : 30;
    
    try {
        $db = get_db();
        
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ? AND status = 'approved'");
        $stmt->execute([$file_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            echo json_encode(['success' => false, 'error' => '网站不存在或未审核']);
            exit;
        }
        
        $share_code = generate_share_code();
        $share_expire = $expire_days > 0 ? date('Y-m-d H:i:s', strtotime("+{$expire_days} days")) : null;
        
        $stmt = $db->prepare("
            UPDATE files SET 
                share_code = ?, 
                share_created_at = CURRENT_TIMESTAMP, 
                share_expire_at = ? 
            WHERE id = ?
        ");
        $stmt->execute([$share_code, $share_expire, $file_id]);
        
        echo json_encode([
            'success' => true,
            'share_code' => $share_code,
            'share_url' => get_share_url($share_code),
            'expire_at' => $share_expire
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => '无效的操作']);
?>