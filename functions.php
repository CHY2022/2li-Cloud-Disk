<?php
// ============================================
// 而立导航 - 函数库
// ============================================

require_once 'config.php';

// ============================================
// 分类相关函数
// ============================================

if (!function_exists('get_categories')) {
    function get_categories($type) {
        try {
            $db = get_db();
            $stmt = $db->prepare("SELECT * FROM categories WHERE type = ? ORDER BY sort_order ASC, name ASC");
            $stmt->execute([$type]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}

if (!function_exists('get_category_names')) {
    function get_category_names($type) {
        $items = get_categories($type);
        return array_column($items, 'name');
    }
}

if (!function_exists('get_nav_types')) {
    function get_nav_types() {
        return get_category_names('nav_type');
    }
}

if (!function_exists('get_nav_tags')) {
    function get_nav_tags() {
        return get_category_names('nav_tag');
    }
}

// ============================================
// 编号相关函数
// ============================================

if (!function_exists('get_next_serial')) {
    function get_next_serial($province, $year) {
        $db = get_db();
        
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("
                INSERT OR IGNORE INTO record_sequences (province, year, current_serial) 
                VALUES (?, ?, 0)
            ");
            $stmt->execute([$province, $year]);
            
            $stmt = $db->prepare("
                UPDATE record_sequences 
                SET current_serial = current_serial + 1, updated_at = CURRENT_TIMESTAMP
                WHERE province = ? AND year = ?
            ");
            $stmt->execute([$province, $year]);
            
            $stmt = $db->prepare("SELECT current_serial FROM record_sequences WHERE province = ? AND year = ?");
            $stmt->execute([$province, $year]);
            $serial = $stmt->fetchColumn();
            
            $db->commit();
            return intval($serial);
        } catch (Exception $e) {
            $db->rollBack();
            $stmt = $db->prepare("SELECT current_serial FROM record_sequences WHERE province = ? AND year = ?");
            $stmt->execute([$province, $year]);
            $serial = intval($stmt->fetchColumn()) + 1;
            
            $stmt = $db->prepare("UPDATE record_sequences SET current_serial = ?, updated_at = CURRENT_TIMESTAMP WHERE province = ? AND year = ?");
            $stmt->execute([$serial, $province, $year]);
            return $serial;
        }
    }
}

if (!function_exists('generate_record_number')) {
    function generate_record_number($province = null) {
        $prefix = get_setting('record_prefix', '网AN');
        $year = date('Y');
        
        if (empty($province)) {
            $province = '鲁';
        }
        
        $serial = get_next_serial($province, $year);
        return $prefix . $province . '备' . $year . str_pad($serial, 4, '0', STR_PAD_LEFT) . '号';
    }
}

if (!function_exists('get_record_number_html')) {
    function get_record_number_html($file) {
        if (empty($file['record_number'])) {
            return '<span style="color:#999;font-size:11px;">未备案</span>';
        }
        
        $enabled = get_setting('record_enabled', '1');
        if ($enabled != '1') {
            return '';
        }
        
        return '<span class="record-number" style="font-size:11px;color:#888;background:#f5f5f5;padding:1px 8px;border-radius:10px;border:1px solid #e0e0e0;">' 
            . htmlspecialchars($file['record_number']) . '</span>';
    }
}

// ============================================
// 获取网站图标
// ============================================

if (!function_exists('get_site_favicon')) {
    function get_site_favicon($url) {
        static $cache = [];
        
        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            return '🌐';
        }
        
        $host = $parsed['host'];
        if (isset($cache[$host])) {
            return $cache[$host];
        }
        
        if (!function_exists('curl_init')) {
            $fallback = "https://www.google.com/s2/favicons?domain=$host&sz=64";
            $cache[$host] = $fallback;
            return $fallback;
        }
        
        $protocol = isset($parsed['scheme']) ? $parsed['scheme'] : 'https';
        $favicon_paths = ['/favicon.ico', '/favicon.png'];
        
        foreach ($favicon_paths as $path) {
            $test_url = "$protocol://$host$path";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $test_url);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code >= 200 && $http_code < 400) {
                $cache[$host] = $test_url;
                return $test_url;
            }
        }
        
        $fallback = "https://www.google.com/s2/favicons?domain=$host&sz=64";
        $cache[$host] = $fallback;
        return $fallback;
    }
}

// ============================================
// 格式化函数
// ============================================

if (!function_exists('format_date')) {
    function format_date($date) {
        if (empty($date)) return '-';
        return date('Y-m-d', strtotime($date));
    }
}

if (!function_exists('safe_input')) {
    function safe_input($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('generate_share_code')) {
    function generate_share_code() {
        $db = get_db();
        do {
            $code = strtoupper(substr(md5(uniqid() . mt_rand()), 0, 8));
            $stmt = $db->prepare("SELECT id FROM files WHERE share_code = ?");
            $stmt->execute([$code]);
        } while ($stmt->fetch());
        return $code;
    }
}

if (!function_exists('get_share_url')) {
    function get_share_url($share_code) {
        return get_setting('site_url', SITE_URL) . '/share.php?code=' . $share_code;
    }
}

// ============================================
// 邮件发送函数
// ============================================

if (!function_exists('send_email')) {
    function send_email($to, $subject, $body) {
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            $autoload_paths = [
                __DIR__ . '/vendor/autoload.php',
                __DIR__ . '/../vendor/autoload.php'
            ];
            foreach ($autoload_paths as $path) {
                if (file_exists($path)) {
                    require_once $path;
                    break;
                }
            }
            if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                error_log("PHPMailer not found");
                return false;
            }
        }
        
        $host = get_setting('smtp_host', 'smtp.qq.com');
        $port = get_setting('smtp_port', '465');
        $secure = get_setting('smtp_secure', 'ssl');
        $user = get_setting('smtp_user', '');
        $pass = get_setting('smtp_pass', '');
        $from = get_setting('smtp_from', '');
        $from_name = get_setting('smtp_from_name', '而立导航');
        
        if (empty($user) || empty($pass) || empty($from)) {
            error_log("SMTP未配置");
            return false;
        }
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->SMTPSecure = $secure;
            $mail->Port = $port;
            $mail->CharSet = 'UTF-8';
            $mail->setFrom($from, $from_name);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            return $mail->send();
        } catch (Exception $e) {
            error_log("邮件发送失败: " . $mail->ErrorInfo);
            return false;
        }
    }
}

if (!function_exists('send_email_with_log')) {
    function send_email_with_log($to, $subject, $body) {
        $db = get_db();
        
        $stmt = $db->prepare("
            INSERT INTO email_logs (to_email, subject, content, status, created_at)
            VALUES (?, ?, ?, 'pending', CURRENT_TIMESTAMP)
        ");
        $stmt->execute([$to, $subject, $body]);
        $log_id = $db->lastInsertId();
        
        $result = send_email($to, $subject, $body);
        
        if ($result) {
            $stmt = $db->prepare("UPDATE email_logs SET status = 'sent', sent_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$log_id]);
        } else {
            $stmt = $db->prepare("UPDATE email_logs SET status = 'failed', error = ? WHERE id = ?");
            $stmt->execute(['邮件发送失败', $log_id]);
        }
        
        return $result;
    }
}

if (!function_exists('send_admin_notification')) {
    function send_admin_notification($file_id, $name, $url, $email) {
        $site_name = get_setting('site_name', '导航站');
        $admin_email = get_setting('contact_email', 'admin@localhost');
        
        $body = "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e9ecef; border-radius: 12px; background: #ffffff;'>
            <div style='text-align: center; padding-bottom: 20px; border-bottom: 2px solid #FF9800; margin-bottom: 25px;'>
                <h2 style='color: #FF9800; margin: 0; font-size: 22px;'>📝 新的网站提交待审核</h2>
            </div>
            <div style='padding: 0 10px;'>
                <p style='font-size: 15px; color: #333;'>有新的网站提交申请，请登录后台审核：</p>
                <div style='background: #f8f9fa; padding: 16px 20px; border-radius: 8px; margin: 16px 0; border-left: 4px solid #FF9800;'>
                    <p style='margin: 4px 0;'><strong>📌 网站名称：</strong>{$name}</p>
                    <p style='margin: 4px 0;'><strong>🔗 网站地址：</strong><a href='{$url}' target='_blank'>{$url}</a></p>
                    <p style='margin: 4px 0;'><strong>📧 提交者邮箱：</strong>{$email}</p>
                    <p style='margin: 4px 0;'><strong>🆔 记录ID：</strong>{$file_id}</p>
                </div>
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='" . get_setting('site_url', SITE_URL) . "/admin.php?tab=pending' style='display: inline-block; padding: 10px 30px; background: #4CAF50; color: #fff; text-decoration: none; border-radius: 6px; font-size: 15px;'>
                        📋 前往审核
                    </a>
                </div>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #999; text-align: center; margin: 0;'>
                    此邮件由系统自动发送，请勿回复。
                </p>
            </div>
        </div>
        ";
        
        return send_email_with_log($admin_email, '【' . $site_name . '】新的网站提交待审核', $body);
    }
}

if (!function_exists('build_approve_email')) {
    function build_approve_email($file, $record_number) {
        $site_name = get_setting('site_name', '导航站');
        $site_url = get_setting('site_url', SITE_URL);
        $site_logo = get_setting('site_logo', '');
        
        $logo_html = '';
        if (!empty($site_logo)) {
            $logo_html = '<img src="' . htmlspecialchars($site_logo) . '" alt="' . htmlspecialchars($site_name) . '" style="max-height:40px;">';
        }
        
        return "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e9ecef; border-radius: 12px; background: #ffffff;'>
            <div style='text-align: center; padding-bottom: 20px; border-bottom: 2px solid #4CAF50; margin-bottom: 25px;'>
                {$logo_html}
                <h2 style='color: #4CAF50; margin: 8px 0 0; font-size: 24px;'>✅ 网站审核通过</h2>
            </div>
            <div style='padding: 0 10px;'>
                <p style='font-size: 16px; color: #333;'>您好，<strong>" . htmlspecialchars($file['submitted_by'] ?: '用户') . "</strong>：</p>
                <p style='font-size: 15px; color: #555; line-height: 1.8;'>
                    恭喜您！您提交的网站 <strong>" . htmlspecialchars($file['name']) . "</strong> 已通过审核，
                    现已正式收录到 <strong>" . htmlspecialchars($site_name) . "</strong> 导航站中。
                </p>
                <div style='background: #e8f5e9; padding: 16px 20px; border-radius: 8px; margin: 16px 0; border-left: 4px solid #4CAF50;'>
                    <p style='margin: 4px 0;'><strong>📌 网站名称：</strong>" . htmlspecialchars($file['name']) . "</p>
                    <p style='margin: 4px 0;'><strong>🔗 网站地址：</strong><a href='" . htmlspecialchars($file['url']) . "' target='_blank'>" . htmlspecialchars($file['url']) . "</a></p>
                    <p style='margin: 4px 0;'><strong>📂 分类：</strong>" . htmlspecialchars($file['category'] ?: '未分类') . "</p>
                    <p style='margin: 4px 0;'><strong>📋 备案编号：</strong><span style='background: #fff;padding:2px 12px;border-radius:4px;border:1px solid #4CAF50;font-weight:bold;font-size:16px;'>" . htmlspecialchars($record_number) . "</span></p>
                </div>
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='" . htmlspecialchars($site_url) . "' style='display: inline-block; padding: 10px 30px; background: #4CAF50; color: #fff; text-decoration: none; border-radius: 6px; font-size: 15px;'>
                        🏠 访问导航站
                    </a>
                </div>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #999; text-align: center; margin: 0;'>
                    此邮件由系统自动发送，请勿回复。<br>
                    如有疑问，请联系管理员。
                </p>
            </div>
        </div>
        ";
    }
}

if (!function_exists('build_reject_email')) {
    function build_reject_email($file, $reason) {
        $site_name = get_setting('site_name', '导航站');
        
        return "
        <div style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e9ecef; border-radius: 12px; background: #ffffff;'>
            <div style='text-align: center; padding-bottom: 20px; border-bottom: 2px solid #f44336; margin-bottom: 25px;'>
                <h2 style='color: #f44336; margin: 0; font-size: 24px;'>❌ 网站审核未通过</h2>
            </div>
            <div style='padding: 0 10px;'>
                <p style='font-size: 16px; color: #333;'>您好，<strong>" . htmlspecialchars($file['submitted_by'] ?: '用户') . "</strong>：</p>
                <p style='font-size: 15px; color: #555; line-height: 1.8;'>
                    很遗憾，您提交的网站 <strong>" . htmlspecialchars($file['name']) . "</strong> 未通过审核。
                </p>
                <div style='background: #fce4ec; padding: 16px 20px; border-radius: 8px; margin: 16px 0; border-left: 4px solid #f44336;'>
                    <p style='margin: 4px 0; font-weight: 600; color: #c62828;'>📌 拒绝原因：</p>
                    <p style='margin: 4px 0; color: #555; font-size: 15px;'>" . nl2br(htmlspecialchars($reason)) . "</p>
                </div>
                <p style='font-size: 14px; color: #666;'>您可以修改后重新提交申请。</p>
                <div style='text-align: center; margin: 20px 0;'>
                    <a href='" . get_setting('site_url', SITE_URL) . "/submit.php' style='display: inline-block; padding: 10px 30px; background: #2196F3; color: #fff; text-decoration: none; border-radius: 6px; font-size: 15px;'>
                        📤 重新提交
                    </a>
                </div>
                <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                <p style='font-size: 12px; color: #999; text-align: center; margin: 0;'>
                    此邮件由系统自动发送，请勿回复。<br>
                    如有疑问，请联系管理员。
                </p>
            </div>
        </div>
        ";
    }
}

// ============================================
// 上传文件函数
// ============================================

if (!function_exists('upload_file')) {
    function upload_file($file, $target_dir = '') {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => '上传失败：' . $file['error']];
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            return ['success' => false, 'error' => '文件超过最大限制'];
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowed_exts)) {
            return ['success' => false, 'error' => '只支持图片格式: jpg, png, gif, webp'];
        }
        
        $filename = date('YmdHis') . '_' . uniqid() . '.' . $ext;
        $path = UPLOAD_DIR . ($target_dir ? $target_dir . '/' : '') . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $path)) {
            return ['success' => true, 'path' => $filename, 'size' => $file['size']];
        }
        return ['success' => false, 'error' => '保存文件失败'];
    }
}