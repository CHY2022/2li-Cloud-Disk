<?php
// ============================================
// 而立导航 - 系统配置文件
// ============================================

date_default_timezone_set('Asia/Shanghai');

// ===== 站点配置 =====
define('SITE_URL', 'http://hellochen87.free.je');
define('SITE_NAME', '而立导航');
define('SITE_TYPE', 'nav');

// ===== 路径配置 =====
define('ROOT_PATH', __DIR__ . '/');
define('UPLOAD_DIR', ROOT_PATH . 'uploads/');
define('DB_FILE', ROOT_PATH . 'data/disk.db');
define('MAX_FILE_SIZE', 100 * 1024 * 1024);

// ===== 管理员账号密码 =====
define('ADMIN_USERNAME', 'admin');
define('ADMIN_PASSWORD_HASH', password_hash('admin123', PASSWORD_DEFAULT));

// ===== 二维码API =====
define('QRCODE_API', 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=');

// ===== 跳转页配置 =====
define('JUMP_TIMEOUT', 5);

// ===== 省份简称 =====
define('PROVINCES', [
    '京' => '北京', '津' => '天津', '沪' => '上海', '渝' => '重庆',
    '冀' => '河北', '晋' => '山西', '辽' => '辽宁', '吉' => '吉林',
    '黑' => '黑龙江', '苏' => '江苏', '浙' => '浙江', '皖' => '安徽',
    '闽' => '福建', '赣' => '江西', '鲁' => '山东', '豫' => '河南',
    '鄂' => '湖北', '湘' => '湖南', '粤' => '广东', '琼' => '海南',
    '川' => '四川', '贵' => '贵州', '云' => '云南', '陕' => '陕西',
    '甘' => '甘肃', '青' => '青海', '台' => '台湾',
    '蒙' => '内蒙古', '桂' => '广西', '藏' => '西藏',
    '宁' => '宁夏', '新' => '新疆', '港' => '香港', '澳' => '澳门'
]);

// ============================================
// 初始化标记
// ============================================
$GLOBALS['_db_initialized'] = false;
$GLOBALS['_db_instance'] = null;

// ============================================
// 数据库连接函数
// ============================================

function get_db() {
    global $_db_instance, $_db_initialized;
    
    if ($_db_instance === null) {
        $data_dir = dirname(DB_FILE);
        if (!is_dir($data_dir)) {
            mkdir($data_dir, 0755, true);
        }
        if (!is_dir(UPLOAD_DIR)) {
            mkdir(UPLOAD_DIR, 0755, true);
        }
        if (!is_dir(UPLOAD_DIR . 'images/')) {
            mkdir(UPLOAD_DIR . 'images/', 0755, true);
        }
        
        try {
            $_db_instance = new PDO('sqlite:' . DB_FILE);
            $_db_instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $_db_instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("数据库连接失败: " . $e->getMessage());
        }
    }
    
    if (!$_db_initialized) {
        init_database($_db_instance);
        $_db_initialized = true;
    }
    
    return $_db_instance;
}

// ============================================
// 数据库初始化函数
// ============================================

function init_database($db) {
    // 创建文件表
    $db->exec("CREATE TABLE IF NOT EXISTS files (
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
    )");
    
    // 创建设置表
    $db->exec("CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 创建举报表
    $db->exec("CREATE TABLE IF NOT EXISTS reports (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_id INTEGER NOT NULL,
        reason TEXT NOT NULL,
        detail TEXT,
        reporter_email TEXT,
        reporter_name TEXT,
        status TEXT DEFAULT 'pending',
        admin_note TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 创建分类表
    $db->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        name TEXT NOT NULL,
        icon TEXT,
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(type, name)
    )");
    
    // 创建编号序号表
    $db->exec("
        CREATE TABLE IF NOT EXISTS record_sequences (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            province TEXT NOT NULL,
            year TEXT NOT NULL,
            current_serial INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(province, year)
        )
    ");
    
    // 创建管理员表
    $db->exec("
        CREATE TABLE IF NOT EXISTS admins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // 创建邮件日志表
    $db->exec("
        CREATE TABLE IF NOT EXISTS email_logs (
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
    
    // 创建审核日志表
    $db->exec("
        CREATE TABLE IF NOT EXISTS review_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            file_id INTEGER NOT NULL,
            action TEXT NOT NULL,
            reason TEXT,
            admin_name TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // 创建索引
    $db->exec("CREATE INDEX IF NOT EXISTS idx_files_category ON files(category)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_files_status ON files(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_files_created_at ON files(created_at DESC)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_files_share_code ON files(share_code)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_categories_type ON categories(type)");
    
    // 初始化管理员账号
    $stmt = $db->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() == 0) {
        $stmt = $db->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
        $stmt->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT)]);
    }
    
    // 初始化分类数据
    $stmt = $db->query("SELECT COUNT(*) FROM categories WHERE type = 'nav_type'");
    if ($stmt->fetchColumn() == 0) {
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
        
        $stmt = $db->prepare("INSERT INTO categories (type, name, icon, sort_order) VALUES ('nav_type', ?, ?, ?)");
        foreach ($nav_types as $type) {
            $stmt->execute($type);
        }
    }
    
    // 初始化标签分类
    $stmt = $db->query("SELECT COUNT(*) FROM categories WHERE type = 'nav_tag'");
    if ($stmt->fetchColumn() == 0) {
        $tags = [
            ['热门推荐', 1],
            ['最新收录', 2],
            ['常用工具', 3],
            ['学习必备', 4],
            ['免费资源', 5]
        ];
        
        $stmt = $db->prepare("INSERT INTO categories (type, name, sort_order) VALUES ('nav_tag', ?, ?)");
        foreach ($tags as $tag) {
            $stmt->execute($tag);
        }
    }
    
    // 初始化系统设置
    $stmt = $db->query("SELECT COUNT(*) FROM settings");
    if ($stmt->fetchColumn() == 0) {
        $defaults = [
            'site_name' => '而立导航',
            'site_url' => SITE_URL,
            'site_logo' => '',
            'site_type' => 'nav',
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
    }
    
    // 初始化省份序号
    $year = date('Y');
    $stmt = $db->prepare("INSERT OR IGNORE INTO record_sequences (province, year, current_serial) VALUES (?, ?, 0)");
    foreach (array_keys(PROVINCES) as $province) {
        $stmt->execute([$province, $year]);
    }
}

// ============================================
// 设置相关函数
// ============================================

function get_setting($key, $default = '') {
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ? $result['value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function update_setting($key, $value) {
    try {
        $db = get_db();
        $stmt = $db->prepare("INSERT OR REPLACE INTO settings (key, value, updated_at) VALUES (?, ?, CURRENT_TIMESTAMP)");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

function get_all_settings() {
    try {
        $db = get_db();
        $stmt = $db->query("SELECT * FROM settings");
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['key']] = $row['value'];
        }
        return $settings;
    } catch (Exception $e) {
        return [];
    }
}

// ============================================
// 管理员验证函数
// ============================================

function verify_admin($username, $password) {
    try {
        $db = get_db();
        $stmt = $db->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute([$username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin && password_verify($password, $admin['password'])) {
            return true;
        }
    } catch (Exception $e) {
        return false;
    }
    return false;
}