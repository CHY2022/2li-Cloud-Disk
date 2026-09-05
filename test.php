<?php
// 开启所有错误显示
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "PHP 运行正常<br>";

// 检查扩展
echo "PDO: " . (extension_loaded('pdo') ? '✅ 已加载' : '❌ 未加载') . "<br>";
echo "PDO_SQLITE: " . (extension_loaded('pdo_sqlite') ? '✅ 已加载' : '❌ 未加载') . "<br>";
echo "SQLITE3: " . (extension_loaded('sqlite3') ? '✅ 已加载' : '❌ 未加载') . "<br>";
echo "CURL: " . (extension_loaded('curl') ? '✅ 已加载' : '❌ 未加载') . "<br>";

// 检查目录权限
echo "<br>目录权限检查：<br>";
$dirs = ['data', 'uploads', 'uploads/images'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (file_exists($path)) {
        echo "$dir: " . (is_writable($path) ? '✅ 可写' : '❌ 不可写') . "<br>";
    } else {
        echo "$dir: ❌ 不存在<br>";
    }
}

phpinfo();
?>