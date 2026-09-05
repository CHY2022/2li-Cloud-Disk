<?php
// 开启错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

session_start();

// 如果已登录，跳转到后台
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: /admin.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    
    if (empty($username) || empty($password)) {
        $error = '请输入用户名和密码';
    } elseif (verify_admin($username, $password)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_login_time'] = time();
        header('Location: /admin.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background: #f0f2f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; }
        .login-box { background: #fff; padding: 40px 36px; border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); width: 380px; max-width: 92%; }
        .login-box .logo { text-align: center; font-size: 24px; font-weight: 700; color: #4CAF50; margin-bottom: 6px; }
        .login-box .subtitle { text-align: center; color: #999; font-size: 14px; margin-bottom: 28px; }
        .login-box .form-group { margin-bottom: 16px; }
        .login-box label { display: block; font-weight: 600; font-size: 13px; color: #444; margin-bottom: 4px; }
        .login-box input { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; transition: 0.2s; box-sizing: border-box; }
        .login-box input:focus { outline: none; border-color: #4CAF50; box-shadow: 0 0 0 3px rgba(76,175,80,0.1); }
        .login-box .btn-login { width: 100%; padding: 11px; background: #4CAF50; color: #fff; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: 0.3s; }
        .login-box .btn-login:hover { background: #388E3C; }
        .login-box .error { background: #fce4ec; color: #c62828; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px; text-align: center; }
        .login-box .hint { text-align: center; color: #bbb; font-size: 12px; margin-top: 16px; padding-top: 14px; border-top: 1px solid #eee; }
        .login-box .hint strong { color: #4CAF50; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">⚙️ 管理登录</div>
        <div class="subtitle">请输入管理员账号和密码</div>
        
        <?php if (!empty($error)): ?>
            <div class="error">❌ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="post">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" placeholder="请输入用户名" value="admin" required autofocus>
            </div>
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" placeholder="请输入密码" required>
            </div>
            <button type="submit" class="btn-login">登 录</button>
        </form>
        
        <div class="hint">
            默认账号：<strong>admin</strong> / 密码：<strong>admin123</strong>
        </div>
    </div>
</body>
</html>