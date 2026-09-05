<?php
// ============================================
// 而立导航 - 完整后台管理（标签页版）
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'functions.php';

session_start();

// 检查登录状态
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /login.php');
    exit;
}

// 退出登录
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

$db = get_db();
$settings = get_all_settings();

// 获取统计数据
$total_sites = $db->query("SELECT COUNT(*) FROM files")->fetchColumn();
$total_clicks = $db->query("SELECT SUM(clicks) FROM files")->fetchColumn() ?: 0;
$pending_count = $db->query("SELECT COUNT(*) FROM files WHERE status = 'pending'")->fetchColumn();
$total_reports = $db->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();

// 获取分类和标签
$nav_types = get_nav_types();
$nav_tags = get_nav_tags();
$provinces = PROVINCES;

// ============================================
// 处理删除网站 - 直接删除，不重新编号
// ============================================
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $db->prepare("DELETE FROM files WHERE id = ?");
        $stmt->execute([$id]);
        echo '<script>alert("✅ 删除成功");window.location.href="/admin.php?tab=sites";</script>';
        exit;
    } catch (Exception $e) {
        $delete_error = '删除失败：' . $e->getMessage();
    }
}

// ============================================
// 处理添加网站
// ============================================
$add_result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_add'])) {
    $name = trim($_POST['name'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon_url = trim($_POST['icon_url'] ?? '');
    $province = trim($_POST['province'] ?? '鲁');
    $icp_number = trim($_POST['icp_number'] ?? '');
    $public_security_number = trim($_POST['public_security_number'] ?? '');
    
    if (empty($name)) {
        $add_result = '<div class="result-error">❌ 请填写网站名称</div>';
    } elseif (empty($url)) {
        $add_result = '<div class="result-error">❌ 请填写网站地址</div>';
    } elseif (empty($category)) {
        $add_result = '<div class="result-error">❌ 请选择分类</div>';
    } else {
        try {
            $record_number = generate_record_number($province);
            $year = date('Y');
            $serial = intval(substr($record_number, -5, 4));
            
            $stmt = $db->prepare("
                INSERT INTO files (
                    name, url, category, tags, description, icon_url,
                    icp_number, public_security_number, province_code,
                    record_number, record_province, record_year, record_serial,
                    status, submitted_by, submitted_at, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', '管理员', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
            ");
            
            $stmt->execute([
                $name, $url, $category, $tags, $description, $icon_url,
                $icp_number, $public_security_number, $province,
                $record_number, $province, $year, $serial
            ]);
            
            $add_result = '<div class="result-success">✅ 网站添加成功！备案编号：' . htmlspecialchars($record_number) . '</div>';
        } catch (Exception $e) {
            $add_result = '<div class="result-error">❌ 添加失败：' . $e->getMessage() . '</div>';
        }
    }
}

// ============================================
// 处理编辑网站
// ============================================
$edit_result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_site'])) {
    $id = intval($_POST['edit_id'] ?? 0);
    $name = trim($_POST['edit_name'] ?? '');
    $url = trim($_POST['edit_url'] ?? '');
    $category = trim($_POST['edit_category'] ?? '');
    $tags = trim($_POST['edit_tags'] ?? '');
    $description = trim($_POST['edit_description'] ?? '');
    $icon_url = trim($_POST['edit_icon_url'] ?? '');
    
    if (!$id) {
        $edit_result = '<div class="result-error">❌ 无效的ID</div>';
    } elseif (empty($name) || empty($url) || empty($category)) {
        $edit_result = '<div class="result-error">❌ 请填写所有必填字段</div>';
    } else {
        try {
            $stmt = $db->prepare("
                UPDATE files SET 
                    name = ?, url = ?, category = ?, tags = ?, 
                    description = ?, icon_url = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([$name, $url, $category, $tags, $description, $icon_url, $id]);
            $edit_result = '<div class="result-success">✅ 网站更新成功！</div>';
        } catch (Exception $e) {
            $edit_result = '<div class="result-error">❌ 更新失败：' . $e->getMessage() . '</div>';
        }
    }
}

// ============================================
// 处理审核通过 - 无事务版本
// ============================================
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $id = intval($_GET['approve']);
    try {
        $db = get_db();
        
        // 获取网站信息
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            throw new Exception('网站不存在');
        }
        if ($file['status'] == 'approved') {
            throw new Exception('该网站已经审核通过');
        }
        
        // 生成备案编号
        $province = $file['province_code'] ?? '鲁';
        $record_number = generate_record_number($province);
        $year = date('Y');
        preg_match('/\d{4}$/', $record_number, $matches);
        $serial = intval($matches[0] ?? 0);
        
        // 直接更新（不使用事务）
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
        $admin_name = $_SESSION['admin_username'] ?? '管理员';
        $stmt->execute([$id, $admin_name]);
        
        // 发送邮件通知
        if (!empty($file['submitted_email'])) {
            $settings = get_all_settings();
            $subject = '【' . ($settings['site_name'] ?? '导航站') . '】网站审核通过通知';
            $body = build_approve_email($file, $record_number);
            send_email_with_log($file['submitted_email'], $subject, $body);
        }
        
        echo '<script>alert("✅ 审核通过！备案编号：' . addslashes($record_number) . '");window.location.href="/admin.php?tab=pending";</script>';
        exit;
        
    } catch (Exception $e) {
        echo '<script>alert("❌ 审核失败：' . addslashes($e->getMessage()) . '");window.location.href="/admin.php?tab=pending";</script>';
        exit;
    }
}

// ============================================
// 处理拒绝审核 - 无事务版本
// ============================================
if (isset($_GET['reject']) && is_numeric($_GET['reject'])) {
    $id = intval($_GET['reject']);
    $reason = isset($_GET['reason']) ? urldecode($_GET['reason']) : '未提供具体原因';
    try {
        $db = get_db();
        
        // 获取网站信息
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$file) {
            throw new Exception('网站不存在');
        }
        if ($file['status'] == 'approved') {
            throw new Exception('该网站已经审核通过，无法拒绝');
        }
        
        // 直接更新（不使用事务）
        $stmt = $db->prepare("UPDATE files SET status = 'rejected', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$id]);
        
        // 记录审核日志
        $stmt = $db->prepare("
            INSERT INTO review_logs (file_id, action, reason, admin_name, created_at)
            VALUES (?, 'reject', ?, ?, CURRENT_TIMESTAMP)
        ");
        $admin_name = $_SESSION['admin_username'] ?? '管理员';
        $stmt->execute([$id, $reason, $admin_name]);
        
        // 发送邮件通知
        if (!empty($file['submitted_email'])) {
            $settings = get_all_settings();
            $subject = '【' . ($settings['site_name'] ?? '导航站') . '】网站审核未通过通知';
            $body = build_reject_email($file, $reason);
            send_email_with_log($file['submitted_email'], $subject, $body);
        }
        
        echo '<script>alert("✅ 已拒绝，邮件已发送");window.location.href="/admin.php?tab=pending";</script>';
        exit;
        
    } catch (Exception $e) {
        echo '<script>alert("❌ 拒绝失败：' . addslashes($e->getMessage()) . '");window.location.href="/admin.php?tab=pending";</script>';
        exit;
    }
}

// ============================================
// 处理生成编号
// ============================================
if (isset($_GET['generate_number']) && is_numeric($_GET['generate_number'])) {
    $id = intval($_GET['generate_number']);
    try {
        $stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file && empty($file['record_number'])) {
            $province = $file['province_code'] ?? '鲁';
            $record_number = generate_record_number($province);
            $year = date('Y');
            $serial = intval(substr($record_number, -5, 4));
            
            $stmt = $db->prepare("
                UPDATE files SET 
                    record_number = ?,
                    record_year = ?,
                    record_province = ?,
                    record_serial = ?
                WHERE id = ?
            ");
            $stmt->execute([$record_number, $year, $province, $serial, $id]);
            
            echo '<script>alert("✅ 编号生成成功：' . $record_number . '");window.location.href="/admin.php?tab=sites";</script>';
            exit;
        }
    } catch (Exception $e) {
        $gen_error = '生成失败：' . $e->getMessage();
    }
}

// ============================================
// 处理批量生成编号
// ============================================
if (isset($_GET['batch_generate'])) {
    try {
        $stmt = $db->query("SELECT * FROM files WHERE record_number IS NULL OR record_number = ''");
        $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $generated = 0;
        
        foreach ($files as $file) {
            $province = $file['province_code'] ?? '鲁';
            $record_number = generate_record_number($province);
            $year = date('Y');
            $serial = intval(substr($record_number, -5, 4));
            
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
        }
        
        echo '<script>alert("✅ 批量生成完成：共生成 ' . $generated . ' 个编号");window.location.href="/admin.php?tab=sites";</script>';
        exit;
    } catch (Exception $e) {
        $batch_error = '批量生成失败：' . $e->getMessage();
    }
}

// ============================================
// 处理举报
// ============================================
if (isset($_GET['report_action']) && isset($_GET['report_id'])) {
    $report_id = intval($_GET['report_id']);
    $action = $_GET['report_action'];
    try {
        $stmt = $db->prepare("UPDATE reports SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$action === 'resolve' ? 'resolved' : 'rejected', $report_id]);
        echo '<script>alert("✅ 举报已处理");window.location.href="/admin.php?tab=reports";</script>';
        exit;
    } catch (Exception $e) {
        $report_error = '操作失败：' . $e->getMessage();
    }
}

// ============================================
// 处理分类操作
// ============================================
$cat_result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 添加分类
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['cat_name'] ?? '');
        $icon = trim($_POST['cat_icon'] ?? '📂');
        if (empty($name)) {
            $cat_result = '<div class="result-error">❌ 请输入分类名称</div>';
        } else {
            try {
                $stmt = $db->prepare("INSERT OR IGNORE INTO categories (type, name, icon) VALUES ('nav_type', ?, ?)");
                $stmt->execute([$name, $icon]);
                $cat_result = '<div class="result-success">✅ 分类添加成功！</div>';
            } catch (Exception $e) {
                $cat_result = '<div class="result-error">❌ ' . $e->getMessage() . '</div>';
            }
        }
    }
    // 删除分类
    if (isset($_POST['delete_category'])) {
        $id = intval($_POST['cat_id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ? AND type = 'nav_type'");
            $stmt->execute([$id]);
            $cat_result = '<div class="result-success">✅ 分类已删除！</div>';
        } catch (Exception $e) {
            $cat_result = '<div class="result-error">❌ ' . $e->getMessage() . '</div>';
        }
    }
    // 添加标签
    if (isset($_POST['add_tag'])) {
        $name = trim($_POST['tag_name'] ?? '');
        if (empty($name)) {
            $cat_result = '<div class="result-error">❌ 请输入标签名称</div>';
        } else {
            try {
                $stmt = $db->prepare("INSERT OR IGNORE INTO categories (type, name) VALUES ('nav_tag', ?)");
                $stmt->execute([$name]);
                $cat_result = '<div class="result-success">✅ 标签添加成功！</div>';
            } catch (Exception $e) {
                $cat_result = '<div class="result-error">❌ ' . $e->getMessage() . '</div>';
            }
        }
    }
    // 删除标签
    if (isset($_POST['delete_tag'])) {
        $id = intval($_POST['tag_id'] ?? 0);
        try {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ? AND type = 'nav_tag'");
            $stmt->execute([$id]);
            $cat_result = '<div class="result-success">✅ 标签已删除！</div>';
        } catch (Exception $e) {
            $cat_result = '<div class="result-error">❌ ' . $e->getMessage() . '</div>';
        }
    }
}

// ============================================
// 处理设置更新
// ============================================
$settings_result = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    try {
        // 基本设置
        update_setting('site_name', trim($_POST['site_name'] ?? ''));
        update_setting('site_url', trim($_POST['site_url'] ?? ''));
        update_setting('icp_number', trim($_POST['icp_number'] ?? ''));
        update_setting('contact_email', trim($_POST['contact_email'] ?? ''));
        update_setting('copyright', trim($_POST['copyright'] ?? ''));
        
        // 备案编号设置
        update_setting('record_prefix', trim($_POST['record_prefix'] ?? '网AN'));
        update_setting('record_enabled', isset($_POST['record_enabled']) ? '1' : '0');
        
        // 跳转页设置
        update_setting('jump_ad_enabled', isset($_POST['jump_ad_enabled']) ? '1' : '0');
        update_setting('jump_ad_timeout', intval($_POST['jump_ad_timeout'] ?? 5));
        update_setting('jump_ad_image', trim($_POST['jump_ad_image'] ?? ''));
        update_setting('jump_ad_text', trim($_POST['jump_ad_text'] ?? ''));
        
        // SMTP设置
        update_setting('smtp_host', trim($_POST['smtp_host'] ?? ''));
        update_setting('smtp_port', trim($_POST['smtp_port'] ?? ''));
        update_setting('smtp_secure', trim($_POST['smtp_secure'] ?? ''));
        update_setting('smtp_user', trim($_POST['smtp_user'] ?? ''));
        if (!empty($_POST['smtp_pass'])) {
            update_setting('smtp_pass', trim($_POST['smtp_pass'] ?? ''));
        }
        update_setting('smtp_from', trim($_POST['smtp_from'] ?? ''));
        update_setting('smtp_from_name', trim($_POST['smtp_from_name'] ?? ''));
        
        $settings_result = '<div class="result-success">✅ 设置已保存！</div>';
    } catch (Exception $e) {
        $settings_result = '<div class="result-error">❌ 保存失败：' . $e->getMessage() . '</div>';
    }
}

// ============================================
// 获取数据
// ============================================
$items = $db->query("SELECT * FROM files ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$pending_items = $db->query("SELECT * FROM files WHERE status = 'pending' ORDER BY submitted_at DESC, created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $db->query("SELECT * FROM categories WHERE type = 'nav_type' ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$tags = $db->query("SELECT * FROM categories WHERE type = 'nav_tag' ORDER BY sort_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$reports = $db->query("SELECT r.*, f.name as file_name FROM reports r LEFT JOIN files f ON r.file_id = f.id ORDER BY r.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// 获取当前标签
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'sites';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>后台管理 - <?php echo htmlspecialchars($settings['site_name'] ?? '而立导航'); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; background: #f0f2f5; padding: 16px; }
        .container { max-width: 1400px; margin: 0 auto; }
        
        .header { background: #fff; padding: 16px 24px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
        .header .logo { font-size: 20px; font-weight: 700; color: #4CAF50; }
        .header .logo span { color: #333; }
        .header .user-info { color: #666; font-size: 14px; }
        .header .logout { color: #f44336; text-decoration: none; margin-left: 12px; }
        .header .logout:hover { text-decoration: underline; }
        
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 16px; }
        .stat { background: #fff; padding: 14px 18px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); text-align: center; }
        .stat .num { font-size: 26px; font-weight: bold; color: #4CAF50; }
        .stat .num.warning { color: #FF9800; }
        .stat .num.danger { color: #f44336; }
        .stat .label { color: #888; font-size: 13px; margin-top: 2px; }
        
        .tabs { display: flex; flex-wrap: wrap; gap: 2px; background: #fff; border-radius: 8px 8px 0 0; padding: 6px 12px 0; box-shadow: 0 1px 4px rgba(0,0,0,0.06); border-bottom: 2px solid #4CAF50; }
        .tabs a { padding: 10px 20px; text-decoration: none; font-size: 14px; color: #666; border-bottom: 3px solid transparent; transition: 0.2s; border-radius: 4px 4px 0 0; }
        .tabs a:hover { color: #333; background: #f5f5f5; }
        .tabs a.active { color: #4CAF50; border-bottom-color: #4CAF50; font-weight: bold; background: #f0f7f0; }
        .tabs a .badge { display: inline-block; background: #f44336; color: #fff; border-radius: 10px; padding: 0 8px; font-size: 11px; margin-left: 4px; }
        
        .tab-content { display: none; background: #fff; padding: 20px 24px; border-radius: 0 0 8px 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.06); }
        .tab-content.active { display: block; }
        
        .card { background: #fff; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; border: 1px solid #f0f0f0; }
        .card h3 { font-size: 16px; color: #333; margin-bottom: 12px; border-bottom: 2px solid #4CAF50; padding-bottom: 8px; }
        .card h4 { font-size: 14px; color: #555; margin: 12px 0 8px; }
        
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 12px; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 14px; margin-bottom: 12px; }
        .form-group { margin-bottom: 12px; }
        .form-group label { display: block; font-weight: 600; font-size: 13px; color: #444; margin-bottom: 3px; }
        .form-group .required { color: #f44336; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; box-sizing: border-box; transition: 0.2s; background: #fafafa; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: #4CAF50; background: #fff; box-shadow: 0 0 0 3px rgba(76,175,80,0.08); }
        .form-group textarea { resize: vertical; font-family: inherit; min-height: 60px; }
        .form-group small { color: #999; font-size: 12px; display: block; margin-top: 2px; }
        
        .btn { padding: 8px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; transition: 0.2s; display: inline-block; text-decoration: none; }
        .btn:hover { transform: scale(1.02); }
        .btn-primary { background: #4CAF50; color: #fff; }
        .btn-primary:hover { background: #388E3C; }
        .btn-edit { background: #2196F3; color: #fff; }
        .btn-edit:hover { background: #1976D2; }
        .btn-danger { background: #f44336; color: #fff; }
        .btn-danger:hover { background: #d32f2f; }
        .btn-warning { background: #FF9800; color: #fff; }
        .btn-warning:hover { background: #E68900; }
        .btn-success { background: #4CAF50; color: #fff; }
        .btn-success:hover { background: #388E3C; }
        .btn-sm { padding: 3px 10px; font-size: 11px; }
        .btn-block { width: 100%; padding: 10px; }
        
        .table-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { background: #f8f9fa; padding: 10px 12px; text-align: left; border-bottom: 2px solid #eee; white-space: nowrap; font-weight: 600; }
        td { padding: 8px 12px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
        tr:hover { background: #fafafa; }
        
        .badge { display: inline-block; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 500; }
        .badge-success { background: #4CAF50; color: #fff; }
        .badge-warning { background: #FF9800; color: #fff; }
        .badge-danger { background: #f44336; color: #fff; }
        .badge-info { background: #2196F3; color: #fff; }
        .badge-secondary { background: #9e9e9e; color: #fff; }
        
        .cat-list { display: flex; flex-wrap: wrap; gap: 8px; margin: 8px 0; }
        .cat-item { display: inline-flex; align-items: center; gap: 6px; background: #f5f5f5; padding: 4px 12px; border-radius: 16px; font-size: 13px; }
        .cat-item .del { color: #f44336; cursor: pointer; font-size: 14px; margin-left: 4px; }
        .cat-item .del:hover { color: #d32f2f; }
        
        .result-success { color: #2e7d32; padding: 10px 14px; background: #e8f5e9; border-radius: 4px; border: 1px solid #4CAF50; margin: 10px 0; }
        .result-error { color: #c62828; padding: 10px 14px; background: #fce4ec; border-radius: 4px; border: 1px solid #f44336; margin: 10px 0; }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 9999; padding: 15px; }
        .modal.active { display: flex; }
        .modal-content { background: #fff; padding: 24px; border-radius: 8px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; animation: modalFadeIn 0.3s ease; }
        @keyframes modalFadeIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        .modal-content .close { float: right; font-size: 24px; cursor: pointer; color: #999; line-height: 1; }
        .modal-content .close:hover { color: #333; }
        .modal-content h3 { margin-bottom: 14px; font-size: 18px; }
        .modal-content label { display: block; margin: 8px 0 3px; font-weight: 600; font-size: 13px; }
        .modal-content input, .modal-content select, .modal-content textarea { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; font-size: 13px; }
        .modal-content .btn { width: 100%; margin-top: 10px; }
        
        @media (max-width: 768px) {
            body { padding: 8px; }
            .form-row, .form-row-3 { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start; gap: 6px; }
            .stats { grid-template-columns: 1fr 1fr; }
            .tabs a { padding: 6px 12px; font-size: 12px; }
            .tab-content { padding: 12px; }
        }
        @media (max-width: 480px) {
            .stats { grid-template-columns: 1fr 1fr; gap: 6px; }
            .stat .num { font-size: 18px; }
            .stat { padding: 8px; }
            .tabs a { font-size: 11px; padding: 4px 8px; }
            table { font-size: 11px; }
            .btn-sm { font-size: 10px; padding: 2px 6px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ===== 头部 ===== -->
        <div class="header">
            <div class="logo">⚙️ <span><?php echo htmlspecialchars($settings['site_name'] ?? '而立导航'); ?></span> - 后台管理</div>
            <div>
                <span class="user-info">👤 <?php echo htmlspecialchars($_SESSION['admin_username'] ?? '管理员'); ?></span>
                <a href="?logout=1" class="logout">退出</a>
            </div>
        </div>
        
        <!-- ===== 统计卡片 ===== -->
        <div class="stats">
            <div class="stat"><div class="num"><?php echo $total_sites; ?></div><div class="label">🌐 网站总数</div></div>
            <div class="stat"><div class="num"><?php echo $total_clicks; ?></div><div class="label">👆 总点击量</div></div>
            <div class="stat"><div class="num <?php echo $pending_count > 0 ? 'warning' : ''; ?>"><?php echo $pending_count; ?></div><div class="label">⏳ 待审核</div></div>
            <div class="stat"><div class="num <?php echo $total_reports > 0 ? 'danger' : ''; ?>"><?php echo $total_reports; ?></div><div class="label">🚨 举报</div></div>
        </div>
        
        <!-- ===== 标签导航 ===== -->
        <div class="tabs">
            <a href="?tab=sites" class="<?php echo $current_tab == 'sites' ? 'active' : ''; ?>">🌐 网站管理</a>
            <a href="?tab=pending" class="<?php echo $current_tab == 'pending' ? 'active' : ''; ?>">⏳ 待审核 <?php if($pending_count > 0): ?><span class="badge"><?php echo $pending_count; ?></span><?php endif; ?></a>
            <a href="?tab=add" class="<?php echo $current_tab == 'add' ? 'active' : ''; ?>">➕ 添加网站</a>
            <a href="?tab=categories" class="<?php echo $current_tab == 'categories' ? 'active' : ''; ?>">📂 分类管理</a>
            <a href="?tab=tags" class="<?php echo $current_tab == 'tags' ? 'active' : ''; ?>">🏷️ 标签管理</a>
            <a href="?tab=settings" class="<?php echo $current_tab == 'settings' ? 'active' : ''; ?>">⚙️ 系统设置</a>
            <a href="?tab=reports" class="<?php echo $current_tab == 'reports' ? 'active' : ''; ?>">🚨 举报管理 <?php if($total_reports > 0): ?><span class="badge"><?php echo $total_reports; ?></span><?php endif; ?></a>
        </div>
        
        <!-- ============================================================ -->
        <!-- Tab: 网站管理 -->
        <!-- ============================================================ -->
        <div class="tab-content <?php echo $current_tab == 'sites' ? 'active' : ''; ?>" id="tab-sites">
            <div class="card">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
                    <h3 style="margin:0;border:none;padding:0;">📋 网站列表 (<?php echo count($items); ?>)</h3>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a href="?batch_generate=1" class="btn btn-warning btn-sm" onclick="return confirm('确定为所有未生成编号的网站批量生成备案编号吗？')">📝 批量生成编号</a>
                    </div>
                </div>
                
                <?php if (isset($delete_error)): ?>
                    <div class="result-error">❌ <?php echo $delete_error; ?></div>
                <?php endif; ?>
                <?php if (isset($gen_error)): ?>
                    <div class="result-error">❌ <?php echo $gen_error; ?></div>
                <?php endif; ?>
                <?php if (isset($batch_error)): ?>
                    <div class="result-error">❌ <?php echo $batch_error; ?></div>
                <?php endif; ?>
                
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>网站名称</th>
                                <th>分类</th>
                                <th>标签</th>
                                <th>访问量</th>
                                <th>备案编号</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">暂无数据</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?php echo $item['id']; ?></td>
                                        <td>
                                            <?php if (!empty($item['icon_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['icon_url']); ?>" style="width:20px;height:20px;border-radius:4px;object-fit:contain;vertical-align:middle;" onerror="this.style.display='none'">
                                            <?php else: ?>
                                                🌐
                                            <?php endif; ?>
                                            <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" style="color:#2196F3;text-decoration:none;"><?php echo htmlspecialchars($item['name']); ?></a>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item['tags'] ?? '-'); ?></td>
                                        <td><?php echo $item['clicks'] ?? 0; ?></td>
                                        <td>
                                            <?php if (!empty($item['record_number'])): ?>
                                                <span style="color:#4CAF50;font-weight:bold;"><?php echo htmlspecialchars($item['record_number']); ?></span>
                                            <?php else: ?>
                                                <span style="color:#999;">未生成</span>
                                                <a href="?generate_number=<?php echo $item['id']; ?>&tab=sites" class="btn btn-warning btn-sm">生成</a>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_map = [
                                                'approved' => '<span class="badge badge-success">已审核</span>',
                                                'pending' => '<span class="badge badge-warning">待审核</span>',
                                                'rejected' => '<span class="badge badge-danger">已拒绝</span>'
                                            ];
                                            echo $status_map[$item['status']] ?? $item['status'];
                                            ?>
                                        </td>
                                        <td>
                                            <a href="?tab=sites&edit=<?php echo $item['id']; ?>" class="btn btn-edit btn-sm">编辑</a>
                                            <a href="?delete=<?php echo $item['id']; ?>&tab=sites" class="btn btn-danger btn-sm" onclick="return confirm('确定删除该网站吗？此操作不可恢复！')">删除</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 编辑区域 -->
            <?php if (isset($_GET['edit']) && is_numeric($_GET['edit'])):
                $edit_stmt = $db->prepare("SELECT * FROM files WHERE id = ?");
                $edit_stmt->execute([intval($_GET['edit'])]);
                $edit_item = $edit_stmt->fetch(PDO::FETCH_ASSOC);
                if ($edit_item):
            ?>
            <div class="card" style="border:2px solid #4CAF50;">
                <h3>✏️ 编辑网站</h3>
                <?php echo $edit_result; ?>
                <form method="post">
                    <input type="hidden" name="edit_site" value="1">
                    <input type="hidden" name="edit_id" value="<?php echo $edit_item['id']; ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>网站名称 <span class="required">*</span></label>
                            <input type="text" name="edit_name" value="<?php echo htmlspecialchars($edit_item['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>网站地址 <span class="required">*</span></label>
                            <input type="url" name="edit_url" value="<?php echo htmlspecialchars($edit_item['url']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>分类 <span class="required">*</span></label>
                            <select name="edit_category" required>
                                <option value="">请选择</option>
                                <?php foreach ($nav_types as $type): ?>
                                    <option value="<?php echo $type; ?>" <?php echo $edit_item['category'] == $type ? 'selected' : ''; ?>><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>标签</label>
                            <select name="edit_tags">
                                <option value="">请选择</option>
                                <?php foreach ($nav_tags as $tag): ?>
                                    <option value="<?php echo $tag; ?>" <?php echo $edit_item['tags'] == $tag ? 'selected' : ''; ?>><?php echo $tag; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>图标URL</label>
                            <input type="url" name="edit_icon_url" value="<?php echo htmlspecialchars($edit_item['icon_url'] ?? ''); ?>" placeholder="https://example.com/icon.png">
                        </div>
                        <div class="form-group">
                            <label>备案编号</label>
                            <input type="text" value="<?php echo htmlspecialchars($edit_item['record_number'] ?? '未生成'); ?>" disabled style="background:#f5f5f5;">
                            <small>备案编号不可编辑，如需修改请重新生成</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>描述</label>
                        <textarea name="edit_description" rows="3"><?php echo htmlspecialchars($edit_item['description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <button type="submit" class="btn btn-primary">💾 保存修改</button>
                        <a href="?tab=sites" class="btn btn-danger">取消</a>
                    </div>
                </form>
            </div>
            <?php endif; endif; ?>
        </div>
        
        <!-- ============================================================ -->
        <!-- Tab: 待审核 -->
        <!-- ============================================================ -->
        <div class="tab-content <?php echo $current_tab == 'pending' ? 'active' : ''; ?>" id="tab-pending">
            <div class="card">
                <h3>⏳ 待审核网站 (<?php echo count($pending_items); ?>)</h3>
                
                <?php if (isset($approve_error)): ?>
                    <div class="result-error">❌ <?php echo $approve_error; ?></div>
                <?php endif; ?>
                <?php if (isset($reject_error)): ?>
                    <div class="result-error">❌ <?php echo $reject_error; ?></div>
                <?php endif; ?>
                
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>网站名称</th>
                                <th>分类</th>
                                <th>提交者</th>
                                <th>邮箱</th>
                                <th>提交时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_items)): ?>
                                <tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">🎉 暂无待审核</td></tr>
                            <?php else: ?>
                                <?php foreach ($pending_items as $item): ?>
                                    <tr>
                                        <td><?php echo $item['id']; ?></td>
                                        <td>
                                            <?php if (!empty($item['icon_url'])): ?>
                                                <img src="<?php echo htmlspecialchars($item['icon_url']); ?>" style="width:20px;height:20px;border-radius:4px;object-fit:contain;vertical-align:middle;" onerror="this.style.display='none'">
                                            <?php else: ?>
                                                🌐
                                            <?php endif; ?>
                                            <a href="<?php echo htmlspecialchars($item['url']); ?>" target="_blank" style="color:#2196F3;text-decoration:none;"><?php echo htmlspecialchars($item['name']); ?></a>
                                        </td>
                                        <td><?php echo htmlspecialchars($item['category'] ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($item['submitted_by'] ?? '匿名'); ?></td>
                                        <td><?php echo htmlspecialchars($item['submitted_email'] ?? '-'); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($item['submitted_at'] ?? $item['created_at'])); ?></td>
                                        <td>
                                            <a href="?approve=<?php echo $item['id']; ?>&tab=pending" class="btn btn-success btn-sm" onclick="return confirm('确定通过该网站审核吗？将自动生成备案编号并发送邮件通知。')">✅ 通过</a>
                                            <button class="btn btn-danger btn-sm" onclick="showRejectModal(<?php echo $item['id']; ?>)">❌ 拒绝</button>
                                            <a href="?tab=sites&edit=<?php echo $item['id']; ?>" class="btn btn-edit btn-sm">编辑</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- Tab: 添加网站 -->
        <!-- ============================================================ -->
        <div class="tab-content <?php echo $current_tab == 'add' ? 'active' : ''; ?>" id="tab-add">
            <div class="card">
                <h3>➕ 添加网站</h3>
                <?php echo $add_result; ?>
                <form method="post">
                    <input type="hidden" name="admin_add" value="1">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>网站名称 <span class="required">*</span></label>
                            <input type="text" name="name" placeholder="请输入网站名称" required>
                        </div>
                        <div class="form-group">
                            <label>网站地址 <span class="required">*</span></label>
                            <input type="url" name="url" placeholder="https://example.com" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>分类 <span class="required">*</span></label>
                            <select name="category" required>
                                <option value="">请选择</option>
                                <?php foreach ($nav_types as $type): ?>
                                    <option value="<?php echo $type; ?>"><?php echo $type; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>标签</label>
                            <select name="tags">
                                <option value="">请选择</option>
                                <?php foreach ($nav_tags as $tag): ?>
                                    <option value="<?php echo $tag; ?>"><?php echo $tag; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>图标URL</label>
                            <input type="url" name="icon_url" placeholder="https://example.com/icon.png">
                            <small>选填，不填将自动获取网站favicon</small>
                        </div>
                        <div class="form-group">
                            <label>备案省份 <span class="required">*</span></label>
                            <select name="province" required>
                                <?php foreach ($provinces as $code => $name): ?>
                                    <option value="<?php echo $code; ?>" <?php echo $code == '鲁' ? 'selected' : ''; ?>>
                                        <?php echo $name; ?> (<?php echo $code; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small>用于生成备案编号</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>ICP备案号</label>
                            <input type="text" name="icp_number" placeholder="例如：鲁ICP备20260001号">
                        </div>
                        <div class="form-group">
                            <label>公安备案号</label>
                            <input type="text" name="public_security_number" placeholder="例如：鲁公网安备 37010002000001号">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>网站描述</label>
                        <textarea name="description" rows="3" placeholder="请简要描述网站内容"></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">➕ 添加网站</button>
                </form>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- Tab: 分类管理 -->
        <!-- ============================================================ -->
        <div class="tab-content <?php echo $current_tab == 'categories' ? 'active' : ''; ?>" id="tab-categories">
            <div class="card">
                <h3>📂 分类管理</h3>
                <p style="color:#888;font-size:13px;margin-bottom:12px;">管理导航网站的分类，添加后可在添加网站时选择</p>
                
                <?php echo $cat_result; ?>
                
                <h4>当前分类</h4>
                <div class="cat-list">
                    <?php foreach ($categories as $cat): ?>
                        <span class="cat-item">
                            <?php echo htmlspecialchars($cat['icon'] ?? '📂'); ?>
                            <?php echo htmlspecialchars($cat['name']); ?>
                            <form method="post" style="display:inline;margin:0;">
                                <input type="hidden" name="delete_category" value="1">
                                <input type="hidden" name="cat_id" value="<?php echo $cat['id']; ?>">
                                <button type="submit" class="del" onclick="return confirm('确定删除该分类吗？')">×</button>
                            </form>
                        </span>
                    <?php endforeach; ?>
                    <?php if (empty($categories)): ?>
                        <span style="color:#999;font-size:13px;">暂无分类</span>
                    <?php endif; ?>
                </div>
                
                <h4 style="margin-top:16px;">添加新分类</h4>
                <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                    <input type="hidden" name="add_category" value="1">
                    <div style="flex:1;min-width:150px;">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:2px;">分类名称</label>
                        <input type="text" name="cat_name" placeholder="请输入分类名称" required style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                    </div>
                    <div style="flex:0 0 80px;">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:2px;">图标</label>
                        <input type="text" name="cat_icon" placeholder="📂" value="📂" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:16px;text-align:center;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="height:38px;">添加分类</button>
                </form>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- Tab: 标签管理 -->
        <!-- ============================================================ -->
        <div class="tab-content <?php echo $current_tab == 'tags' ? 'active' : ''; ?>" id="tab-tags">
            <div class="card">
                <h3>🏷️ 标签管理</h3>
                <p style="color:#888;font-size:13px;margin-bottom:12px;">管理导航网站的标签，添加后可在添加网站时选择</p>
                
                <?php echo $cat_result; ?>
                
                <h4>当前标签</h4>
                <div class="cat-list">
                    <?php foreach ($tags as $tag): ?>
                        <span class="cat-item">
                            🏷️ <?php echo htmlspecialchars($tag['name']); ?>
                            <form method="post" style="display:inline;margin:0;">
                                <input type="hidden" name="delete_tag" value="1">
                                <input type="hidden" name="tag_id" value="<?php echo $tag['id']; ?>">
                                <button type="submit" class="del" onclick="return confirm('确定删除该标签吗？')">×</button>
                            </form>
                        </span>
                    <?php endforeach; ?>
                    <?php if (empty($tags)): ?>
                        <span style="color:#999;font-size:13px;">暂无标签</span>
                    <?php endif; ?>
                </div>
                
                <h4 style="margin-top:16px;">添加新标签</h4>
                <form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                    <input type="hidden" name="add_tag" value="1">
                    <div style="flex:1;min-width:200px;">
                        <label style="font-weight:600;font-size:13px;display:block;margin-bottom:2px;">标签名称</label>
                        <input type="text" name="tag_name" placeholder="请输入标签名称" required style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm" style="height:38px;">添加标签</button>
                </form>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- Tab: 系统设置 -->
        <!-- ============================================================ -->
        <div class="tab-content <?php echo $current_tab == 'settings' ? 'active' : ''; ?>" id="tab-settings">
            <div class="card">
                <h3>⚙️ 系统设置</h3>
                <?php echo $settings_result; ?>
                
                <form method="post">
                    <input type="hidden" name="update_settings" value="1">
                    
                    <!-- 基本设置 -->
                    <h4>📌 基本设置</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>网站名称</label>
                            <input type="text" name="site_name" value="<?php echo htmlspecialchars($settings['site_name'] ?? '而立导航'); ?>">
                        </div>
                        <div class="form-group">
                            <label>网站地址</label>
                            <input type="url" name="site_url" value="<?php echo htmlspecialchars($settings['site_url'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>ICP备案号</label>
                            <input type="text" name="icp_number" value="<?php echo htmlspecialchars($settings['icp_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>联系邮箱</label>
                            <input type="email" name="contact_email" value="<?php echo htmlspecialchars($settings['contact_email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>版权信息</label>
                        <input type="text" name="copyright" value="<?php echo htmlspecialchars($settings['copyright'] ?? ''); ?>">
                    </div>
                    
                    <!-- 备案编号设置 -->
                    <h4>📋 备案编号设置</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>备案编号前缀 <span style="color:#999;font-size:12px;">（如：网AN）</span></label>
                            <input type="text" name="record_prefix" value="<?php echo htmlspecialchars($settings['record_prefix'] ?? '网AN'); ?>" placeholder="网AN">
                            <small>默认：网AN，修改后新生成的编号将使用此前缀</small>
                        </div>
                        <div class="form-group">
                            <label>启用备案编号</label>
                            <select name="record_enabled">
                                <option value="1" <?php echo ($settings['record_enabled'] ?? '1') == '1' ? 'selected' : ''; ?>>启用</option>
                                <option value="0" <?php echo ($settings['record_enabled'] ?? '1') == '0' ? 'selected' : ''; ?>>禁用</option>
                            </select>
                            <small>关闭后前台不显示备案编号</small>
                        </div>
                    </div>
                    
                    <!-- 跳转页设置 -->
                    <h4>🔗 跳转页设置</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>跳转页广告</label>
                            <select name="jump_ad_enabled">
                                <option value="1" <?php echo ($settings['jump_ad_enabled'] ?? '1') == '1' ? 'selected' : ''; ?>>启用</option>
                                <option value="0" <?php echo ($settings['jump_ad_enabled'] ?? '1') == '0' ? 'selected' : ''; ?>>禁用</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>跳转等待秒数</label>
                            <input type="number" name="jump_ad_timeout" value="<?php echo htmlspecialchars($settings['jump_ad_timeout'] ?? '5'); ?>" min="1" max="15">
                            <small>用户等待几秒后自动跳转</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>跳转页广告图片URL</label>
                        <input type="url" name="jump_ad_image" value="<?php echo htmlspecialchars($settings['jump_ad_image'] ?? ''); ?>" placeholder="https://example.com/ad.png">
                        <small>推荐尺寸：600×200px</small>
                    </div>
                    <div class="form-group">
                        <label>跳转页广告文字</label>
                        <textarea name="jump_ad_text" rows="2"><?php echo htmlspecialchars($settings['jump_ad_text'] ?? '正在为您跳转到目标网站，请稍候...'); ?></textarea>
                    </div>
                    
                    <!-- SMTP设置 -->
                    <h4>📧 SMTP邮件设置</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>SMTP服务器</label>
                            <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? 'smtp.qq.com'); ?>">
                        </div>
                        <div class="form-group">
                            <label>SMTP端口</label>
                            <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '465'); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>加密方式</label>
                            <select name="smtp_secure">
                                <option value="ssl" <?php echo ($settings['smtp_secure'] ?? 'ssl') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="tls" <?php echo ($settings['smtp_secure'] ?? '') == 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="" <?php echo ($settings['smtp_secure'] ?? '') == '' ? 'selected' : ''; ?>>无</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>发件人邮箱</label>
                            <input type="email" name="smtp_from" value="<?php echo htmlspecialchars($settings['smtp_from'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>发件人名称</label>
                            <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? '而立导航'); ?>">
                        </div>
                        <div class="form-group">
                            <label>SMTP用户名</label>
                            <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($settings['smtp_user'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>SMTP密码（授权码）</label>
                        <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($settings['smtp_pass'] ?? ''); ?>" placeholder="留空则保持不变">
                        <small>QQ邮箱请使用授权码，在邮箱设置中获取</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block" style="margin-top:12px;">💾 保存所有设置</button>
                </form>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- Tab: 举报管理 -->
        <!-- ============================================================ -->
        <div class="tab-content <?php echo $current_tab == 'reports' ? 'active' : ''; ?>" id="tab-reports">
            <div class="card">
                <h3>🚨 举报管理</h3>
                
                <?php if (isset($report_error)): ?>
                    <div class="result-error">❌ <?php echo $report_error; ?></div>
                <?php endif; ?>
                
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>网站</th>
                                <th>举报原因</th>
                                <th>举报人</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reports)): ?>
                                <tr><td colspan="6" style="text-align:center;padding:30px;color:#999;">暂无举报记录</td></tr>
                            <?php else: ?>
                                <?php foreach ($reports as $report): ?>
                                    <tr>
                                        <td><?php echo $report['id']; ?></td>
                                        <td><?php echo htmlspecialchars($report['file_name'] ?? '已删除'); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($report['reason']); ?></strong>
                                            <?php if (!empty($report['detail'])): ?>
                                                <br><small style="color:#999;"><?php echo htmlspecialchars($report['detail']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($report['reporter_name'] ?? '匿名'); ?>
                                            <?php if (!empty($report['reporter_email'])): ?>
                                                <br><small style="color:#999;"><?php echo htmlspecialchars($report['reporter_email']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $r_status_map = [
                                                'pending' => '<span class="badge badge-warning">待处理</span>',
                                                'resolved' => '<span class="badge badge-success">已解决</span>',
                                                'rejected' => '<span class="badge badge-danger">已拒绝</span>'
                                            ];
                                            echo $r_status_map[$report['status']] ?? $report['status'];
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($report['status'] == 'pending'): ?>
                                                <a href="?report_action=resolve&report_id=<?php echo $report['id']; ?>&tab=reports" class="btn btn-success btn-sm" onclick="return confirm('确定将该举报标记为已解决吗？')">已解决</a>
                                                <a href="?report_action=reject&report_id=<?php echo $report['id']; ?>&tab=reports" class="btn btn-danger btn-sm" onclick="return confirm('确定拒绝该举报吗？')">拒绝</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ===== 拒绝弹窗 ===== -->
    <div class="modal" id="reject-modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">×</span>
            <h3>❌ 拒绝审核</h3>
            <p style="color:#888;font-size:13px;margin-bottom:12px;">请填写拒绝理由，将发送邮件通知提交者</p>
            <form id="reject-form" method="get">
                <input type="hidden" name="tab" value="pending">
                <input type="hidden" name="reject" id="reject-id">
                <div class="form-group">
                    <label>拒绝理由 <span style="color:#f44336;">*</span></label>
                    <textarea name="reason" id="reject-reason" rows="4" required placeholder="请填写拒绝理由..." style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;resize:vertical;"></textarea>
                </div>
                <button type="submit" class="btn btn-danger btn-block">确认拒绝</button>
            </form>
        </div>
    </div>
    
    <script>
        // ========================================
        // 拒绝弹窗
        // ========================================
        function showRejectModal(id) {
            document.getElementById('reject-id').value = id;
            document.getElementById('reject-modal').classList.add('active');
            document.getElementById('reject-reason').value = '';
        }
        
        function closeModal() {
            document.getElementById('reject-modal').classList.remove('active');
        }
        
        document.getElementById('reject-modal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });
    </script>
</body>
</html>