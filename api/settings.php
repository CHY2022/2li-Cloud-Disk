<?php
// ============================================
// 系统设置API
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

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        echo json_encode(['success' => false, 'error' => '无效的数据']);
        exit;
    }
    
    $allowed_keys = [
        'site_name', 'site_url', 'site_logo', 'icp_number', 'contact_email', 'copyright',
        'record_prefix', 'record_enabled', 'jump_ad_enabled', 'jump_ad_image', 
        'jump_ad_text', 'jump_ad_timeout', 'baidu_analytics', 'cnzz_analytics',
        'smtp_host', 'smtp_port', 'smtp_secure', 'smtp_user', 'smtp_pass',
        'smtp_from', 'smtp_from_name'
    ];
    
    $db = get_db();
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed_keys)) {
            update_setting($key, $value);
        }
    }
    
    echo json_encode(['success' => true, 'message' => '设置已更新']);
    exit;
}

if ($action === 'upload_logo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['logo'])) {
        echo json_encode(['success' => false, 'error' => '请选择图片']);
        exit;
    }
    
    if (!is_dir(UPLOAD_DIR . 'images/')) {
        mkdir(UPLOAD_DIR . 'images/', 0755, true);
    }
    
    $result = upload_file($_FILES['logo'], 'images');
    if ($result['success']) {
        $logo_path = '/uploads/images/' . $result['path'];
        update_setting('site_logo', $logo_path);
        echo json_encode(['success' => true, 'path' => $logo_path]);
    } else {
        echo json_encode(['success' => false, 'error' => $result['error']]);
    }
    exit;
}

if ($action === 'test_email' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => '请输入有效的邮箱地址']);
        exit;
    }
    
    $site_name = get_setting('site_name', '导航站');
    $subject = '【' . $site_name . '】SMTP测试邮件';
    $body = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #4CAF50; border-radius: 8px;'>
        <h2 style='color: #4CAF50;'>✅ SMTP配置测试成功</h2>
        <p>此邮件用于验证SMTP邮件发送配置是否正确。</p>
        <div style='background: #f5f5f5; padding: 15px; border-radius: 4px; margin: 10px 0;'>
            <p><strong>📅 发送时间：</strong>" . date('Y-m-d H:i:s') . "</p>
            <p><strong>📧 发件人：</strong>" . htmlspecialchars(get_setting('smtp_from_name', '导航站')) . "</p>
            <p><strong>📨 收件人：</strong>" . htmlspecialchars($email) . "</p>
        </div>
        <p style='color: #999; font-size: 12px;'>如果你收到这封邮件，说明SMTP配置正确！</p>
    </div>
    ";
    
    $result = send_email($email, $subject, $body);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => '测试邮件已发送，请检查收件箱']);
    } else {
        echo json_encode(['success' => false, 'error' => '邮件发送失败，请检查SMTP配置']);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => '无效的操作']);
?>