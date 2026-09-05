<?php
// ============================================
// 而立导航 - 首页
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'functions.php';

$settings = get_all_settings();
$db = get_db();

// 获取参数
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$keyword = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;

// 构建查询
$where = "status = 'approved'";
$params = [];

if (!empty($category)) {
    $where .= " AND category = ?";
    $params[] = $category;
}
if (!empty($keyword)) {
    $where .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = '%' . $keyword . '%';
    $params[] = '%' . $keyword . '%';
}

// 获取总数
$count_stmt = $db->prepare("SELECT COUNT(*) FROM files WHERE $where");
$count_stmt->execute($params);
$total = $count_stmt->fetchColumn();

// 获取数据
$offset = ($page - 1) * $per_page;
$stmt = $db->prepare("SELECT * FROM files WHERE $where ORDER BY clicks DESC, created_at DESC LIMIT ? OFFSET ?");
$params[] = $per_page;
$params[] = $offset;
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_pages = ceil($total / $per_page);

// 获取分类统计
$nav_types = get_nav_types();
$nav_tags = get_nav_tags();

$category_stats = [];
foreach ($nav_types as $type) {
    $s = $db->prepare("SELECT COUNT(*) FROM files WHERE status = 'approved' AND category = ?");
    $s->execute([$type]);
    $category_stats[$type] = $s->fetchColumn();
}

// 构建查询字符串
function build_query($params) {
    $current = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($current[$key]);
        } else {
            $current[$key] = $value;
        }
    }
    return http_build_query($current);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($settings['site_name']); ?> - 网址导航</title>
    <link rel="stylesheet" href="style.css">
    <?php if (!empty($settings['baidu_analytics'])): ?>
        <script><?php echo $settings['baidu_analytics']; ?></script>
    <?php endif; ?>
    <?php if (!empty($settings['cnzz_analytics'])): ?>
        <script><?php echo $settings['cnzz_analytics']; ?></script>
    <?php endif; ?>
    <style>
        .nav-hero {
            text-align: center;
            padding: 30px 0 20px;
        }
        .nav-hero h1 {
            font-size: 30px;
            color: #222;
            margin-bottom: 4px;
        }
        .nav-hero p {
            color: #888;
            font-size: 15px;
        }
        
        .nav-search {
            max-width: 520px;
            margin: 16px auto 24px;
            display: flex;
            gap: 8px;
        }
        .nav-search input {
            flex: 1;
            padding: 10px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: 0.3s;
            outline: none;
        }
        .nav-search input:focus {
            border-color: #4CAF50;
        }
        .nav-search button {
            padding: 10px 24px;
            background: #4CAF50;
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            cursor: pointer;
            transition: 0.3s;
            white-space: nowrap;
        }
        .nav-search button:hover {
            background: #388E3C;
        }
        
        /* ===== 用户信息条 ===== */
        .user-info-bar {
            background: linear-gradient(135deg, #f0f7ff 0%, #e8f5e9 100%);
            border: 1px solid #c8e6c9;
            border-radius: 10px;
            padding: 10px 18px;
            margin: 8px 0 18px;
            font-size: 13px;
            color: #333;
            min-height: 40px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
            box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        }
        .user-info-inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px 18px;
            width: 100%;
        }
        .user-info-item {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .user-info-item .label {
            color: #888;
            font-size: 12px;
        }
        .user-info-item .value {
            font-weight: 500;
        }
        .user-info-item .value.ip {
            color: #1565c0;
            font-weight: 600;
        }
        .user-info-item .value.location {
            color: #e65100;
        }
        .user-info-item .value.browser {
            color: #6a1b9a;
        }
        .user-info-item .value.platform {
            color: #0d47a1;
        }
        .user-info-divider {
            color: #ddd;
        }
        .user-info-loading {
            color: #999;
            font-size: 12px;
        }
        .user-info-error {
            color: #f44336;
            font-size: 12px;
        }
        .user-info-refresh {
            margin-left: auto;
            cursor: pointer;
            color: #2196F3;
            font-size: 12px;
            background: none;
            border: none;
            padding: 2px 10px;
            border-radius: 4px;
            transition: 0.2s;
        }
        .user-info-refresh:hover {
            background: #e3f2fd;
        }
        
        .nav-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            justify-content: center;
            margin: 12px 0 16px;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .nav-categories a {
            padding: 4px 14px;
            border-radius: 16px;
            text-decoration: none;
            font-size: 13px;
            color: #555;
            background: #f5f5f5;
            transition: 0.2s;
            white-space: nowrap;
        }
        .nav-categories a:hover {
            background: #e8f5e9;
            color: #4CAF50;
        }
        .nav-categories a.active {
            background: #4CAF50;
            color: #fff;
        }
        .nav-categories a .count {
            font-size: 10px;
            color: #999;
            margin-left: 2px;
        }
        .nav-categories a.active .count {
            color: rgba(255,255,255,0.7);
        }
        
        .site-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
            margin: 16px 0 24px;
        }
        .site-card {
            background: #fff;
            border-radius: 10px;
            padding: 16px 14px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            border: 1px solid #f0f0f0;
            transition: 0.25s;
            text-align: center;
            text-decoration: none;
            color: #333;
            display: block;
        }
        .site-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
            border-color: #4CAF50;
        }
        .site-card .site-icon {
            font-size: 40px;
            margin-bottom: 6px;
            display: block;
        }
        .site-card .site-icon img {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            object-fit: contain;
        }
        .site-card .site-name {
            font-size: 14px;
            font-weight: 600;
            margin: 4px 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .site-card .site-desc {
            font-size: 12px;
            color: #999;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            margin: 2px 0;
        }
        .site-card .site-meta {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin-top: 6px;
            flex-wrap: wrap;
        }
        .site-card .site-meta .badge {
            font-size: 10px;
            padding: 1px 8px;
            border-radius: 10px;
            background: #f0f0f0;
            color: #666;
        }
        .site-card .site-meta .badge.category {
            background: #e3f2fd;
            color: #1565c0;
        }
        .site-card .site-actions {
            margin-top: 8px;
            display: flex;
            gap: 4px;
            justify-content: center;
        }
        .site-card .site-actions .btn {
            font-size: 10px;
            padding: 2px 10px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        .site-card .site-actions .btn-share {
            background: #FF9800;
            color: #fff;
        }
        .site-card .site-actions .btn-report {
            background: #f5f5f5;
            color: #666;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
            grid-column: 1 / -1;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 4px;
            margin: 16px 0;
            flex-wrap: wrap;
        }
        .pagination a {
            padding: 4px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #555;
            font-size: 13px;
            transition: 0.2s;
        }
        .pagination a:hover {
            background: #f0f0f0;
        }
        .pagination a.active {
            background: #4CAF50;
            color: #fff;
            border-color: #4CAF50;
        }
        
        @media (max-width: 575px) {
            .site-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }
            .site-card {
                padding: 12px 10px;
            }
            .site-card .site-icon {
                font-size: 32px;
            }
            .site-card .site-icon img {
                width: 36px;
                height: 36px;
            }
            .site-card .site-name {
                font-size: 12px;
            }
            .site-card .site-desc {
                font-size: 10px;
            }
            .nav-hero h1 {
                font-size: 22px;
            }
            .nav-categories a {
                font-size: 11px;
                padding: 3px 10px;
            }
            .nav-search {
                flex-direction: column;
            }
            .nav-search button {
                width: 100%;
            }
            .user-info-bar {
                padding: 6px 10px;
                font-size: 11px;
                min-height: 32px;
                margin: 4px 0 10px;
            }
            .user-info-inner {
                gap: 4px 8px;
            }
            .user-info-item .label {
                font-size: 10px;
            }
            .user-info-divider {
                display: none;
            }
        }
        @media (max-width: 400px) {
            .site-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            .site-card .site-icon {
                font-size: 28px;
            }
            .site-card .site-icon img {
                width: 30px;
                height: 30px;
            }
        }
        
        .toast-message {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            color: #fff;
            padding: 8px 20px;
            border-radius: 6px;
            z-index: 99999;
            font-size: 14px;
            max-width: 90%;
            text-align: center;
            animation: fadeInUp 0.3s ease;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateX(-50%) translateY(20px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 15px;
        }
        .modal.active { display: flex; }
        .modal-content {
            background: #fff;
            padding: 24px;
            border-radius: 8px;
            max-width: 450px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-content .close {
            float: right;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            line-height: 1;
        }
        .modal-content .close:hover { color: #333; }
        .modal-content h3 { margin-bottom: 14px; font-size: 18px; }
        .modal-content label { display: block; margin: 8px 0 3px; font-weight: 600; font-size: 13px; }
        .modal-content input, .modal-content select, .modal-content textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 13px;
        }
        .modal-content .btn {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 15px;
            margin-top: 10px;
        }
        .modal-content .btn-primary { background: #4CAF50; color: #fff; }
        .modal-content .btn-primary:hover { background: #388E3C; }
        .modal-content .result-success { color: #2e7d32; padding: 10px; background: #e8f5e9; border-radius: 4px; border: 1px solid #4CAF50; margin: 10px 0; }
        .modal-content .result-error { color: #c62828; padding: 10px; background: #fce4ec; border-radius: 4px; border: 1px solid #f44336; margin: 10px 0; }
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
                    <a href="/" class="active">首页</a>
                    <a href="/submit.php">提交网站</a>
                    <a href="/admin.php">后台管理</a>
                </nav>
            </div>
        </header>
        
        <div class="nav-hero">
            <h1>🔖 <?php echo htmlspecialchars($settings['site_name']); ?></h1>
            <p>精选优质网站，方便快捷访问</p>
        </div>
        
        <!-- ===== 用户信息条 ===== -->
        <div class="user-info-bar" id="user-info-bar">
            <div class="user-info-inner">
                <span class="user-info-loading">👤 正在获取您的信息...</span>
            </div>
        </div>
        
        <div class="nav-search">
            <form method="get" style="display:flex;gap:8px;width:100%;">
                <input type="text" name="keyword" placeholder="搜索网站..." value="<?php echo htmlspecialchars($keyword); ?>">
                <button type="submit">🔍 搜索</button>
            </form>
        </div>
        
        <div class="nav-categories">
            <a href="?" class="<?php echo empty($category) ? 'active' : ''; ?>">📁 全部 <span class="count">(<?php echo $total; ?>)</span></a>
            <?php foreach ($nav_types as $type): ?>
                <?php if (($category_stats[$type] ?? 0) > 0 || $category == $type): ?>
                    <a href="?<?php echo build_query(['category' => $type, 'page' => 1]); ?>" class="<?php echo $category == $type ? 'active' : ''; ?>">
                        <?php echo $type; ?> <span class="count">(<?php echo $category_stats[$type] ?? 0; ?>)</span>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <div class="site-grid">
            <?php if (empty($items)): ?>
                <div class="empty-state">📭 暂无网站，<a href="/admin.php?tab=add">去添加第一个</a></div>
            <?php else: ?>
                <?php foreach ($items as $item): 
                    $icon = $item['icon_url'] ?? '';
                    $iconHtml = '';
                    if (!empty($icon)) {
                        $iconHtml = '<img src="' . htmlspecialchars($icon) . '" alt="' . htmlspecialchars($item['name']) . '" onerror="this.outerHTML=\'🌐\'">';
                    } else {
                        $iconHtml = get_site_favicon($item['url']);
                        if (strpos($iconHtml, 'http') === 0) {
                            $iconHtml = '<img src="' . htmlspecialchars($iconHtml) . '" alt="' . htmlspecialchars($item['name']) . '" onerror="this.outerHTML=\'🌐\'">';
                        } else {
                            $iconHtml = '<span style="font-size:36px;">🌐</span>';
                        }
                    }
                ?>
                <a href="/jump.php?id=<?php echo $item['id']; ?>&url=<?php echo urlencode($item['url']); ?>" class="site-card" target="_blank">
                    <span class="site-icon"><?php echo $iconHtml; ?></span>
                    <div class="site-name"><?php echo htmlspecialchars($item['name']); ?></div>
                    <?php if (!empty($item['description'])): ?>
                        <div class="site-desc"><?php echo htmlspecialchars(mb_substr($item['description'], 0, 30)); ?></div>
                    <?php endif; ?>
                    <div class="site-meta">
                        <?php if (!empty($item['category'])): ?>
                            <span class="badge category"><?php echo htmlspecialchars($item['category']); ?></span>
                        <?php endif; ?>
                        <?php if (!empty($item['tags'])): ?>
                            <span class="badge">🏷️ <?php echo htmlspecialchars($item['tags']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="site-actions">
                        <button class="btn btn-share" onclick="event.preventDefault();shareSite(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>', '<?php echo $item['share_code'] ?? ''; ?>')">分享</button>
                        <button class="btn btn-report" onclick="event.preventDefault();showReportModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>')">举报</button>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?php echo build_query(['page' => $page - 1]); ?>">上一页</a>
            <?php else: ?>
                <span class="disabled">上一页</span>
            <?php endif; ?>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            if ($start > 1) {
                echo '<a href="?'.build_query(['page' => 1]).'">1</a>';
                if ($start > 2) echo '<span style="padding:4px 8px;color:#999;">...</span>';
            }
            for ($i = $start; $i <= $end; $i++) {
                echo '<a href="?'.build_query(['page' => $i]).'" '.($i == $page ? 'class="active"' : '').'>'.$i.'</a>';
            }
            if ($end < $total_pages) {
                if ($end < $total_pages - 1) echo '<span style="padding:4px 8px;color:#999;">...</span>';
                echo '<a href="?'.build_query(['page' => $total_pages]).'">'.$total_pages.'</a>';
            }
            ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?php echo build_query(['page' => $page + 1]); ?>">下一页</a>
            <?php else: ?>
                <span class="disabled">下一页</span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <footer class="site-footer">
            <p><?php echo $settings['copyright'] ?? ''; ?></p>
            <p><?php echo $settings['icp_number'] ?? ''; ?></p>
            <p>联系邮箱：<a href="mailto:<?php echo $settings['contact_email'] ?? ''; ?>"><?php echo $settings['contact_email'] ?? ''; ?></a></p>
        </footer>
    </div>
    
    <!-- ===== 分享弹窗 ===== -->
    <div class="modal" id="share-modal">
        <div class="modal-content" style="text-align:center;">
            <span class="close" onclick="closeModal('share-modal')">×</span>
            <h3>🔗 分享网站</h3>
            <p id="share-name" style="color:#666;margin:4px 0 12px;"></p>
            <input type="text" id="share-url-input" style="width:100%;padding:8px 12px;border:1px solid #ddd;border-radius:4px;font-size:13px;margin-bottom:10px;" readonly>
            <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;">
                <button onclick="copyShareUrl()" class="btn btn-primary" style="width:auto;padding:8px 20px;background:#2196F3;color:#fff;">📋 复制链接</button>
                <button onclick="shareQRCode()" class="btn" style="width:auto;padding:8px 20px;background:#FF9800;color:#fff;">📱 二维码</button>
            </div>
            <div id="share-qrcode-container" style="display:none;margin-top:10px;">
                <img id="share-qrcode-img" style="max-width:180px;border:1px solid #eee;border-radius:8px;padding:8px;">
            </div>
        </div>
    </div>
    
    <!-- ===== 举报弹窗 ===== -->
    <div class="modal" id="report-modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('report-modal')">×</span>
            <h3>🚨 举报网站</h3>
            <form id="report-form" onsubmit="submitReport(event)">
                <input type="hidden" name="file_id" id="report-file-id">
                <label>举报原因 <span style="color:#f44336;">*</span></label>
                <select name="reason" id="report-reason" required>
                    <option value="">请选择举报原因</option>
                    <option value="违规内容">违规内容</option>
                    <option value="色情低俗">色情低俗</option>
                    <option value="病毒木马">病毒木马</option>
                    <option value="虚假信息">虚假信息</option>
                    <option value="侵权">侵权</option>
                    <option value="其他">其他</option>
                </select>
                <label>详细描述</label>
                <textarea name="detail" id="report-detail" rows="3" placeholder="请详细描述举报原因..."></textarea>
                <label>您的邮箱</label>
                <input type="email" name="email" id="report-email" placeholder="选填">
                <label>您的昵称</label>
                <input type="text" name="name" id="report-name" placeholder="选填">
                <button type="submit" class="btn btn-primary">提交举报</button>
            </form>
            <div id="report-result" style="display:none;"></div>
        </div>
    </div>
    
    <script>
    // ========================================
    // 工具函数
    // ========================================
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }
    
    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }
    
    function showToast(message) {
        var existing = document.querySelector('.toast-message');
        if (existing) existing.remove();
        
        var toast = document.createElement('div');
        toast.className = 'toast-message';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.5s';
            setTimeout(function() { toast.remove(); }, 500);
        }, 2000);
    }
    
    function copyText(text) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function() {
                showToast('已复制：' + text);
            }).catch(function() {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    }
    
    function fallbackCopy(text) {
        var input = document.createElement('input');
        input.value = text;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
        showToast('已复制：' + text);
    }
    
    // ========================================
    // 获取用户信息
    // ========================================
    function getUserInfo() {
        var bar = document.getElementById('user-info-bar');
        if (!bar) return;
        
        var inner = bar.querySelector('.user-info-inner');
        if (!inner) return;
        
        inner.innerHTML = '<span class="user-info-loading">👤 正在获取您的信息...</span>';
        
        fetch('/api/user_info.php')
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (!data.success) {
                    inner.innerHTML = '<span class="user-info-error">⚠️ 无法获取您的信息</span>';
                    return;
                }
                
                var parts = [];
                
                if (data.ip) {
                    parts.push('<span class="user-info-item"><span class="label">📡 IP：</span><span class="value ip">' + escapeHtml(data.ip) + '</span></span>');
                }
                
                var locationText = data.location.full || '';
                if (!locationText && data.location.city) {
                    locationText = data.location.city;
                }
                if (!locationText && data.location.region) {
                    locationText = data.location.region;
                }
                if (locationText) {
                    parts.push('<span class="user-info-item"><span class="label">📍 位置：</span><span class="value location">' + escapeHtml(locationText) + '</span></span>');
                }
                
                if (data.browser && data.browser.name) {
                    var browserText = data.browser.name;
                    if (data.browser.version) {
                        browserText += ' ' + data.browser.version;
                    }
                    parts.push('<span class="user-info-item"><span class="label">🌐 浏览器：</span><span class="value browser">' + escapeHtml(browserText) + '</span></span>');
                }
                
                if (data.platform) {
                    parts.push('<span class="user-info-item"><span class="label">💻 平台：</span><span class="value platform">' + escapeHtml(data.platform) + '</span></span>');
                }
                
                if (parts.length === 0) {
                    inner.innerHTML = '<span class="user-info-error">⚠️ 无法获取您的信息</span>';
                    return;
                }
                
                inner.innerHTML = parts.join('<span class="user-info-divider">|</span>') + 
                    ' <button class="user-info-refresh" onclick="getUserInfo()">🔄</button>';
            })
            .catch(function() {
                inner.innerHTML = '<span class="user-info-error">⚠️ 获取信息失败</span>';
            });
    }
    
    // ========================================
    // 分享功能
    // ========================================
    var currentShareCode = '';
    
    function shareSite(fileId, fileName, shareCode) {
        if (shareCode && shareCode != '' && shareCode != 'null') {
            showShareModal(shareCode, fileName);
            return;
        }
        
        var days = prompt('请输入有效期（天），0为永久：', '30');
        if (days === null) return;
        var expireDays = parseInt(days);
        if (isNaN(expireDays) || expireDays < 0) {
            alert('请输入有效天数（0 表示永久）');
            return;
        }
        
        fetch('/api/share.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'create',
                file_id: fileId,
                expire_days: expireDays
            })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                showShareModal(data.share_code, fileName);
            } else {
                alert('生成分享失败：' + data.error);
            }
        });
    }
    
    function showShareModal(shareCode, fileName) {
        currentShareCode = shareCode;
        document.getElementById('share-name').textContent = fileName || '网站';
        
        var url = window.location.origin + '/share.php?code=' + shareCode;
        document.getElementById('share-url-input').value = url;
        document.getElementById('share-qrcode-img').src = '<?php echo QRCODE_API; ?>' + encodeURIComponent(url);
        document.getElementById('share-qrcode-container').style.display = 'none';
        document.getElementById('share-modal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function copyShareUrl() {
        var input = document.getElementById('share-url-input');
        copyText(input.value);
    }
    
    function shareQRCode() {
        var container = document.getElementById('share-qrcode-container');
        if (container.style.display === 'none') {
            container.style.display = 'block';
        } else {
            container.style.display = 'none';
        }
    }
    
    // ========================================
    // 举报功能
    // ========================================
    function showReportModal(fileId, fileName) {
        document.getElementById('report-file-id').value = fileId;
        document.getElementById('report-form').style.display = 'block';
        document.getElementById('report-result').style.display = 'none';
        document.getElementById('report-form').reset();
        document.getElementById('report-modal').classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function submitReport(e) {
        e.preventDefault();
        var form = document.getElementById('report-form');
        var formData = new FormData(form);
        var btn = form.querySelector('.btn');
        btn.disabled = true;
        btn.textContent = '提交中...';
        
        fetch('/api/report.php', {
            method: 'POST',
            body: formData
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            document.getElementById('report-form').style.display = 'none';
            var result = document.getElementById('report-result');
            result.style.display = 'block';
            if (data.success) {
                result.className = 'result-success';
                result.innerHTML = '✅ ' + data.message;
                setTimeout(function() { closeModal('report-modal'); }, 3000);
            } else {
                result.className = 'result-error';
                result.innerHTML = '❌ ' + data.error;
                btn.disabled = false;
                btn.textContent = '提交举报';
            }
        });
    }
    
    // ========================================
    // 键盘快捷键
    // ========================================
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.active').forEach(function(m) {
                m.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
    });
    
    document.querySelectorAll('.modal').forEach(function(m) {
        m.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    });
    
    // ========================================
    // 页面加载
    // ========================================
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(getUserInfo, 300);
    });
    </script>
</body>
</html>