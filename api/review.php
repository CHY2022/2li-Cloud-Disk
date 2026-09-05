<?php
// ============================================
// 审核API - 通过/拒绝网站提交
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
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'ID无效']);
    exit;
}

$db = get_db();

// 获取网站信息
$stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    echo json_encode(['success' => false, 'error' => '网站不存在']);
    exit;
}

// ========================================
// 审核通过
// ========================================
if ($action === 'approve') {
    try {
        $db->beginTransaction();
        
        // 生成备案编号
        $province = $file['province_code'] ?? '鲁';
        $record_number = generate_record_number($province);
        
        // 提取序号
        preg_match('/\d{4}$/', $record_number, $matches);
        $serial = intval($matches[0] ?? 0);
        $year = date('Y');
        
        // 更新状态
        $stmt = $db->prepare("
            UPDATE files SET 
                status = 'approved',
                record_number = ?,
                record_year = ?,
                record_province = ?,
                record_serial = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        $stmt->execute([$record_number, $year, $province, $serial, $id]);
        
        // 记录审核日志
        $stmt = $db->prepare("
            INSERT INTO review_logs (file_id, action, admin_name, created_at)
            VALUES (?, 'approve', ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$id, $_SESSION['admin_username'] ?? '管理员']);
        
        $db->commit();
        
        // 发送邮件通知
        if (!empty($file['submitted_email'])) {
            $subject = '【' . get_setting('site_name', '导航站') . '】网站审核通过通知';
            $body = build_approve_email($file, $record_number);
            send_email_with_log($file['submitted_email'], $subject, $body);
        }
        
        echo json_encode([
            'success' => true,
            'record_number' => $record_number,
            'message' => '审核通过，编号已生成'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ========================================
// 审核拒绝
// ========================================
if ($action === 'reject') {
    $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '未提供具体原因';
    
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("UPDATE files SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
        
        $stmt = $db->prepare("
            INSERT INTO review_logs (file_id, action, reason, admin_name, created_at)
            VALUES (?, 'reject', ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$id, $reason, $_SESSION['admin_username'] ?? '管理员']);
        
        $db->commit();
        
        // 发送邮件通知
        if (!empty($file['submitted_email'])) {
            $subject = '【' . get_setting('site_name', '导航站') . '】网站审核未通过通知';
            $body = build_reject_email($file, $reason);
            send_email_with_log($file['submitted_email'], $subject, $body);
        }
        
        echo json_encode(['success' => true, 'message' => '已拒绝']);
        
    } catch (Exception $e) {
        $db->rollBack();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => '无效的操作']);
?>