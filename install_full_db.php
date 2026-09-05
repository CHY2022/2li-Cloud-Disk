<?php
// ============================================
// 完整数据库安装脚本 - 导航系统
// ============================================

require_once 'config.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>安装完整数据库</title>";
echo "<style>
    body{font-family:Arial;padding:20px;background:#f5f5f5;}
    .box{max-width:800px;margin:0 auto;background:#fff;padding:30px;border-radius:12px;box-shadow:0 2px 20px rgba(0,0,0,0.1);}
    .success{color:#4CAF50;font-weight:bold;}
    .error{color:#f44336;font-weight:bold;}
    .info{color:#2196F3;}
    .warning{color:#FF9800;}
    hr{margin:16px 0;border:1px solid #eee;}
    .step{padding:6px 0;border-bottom:1px solid #f5f5f5;}
    .count{background:#4CAF50;color:#fff;padding:2px 10px;border-radius:12px;font-size:12px;}
</style>";
echo "</head><body><div class='box'>";
echo "<h1>🗄️ 安装完整数据库 - 导航系统</h1>";
echo "<p style='color:#888;'>此脚本将创建完整的数据库结构并初始化所有数据</p>";
echo "<hr>";

// 获取数据库连接（会自动创建数据库文件）
$db = get_db();

echo "<h2>📁 创建数据表</h2>";

// ============================================
// 1. 创建 files 表
// ============================================
echo "<div class='step'>";
try {
    $db->exec("DROP TABLE IF EXISTS files");
    $db->exec("
        CREATE TABLE files (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            url TEXT NOT NULL,
            description TEXT,
            category TEXT,
            tags TEXT,
            icon_url TEXT,
            clicks INTEGER DEFAULT 0,
            share_code TEXT UNIQUE,
            share_views INTEGER DEFAULT 0,
            share_created_at DATETIME,
            share_expire_at DATETIME,
            status TEXT DEFAULT 'approved',
            record_number TEXT,
            record_year TEXT,
            record_province TEXT,
            record_serial INTEGER DEFAULT 0,
            submitted_by TEXT,
            submitted_email TEXT,
            submitted_at DATETIME,
            icp_number TEXT,
            public_security_number TEXT,
            has_friend_link INTEGER DEFAULT 0,
            province_code TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<span class='success'>✅ files 表创建成功</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 2. 创建 settings 表
// ============================================
echo "<div class='step'>";
try {
    $db->exec("DROP TABLE IF EXISTS settings");
    $db->exec("
        CREATE TABLE settings (
            key TEXT PRIMARY KEY,
            value TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<span class='success'>✅ settings 表创建成功</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 3. 创建 reports 表
// ============================================
echo "<div class='step'>";
try {
    $db->exec("DROP TABLE IF EXISTS reports");
    $db->exec("
        CREATE TABLE reports (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_id INTEGER NOT NULL,
            reason TEXT NOT NULL,
            detail TEXT,
            reporter_email TEXT,
            reporter_name TEXT,
            status TEXT DEFAULT 'pending',
            admin_note TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
        )
    ");
    echo "<span class='success'>✅ reports 表创建成功</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 4. 创建 categories 表
// ============================================
echo "<div class='step'>";
try {
    $db->exec("DROP TABLE IF EXISTS categories");
    $db->exec("
        CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            type TEXT NOT NULL,
            name TEXT NOT NULL,
            icon TEXT,
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(type, name)
        )
    ");
    echo "<span class='success'>✅ categories 表创建成功</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 5. 创建 record_sequences 表
// ============================================
echo "<div class='step'>";
try {
    $db->exec("DROP TABLE IF EXISTS record_sequences");
    $db->exec("
        CREATE TABLE record_sequences (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            province TEXT NOT NULL,
            year TEXT NOT NULL,
            current_serial INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(province, year)
        )
    ");
    echo "<span class='success'>✅ record_sequences 表创建成功</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 6. 创建 admins 表
// ============================================
echo "<div class='step'>";
try {
    $db->exec("DROP TABLE IF EXISTS admins");
    $db->exec("
        CREATE TABLE admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<span class='success'>✅ admins 表创建成功</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 7. 创建 email_logs 表
// ============================================
echo "<div class='step'>";
try {
    $db->exec("DROP TABLE IF EXISTS email_logs");
    $db->exec("
        CREATE TABLE email_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            to_email TEXT NOT NULL,
            subject TEXT NOT NULL,
            content TEXT,
            status TEXT DEFAULT 'pending',
            error TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME
        )
    ");
    echo "<span class='success'>✅ email_logs 表创建成功</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 8. 创建 review_logs 表
// ============================================
echo "<div class='step'>";
try {
    $db->exec("DROP TABLE IF EXISTS review_logs");
    $db->exec("
        CREATE TABLE review_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            reason TEXT,
            admin_name TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE
        )
    ");
    echo "<span class='success'>✅ review_logs 表创建成功</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 9. 创建索引
// ============================================
echo "<div class='step'>";
try {
    $db->exec("CREATE INDEX IF NOT EXISTS idx_files_category ON files(category)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_files_status ON files(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_files_created_at ON files(created_at DESC)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_files_share_code ON files(share_code)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_files_submitted_email ON files(submitted_email)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_files_status_created ON files(status, created_at DESC)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_type ON categories(type)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_sort ON categories(sort_order)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_reports_status ON reports(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_reports_file_id ON reports(file_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_reports_created ON reports(created_at DESC)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_review_logs_file_id ON review_logs(file_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_review_logs_created ON review_logs(created_at DESC)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_email_logs_status ON email_logs(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_email_logs_created ON email_logs(created_at DESC)");
    echo "<span class='success'>✅ 所有索引创建成功</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

echo "<hr>";
echo "<h2>📊 初始化数据</h2>";

// ============================================
// 10. 初始化管理员账号
// ============================================
echo "<div class='step'>";
try {
    $stmt = $db->prepare("INSERT OR IGNORE INTO admins (username, password) VALUES (?, ?)");
    $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
    echo "<span class='success'>✅ 管理员账号创建成功 (admin / admin123)</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 11. 初始化分类数据
// ============================================
echo "<div class='step'>";
try {
    $nav_types = [
        ['资讯信息', '📰', 1],
        ['软件工具', '🔧', 2],
        ['网络工具', '🌐', 3],
        ['学习资源', '📚', 4],
        ['娱乐休闲', '🎮', 5],
        ['设计素材', '🎨', 6],
        ['开发资源', '💻', 7],
        ['其他', '📁', 8]
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO categories (type, name, icon, sort_order) VALUES ('nav_type', ?, ?, ?)");
    foreach ($nav_types as $type) {
        $stmt->execute($type);
    }
    echo "<span class='success'>✅ 分类数据初始化完成 (" . count($nav_types) . " 条)</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 12. 初始化标签数据
// ============================================
echo "<div class='step'>";
try {
    $tags = [
        ['热门推荐', 1],
        ['最新收录', 2],
        ['常用工具', 3],
        ['学习必备', 4],
        ['免费资源', 5]
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO categories (type, name, sort_order) VALUES ('nav_tag', ?, ?)");
    foreach ($tags as $tag) {
        $stmt->execute($tag);
    }
    echo "<span class='success'>✅ 标签数据初始化完成 (" . count($tags) . " 条)</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 13. 初始化系统设置
// ============================================
echo "<div class='step'>";
try {
    $defaults = [
        'site_name' => '而立导航',
        'site_url' => 'http://pan.2li.xyz',
        'site_logo' => '',
        'icp_number' => '鲁ICP备20260001号',
        'contact_email' => 'admin@2li.xyz',
        'copyright' => '2026 © 而立导航',
        'record_prefix' => '网AN',
        'record_enabled' => '1',
        'jump_ad_enabled' => '1',
        'jump_ad_image' => '',
        'jump_ad_text' => '正在为您跳转到目标网站，请稍候...',
        'jump_ad_timeout' => '5',
        'baidu_analytics' => '',
        'cnzz_analytics' => '',
        'smtp_host' => 'smtp.qq.com',
        'smtp_port' => '465',
        'smtp_secure' => 'ssl',
        'smtp_user' => '',
        'smtp_pass' => '',
        'smtp_from' => '',
        'smtp_from_name' => '而立导航'
    ];
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)");
    foreach ($defaults as $key => $value) {
        $stmt->execute([$key, $value]);
    }
    echo "<span class='success'>✅ 系统设置初始化完成 (" . count($defaults) . " 条)</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 14. 初始化省份序号
// ============================================
echo "<div class='step'>";
try {
    $provinces = ['京','津','沪','渝','冀','晋','辽','吉','黑','苏','浙','皖','闽','赣','鲁','豫','鄂','湘','粤','琼','川','贵','云','陕','甘','青','台','蒙','桂','藏','宁','新','港','澳'];
    $year = date('Y');
    $stmt = $db->prepare("INSERT OR IGNORE INTO record_sequences (province, year, current_serial) VALUES (?, ?, 0)");
    foreach ($provinces as $province) {
        $stmt->execute([$province, $year]);
    }
    echo "<span class='success'>✅ 省份序号初始化完成 (" . count($provinces) . " 个省份)</span>";
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

// ============================================
// 15. 添加示例数据
// ============================================
echo "<div class='step'>";
try {
    // 检查是否已有数据
    $stmt = $db->query("SELECT COUNT(*) FROM files");
    $count = $stmt->fetchColumn();
    
    if ($count == 0) {
        // 示例网站数据
        $sample_sites = [
            [
                'name' => '百度',
                'url' => 'https://www.baidu.com',
                'category' => '网络工具',
                'tags' => '热门推荐',
                'description' => '全球最大的中文搜索引擎',
                'icon_url' => 'https://www.baidu.com/favicon.ico',
                'record_number' => '网AN鲁备20260001号',
                'record_year' => '2026',
                'record_province' => '鲁',
                'record_serial' => 1,
                'status' => 'approved',
                'clicks' => 1250,
                'province_code' => '京'
            ],
            [
                'name' => 'Google',
                'url' => 'https://www.google.com',
                'category' => '网络工具',
                'tags' => '热门推荐',
                'description' => '全球最大的搜索引擎',
                'icon_url' => 'https://www.google.com/favicon.ico',
                'record_number' => '网AN鲁备20260002号',
                'record_year' => '2026',
                'record_province' => '鲁',
                'record_serial' => 2,
                'status' => 'approved',
                'clicks' => 980,
                'province_code' => '京'
            ],
            [
                'name' => 'GitHub',
                'url' => 'https://github.com',
                'category' => '开发资源',
                'tags' => '常用工具',
                'description' => '全球最大的代码托管平台',
                'icon_url' => 'https://github.com/favicon.ico',
                'record_number' => '网AN鲁备20260003号',
                'record_year' => '2026',
                'record_province' => '鲁',
                'record_serial' => 3,
                'status' => 'approved',
                'clicks' => 760,
                'province_code' => '京'
            ],
            [
                'name' => 'B站',
                'url' => 'https://www.bilibili.com',
                'category' => '娱乐休闲',
                'tags' => '热门推荐',
                'description' => '中国最大的年轻人文化社区',
                'icon_url' => 'https://www.bilibili.com/favicon.ico',
                'record_number' => '网AN鲁备20260004号',
                'record_year' => '2026',
                'record_province' => '鲁',
                'record_serial' => 4,
                'status' => 'approved',
                'clicks' => 2100,
                'province_code' => '沪'
            ],
            [
                'name' => '知乎',
                'url' => 'https://www.zhihu.com',
                'category' => '资讯信息',
                'tags' => '学习必备',
                'description' => '中文互联网最大的知识分享平台',
                'icon_url' => 'https://www.zhihu.com/favicon.ico',
                'record_number' => '网AN鲁备20260005号',
                'record_year' => '2026',
                'record_province' => '鲁',
                'record_serial' => 5,
                'status' => 'approved',
                'clicks' => 560,
                'province_code' => '京'
            ],
            [
                'name' => 'V2EX',
                'url' => 'https://www.v2ex.com',
                'category' => '开发资源',
                'tags' => '常用工具',
                'description' => '创意工作者的社区',
                'icon_url' => 'https://www.v2ex.com/favicon.ico',
                'record_number' => '网AN鲁备20260006号',
                'record_year' => '2026',
                'record_province' => '鲁',
                'record_serial' => 6,
                'status' => 'approved',
                'clicks' => 430,
                'province_code' => '京'
            ],
            [
                'name' => 'CSDN',
                'url' => 'https://www.csdn.net',
                'category' => '开发资源',
                'tags' => '学习必备',
                'description' => '中文IT技术社区',
                'icon_url' => 'https://www.csdn.net/favicon.ico',
                'record_number' => '网AN鲁备20260007号',
                'record_year' => '2026',
                'record_province' => '鲁',
                'record_serial' => 7,
                'status' => 'approved',
                'clicks' => 320,
                'province_code' => '京'
            ],
            [
                'name' => '豆瓣',
                'url' => 'https://www.douban.com',
                'category' => '娱乐休闲',
                'tags' => '热门推荐',
                'description' => '中国最大的书影音社区',
                'icon_url' => 'https://www.douban.com/favicon.ico',
                'record_number' => '网AN鲁备20260008号',
                'record_year' => '2026',
                'record_province' => '鲁',
                'record_serial' => 8,
                'status' => 'approved',
                'clicks' => 280,
                'province_code' => '京'
            ]
        ];
        
        $stmt = $db->prepare("
            INSERT INTO files (
                name, url, category, tags, description, icon_url,
                record_number, record_year, record_province, record_serial,
                status, clicks, province_code, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        
        foreach ($sample_sites as $site) {
            $stmt->execute([
                $site['name'], $site['url'], $site['category'], $site['tags'],
                $site['description'], $site['icon_url'],
                $site['record_number'], $site['record_year'], $site['record_province'],
                $site['record_serial'], $site['status'], $site['clicks'],
                $site['province_code']
            ]);
        }
        
        echo "<span class='success'>✅ 示例数据添加完成 (" . count($sample_sites) . " 条)</span>";
    } else {
        echo "<span class='info'>ℹ️ 已有 {$count} 条数据，跳过示例数据</span>";
    }
} catch (PDOException $e) {
    echo "<span class='error'>❌ " . $e->getMessage() . "</span>";
}
echo "</div>";

echo "<hr>";
echo "<h2>📊 安装完成统计</h2>";

// 显示统计信息
$tables = [
    'files' => '网站',
    'categories' => '分类',
    'admins' => '管理员',
    'settings' => '系统设置',
    'record_sequences' => '编号序号',
    'reports' => '举报',
    'email_logs' => '邮件日志',
    'review_logs' => '审核日志'
];

echo "<table style='width:100%;border-collapse:collapse;margin:10px 0;'>";
echo "<tr style='background:#f5f5f5;'><th style='padding:8px;text-align:left;border:1px solid #ddd;'>表名</th>";
echo "<th style='padding:8px;text-align:center;border:1px solid #ddd;'>记录数</th></tr>";

foreach ($tables as $table => $label) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "<tr><td style='padding:6px 10px;border:1px solid #ddd;'>$label ($table)</td>";
        echo "<td style='padding:6px 10px;border:1px solid #ddd;text-align:center;'><span class='count'>$count</span></td></tr>";
    } catch (Exception $e) {
        echo "<tr><td style='padding:6px 10px;border:1px solid #ddd;'>$label ($table)</td>";
        echo "<td style='padding:6px 10px;border:1px solid #ddd;text-align:center;color:#f44336;'>-</td></tr>";
    }
}
echo "</table>";

echo "<hr>";
echo "<div style='text-align:center;padding:10px 0;'>";
echo "<h2 style='color:#4CAF50;'>🎉 完整数据库安装成功！</h2>";
echo "<p style='color:#888;'>数据库文件位置：<code>" . DB_FILE . "</code></p>";
echo "<div style='margin-top:15px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;'>";
echo "<a href='/admin.php' style='display:inline-block;padding:10px 30px;background:#4CAF50;color:#fff;text-decoration:none;border-radius:6px;'>进入后台管理</a>";
echo "<a href='/' style='display:inline-block;padding:10px 30px;background:#2196F3;color:#fff;text-decoration:none;border-radius:6px;'>返回首页</a>";
echo "<a href='/submit.php' style='display:inline-block;padding:10px 30px;background:#FF9800;color:#fff;text-decoration:none;border-radius:6px;'>提交网站</a>";
echo "</div>";
echo "<p style='margin-top:15px;color:#999;font-size:13px;'>管理员账号：<strong>admin</strong> / 密码：<strong>admin123</strong></p>";
echo "</div>";
echo "</div></body></html>";
?>