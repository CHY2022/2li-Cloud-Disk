<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
// ============================================
// 用户提交网站
// ============================================

require_once 'config.php';
require_once 'functions.php';

$settings = get_all_settings();
$db = get_db();

// 获取分类
$nav_types = get_nav_types();
$provinces = PROVINCES;

// 处理提交
$submit_result = '';
$show_form = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit') {
    $name = trim($_POST['name'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $province_code = trim($_POST['province'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $icp_number = trim($_POST['icp_number'] ?? '');
    $public_security_number = trim($_POST['public_security_number'] ?? '');
    $has_friend_link = isset($_POST['has_friend_link']) ? 1 : 0;
    $email = trim($_POST['email'] ?? '');
    $submitter = trim($_POST['submitter'] ?? '');
    
    // 验证
    $errors = [];
    if (empty($name)) $errors[] = '请填写网站名称';
    if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) $errors[] = '请输入有效的网站地址';
    if (empty($category)) $errors[] = '请选择网站分类';
    if (empty($province_code)) $errors[] = '请选择网站所在省份';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = '请输入有效的联系邮箱';
    if ($has_friend_link == 0) $errors[] = '请确认已添加本站友情链接';
    
    if (empty($errors)) {
        try {
            // 检查是否已提交
            $stmt = $db->prepare("SELECT id FROM files WHERE url = ? AND status != 'rejected'");
            $stmt->execute([$url]);
            if ($stmt->fetch()) {
                $errors[] = '该网站已提交过，请勿重复提交';
            }
        } catch (Exception $e) {}
    }
    
    if (empty($errors)) {
        try {
            // 插入数据（状态为 pending）
            $stmt = $db->prepare("
                INSERT INTO files (
                    name, url, category, province_code, description, icon_url,
                    icp_number, public_security_number, has_friend_link,
                    submitted_email, submitted_by, submitted_at,
                    status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, 'pending', CURRENT_TIMESTAMP)
            ");
            $stmt->execute([
                $name, $url, $category, $province_code, $description, $logo_url,
                $icp_number, $public_security_number, $has_friend_link,
                $email, $submitter
            ]);
            
            $file_id = $db->lastInsertId();
            
            // 发送通知邮件给管理员
            send_admin_notification($file_id, $name, $url, $email);
            
            $submit_result = '<div class="result-success">
                <div class="icon">✅</div>
                <h3>提交成功！</h3>
                <p>您的网站已提交审核，管理员会尽快处理。</p>
                <p style="font-size:13px;color:#555;margin-top:8px;">审核结果将通过邮件通知您，请保持邮箱畅通。</p>
                <a href="/" class="btn-back">🏠 返回首页</a>
            </div>';
            $show_form = false;
            
        } catch (Exception $e) {
            $errors[] = '提交失败：' . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $submit_result = '<div class="result-error">❌ ' . implode('<br>', $errors) . '</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>提交网站 - <?php echo htmlspecialchars($settings['site_name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        .submit-container {
            max-width: 700px;
            margin: 24px auto;
            background: #fff;
            padding: 28px 32px 32px;
            border-radius: 12px;
            box-shadow: 0 1px 8px rgba(0,0,0,0.06);
        }
        .submit-container h2 {
            text-align: center;
            font-size: 22px;
            margin-bottom: 4px;
            color: #333;
        }
        .submit-container .subtitle {
            text-align: center;
            color: #999;
            margin-bottom: 22px;
            font-size: 13px;
        }
        
        .submit-form .form-group {
            margin-bottom: 14px;
        }
        .submit-form label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #444;
            margin-bottom: 3px;
        }
        .submit-form .required {
            color: #f44336;
        }
        .submit-form input, .submit-form select, .submit-form textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: 0.2s;
            box-sizing: border-box;
            background: #fafafa;
        }
        .submit-form input:focus, .submit-form select:focus, .submit-form textarea:focus {
            outline: none;
            border-color: #4CAF50;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(76,175,80,0.08);
        }
        .submit-form textarea {
            resize: vertical;
            font-family: inherit;
            min-height: 60px;
        }
        .submit-form small {
            color: #999;
            font-size: 12px;
            display: block;
            margin-top: 2px;
        }
        .submit-form .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .submit-form .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 14px;
        }
        
        .submit-form .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        .submit-form .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #4CAF50;
            flex-shrink: 0;
        }
        .submit-form .checkbox-group label {
            font-weight: normal;
            cursor: pointer;
            margin: 0;
            font-size: 14px;
        }
        .submit-form .checkbox-group .hint {
            color: #999;
            font-size: 12px;
            margin-left: auto;
        }
        
        .btn-submit-form {
            width: 100%;
            padding: 11px;
            background: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            margin-top: 6px;
        }
        .btn-submit-form:hover {
            background: #388E3C;
        }
        .btn-submit-form:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .result-success {
            text-align: center;
            padding: 30px 20px;
        }
        .result-success .icon {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
        }
        .result-success h3 {
            color: #2e7d32;
            font-size: 20px;
            margin-bottom: 8px;
        }
        .result-success p {
            color: #555;
            font-size: 14px;
        }
        .result-success .btn-back {
            display: inline-block;
            margin-top: 16px;
            padding: 8px 30px;
            background: #4CAF50;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            transition: 0.3s;
        }
        .result-success .btn-back:hover {
            background: #388E3C;
        }
        
        .result-error {
            background: #fce4ec;
            color: #c62828;
            padding: 14px 18px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        
        .friend-link-info {
            background: #e3f2fd;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 13px;
            color: #1565c0;
            margin-bottom: 10px;
            border-left: 4px solid #2196F3;
        }
        .friend-link-info strong {
            display: block;
            margin-bottom: 4px;
        }
        
        @media (max-width: 768px) {
            .submit-container {
                padding: 18px 16px 22px;
                margin: 12px 8px;
            }
            .submit-container h2 {
                font-size: 19px;
            }
            .submit-form .form-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }
            .submit-form .form-row-3 {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
        @media (max-width: 480px) {
            .submit-container {
                padding: 12px 10px 16px;
                margin: 8px 4px;
            }
            .submit-container h2 {
                font-size: 17px;
            }
            .submit-form label {
                font-size: 12px;
            }
            .submit-form input, .submit-form select, .submit-form textarea {
                font-size: 13px;
                padding: 6px 10px;
            }
            .submit-form .checkbox-group {
                padding: 8px 10px;
            }
            .submit-form .checkbox-group label {
                font-size: 12px;
            }
            .btn-submit-form {
                font-size: 14px;
                padding: 9px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="site-header">
            <div class="header-inner">
                <div class="logo">
                    <?php if (!empty($settings['site_logo'])): ?>
                        <img src="<?php echo htmlspecialchars($settings['site_logo']); ?>" alt="<?php echo htmlspecialchars($settings['site_name']); ?>" style="height:36px;max-width:160px;">
                    <?php else: ?>
                        <a href="/"><?php echo htmlspecialchars($settings['site_name']); ?></a>
                    <?php endif; ?>
                </div>
                <nav class="nav">
                    <a href="/">首页</a>
                    <a href="/submit.php" class="active">提交网站</a>
                </nav>
            </div>
        </header>
        
        <div class="submit-container">
            <h2>📤 提交网站</h2>
            <p class="subtitle">提交后需等待管理员审核，审核通过后会在导航站展示</p>
            
            <?php if ($show_form): ?>
                <?php echo $submit_result; ?>
                
                <div class="friend-link-info">
                    <strong>🔗 友情链接要求</strong>
                    请先在您的网站添加本站链接，然后提交申请。<br>
                    本站名称：<strong><?php echo htmlspecialchars($settings['site_name'] ?? '而立导航'); ?></strong><br>
                    本站地址：<strong><?php echo htmlspecialchars($settings['site_url'] ?? SITE_URL); ?></strong>
                </div>
                
                <form method="post" class="submit-form" onsubmit="return preventDoubleSubmit(this)">
                    <input type="hidden" name="action" value="submit">
                    
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
                            <label>网站分类 <span class="required">*</span></label>
                            <select name="category" required>
                                <option value="">请选择分类</option>
                                <?php foreach ($nav_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>网站所在省份 <span class="required">*</span></label>
                            <select name="province" required>
                                <option value="">请选择省份</option>
                                <?php foreach ($provinces as $code => $name): ?>
                                    <option value="<?php echo $code; ?>"><?php echo htmlspecialchars($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <small>用于生成备案编号</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>网站Logo地址</label>
                            <input type="url" name="logo_url" placeholder="https://example.com/logo.png">
                            <small>选填，建议上传120x120的图标</small>
                        </div>
                        <div class="form-group">
                            <label>ICP备案号</label>
                            <input type="text" name="icp_number" placeholder="例如：鲁ICP备20260001号">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>公安备案号</label>
                        <input type="text" name="public_security_number" placeholder="例如：鲁公网安备 37010002000001号">
                        <small>选填</small>
                    </div>
                    
                    <div class="form-group">
                        <label>网站介绍</label>
                        <textarea name="description" rows="3" placeholder="请简要介绍网站内容和特色"></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>您的昵称</label>
                            <input type="text" name="submitter" placeholder="选填">
                        </div>
                        <div class="form-group">
                            <label>联系邮箱 <span class="required">*</span></label>
                            <input type="email" name="email" placeholder="用于接收审核通知" required>
                        </div>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <input type="checkbox" name="has_friend_link" id="has_friend_link" value="1" required>
                        <label for="has_friend_link">
                            我已添加本站友情链接 <span style="color:#f44336;">*</span>
                        </label>
                        <span class="hint">必选</span>
                    </div>
                    
                    <button type="submit" class="btn-submit-form" id="submit-btn">提交审核</button>
                </form>
            <?php else: ?>
                <?php echo $submit_result; ?>
            <?php endif; ?>
        </div>
        
        <footer class="site-footer">
            <p><?php echo htmlspecialchars($settings['copyright'] ?? ''); ?></p>
            <p><?php echo htmlspecialchars($settings['icp_number'] ?? ''); ?></p>
        </footer>
    </div>
    
    <script>
    function preventDoubleSubmit(form) {
        var btn = document.getElementById('submit-btn');
        if (btn.disabled) return false;
        btn.disabled = true;
        btn.textContent = '提交中...';
        return true;
    }
    </script>
</body>
</html>