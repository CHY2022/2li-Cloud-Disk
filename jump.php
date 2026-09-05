<?php
// ============================================
// 导航跳转中间页
// ============================================

require_once 'config.php';

$url = isset($_GET['url']) ? trim($_GET['url']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (empty($url)) {
    header('Location: /');
    exit;
}

// 解码URL
$url = urldecode($url);

// 验证URL安全性
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    die('无效的链接');
}

// 记录点击
if ($id > 0) {
    try {
        $db = get_db();
        $stmt = $db->prepare("UPDATE files SET clicks = clicks + 1 WHERE id = ?");
        $stmt->execute([$id]);
    } catch (Exception $e) {}
}

$settings = get_all_settings();
$site_name = $settings['site_name'] ?? '而立导航';
$site_logo = $settings['site_logo'] ?? '';
$jump_ad_enabled = $settings['jump_ad_enabled'] ?? '1';
$jump_ad_image = $settings['jump_ad_image'] ?? '';
$jump_ad_text = $settings['jump_ad_text'] ?? '正在为您跳转到目标网站，请稍候...';
$jump_ad_timeout = intval($settings['jump_ad_timeout'] ?? 5);

// 获取网站标题
$parsed = parse_url($url);
$site_title = $parsed['host'] ?? $url;
if (strpos($site_title, 'www.') === 0) {
    $site_title = substr($site_title, 4);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>跳转中 - <?php echo htmlspecialchars($site_name); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .jump-container {
            max-width: 500px;
            width: 100%;
            background: #fff;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 8px 40px rgba(0,0,0,0.08);
            text-align: center;
        }
        .jump-logo {
            font-size: 26px;
            font-weight: 700;
            color: #4CAF50;
            margin-bottom: 6px;
            text-decoration: none;
            display: inline-block;
        }
        .jump-logo img {
            max-height: 40px;
            max-width: 200px;
            vertical-align: middle;
        }
        .jump-title {
            font-size: 18px;
            color: #333;
            margin: 14px 0 6px;
        }
        .jump-url {
            color: #888;
            font-size: 13px;
            word-break: break-all;
            padding: 8px 14px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 6px 0 14px;
        }
        .jump-url a {
            color: #4CAF50;
            text-decoration: none;
        }
        .jump-url a:hover {
            text-decoration: underline;
        }
        
        .jump-ad {
            margin: 14px 0 18px;
            border-radius: 12px;
            overflow: hidden;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
        }
        .jump-ad img {
            width: 100%;
            max-height: 180px;
            object-fit: cover;
            display: block;
        }
        .jump-ad-text {
            padding: 10px 16px;
            color: #555;
            font-size: 14px;
            line-height: 1.6;
        }
        
        .progress-bar {
            width: 100%;
            height: 4px;
            background: #e9ecef;
            border-radius: 2px;
            margin: 14px 0 10px;
            overflow: hidden;
        }
        .progress-bar .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #4CAF50, #66BB6A);
            border-radius: 2px;
            transition: width 0.3s ease;
            width: 0%;
        }
        .jump-timer {
            color: #999;
            font-size: 13px;
        }
        .jump-timer strong {
            color: #4CAF50;
            font-size: 18px;
        }
        
        .jump-btn {
            display: inline-block;
            padding: 10px 32px;
            background: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            text-decoration: none;
            transition: 0.3s;
            margin-top: 8px;
        }
        .jump-btn:hover {
            background: #388E3C;
            transform: translateY(-2px);
        }
        
        .jump-footer {
            margin-top: 14px;
            color: #ccc;
            font-size: 12px;
        }
        
        @media (max-width: 480px) {
            .jump-container { padding: 24px 16px; }
            .jump-title { font-size: 16px; }
            .jump-url { font-size: 12px; }
            .jump-ad-text { font-size: 12px; padding: 8px 12px; }
        }
    </style>
</head>
<body>
    <div class="jump-container">
        <a href="/" class="jump-logo">
            <?php if (!empty($site_logo)): ?>
                <img src="<?php echo htmlspecialchars($site_logo); ?>" alt="<?php echo htmlspecialchars($site_name); ?>">
            <?php else: ?>
                <?php echo htmlspecialchars($site_name); ?>
            <?php endif; ?>
        </a>
        
        <div class="jump-title">🔗 正在跳转</div>
        <div class="jump-url">
            <a href="<?php echo htmlspecialchars($url); ?>" target="_blank">
                <?php echo htmlspecialchars($site_title); ?>
            </a>
        </div>
        
        <?php if ($jump_ad_enabled == '1' && !empty($jump_ad_image)): ?>
        <div class="jump-ad">
            <img src="<?php echo htmlspecialchars($jump_ad_image); ?>" alt="广告" onerror="this.style.display='none'">
            <?php if (!empty($jump_ad_text)): ?>
                <div class="jump-ad-text"><?php echo nl2br(htmlspecialchars($jump_ad_text)); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
        
        <div class="jump-timer">
            将在 <strong id="countdown"><?php echo $jump_ad_timeout; ?></strong> 秒后自动跳转
        </div>
        
        <a href="<?php echo htmlspecialchars($url); ?>" class="jump-btn" target="_blank">
            立即跳转
        </a>
        
        <div class="jump-footer">
            <?php echo htmlspecialchars($settings['copyright'] ?? ''); ?>
        </div>
    </div>
    
    <script>
        var timeout = <?php echo $jump_ad_timeout; ?>;
        var countdownEl = document.getElementById('countdown');
        var progressEl = document.getElementById('progressFill');
        var targetUrl = '<?php echo htmlspecialchars($url, ENT_QUOTES); ?>';
        
        var startTime = Date.now();
        var totalTime = timeout * 1000;
        
        function updateProgress() {
            var elapsed = Date.now() - startTime;
            var progress = Math.min(elapsed / totalTime * 100, 100);
            progressEl.style.width = progress + '%';
            
            var remaining = Math.max(Math.ceil((totalTime - elapsed) / 1000), 0);
            countdownEl.textContent = remaining;
            
            if (remaining > 0) {
                requestAnimationFrame(updateProgress);
            } else {
                window.location.href = targetUrl;
            }
        }
        
        setTimeout(updateProgress, 100);
    </script>
</body>
</html>