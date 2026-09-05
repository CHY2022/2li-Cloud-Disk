<?php
// ============================================
// 获取用户信息 API
// ============================================

header('Content-Type: application/json');

function getUserIP() {
    $ip = '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    if (strpos($ip, ',') !== false) {
        $ips = explode(',', $ip);
        $ip = trim($ips[0]);
    }
    return $ip;
}

function getIPLocation($ip) {
    if (in_array($ip, ['127.0.0.1', '::1', 'localhost']) || strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
        return ['country' => '内网', 'region' => '本地', 'city' => '本地', 'isp' => '', 'full' => '内网IP'];
    }
    
    // 使用多个API，提高成功率
    $apis = [
        "http://ip-api.com/json/{$ip}?lang=zh-CN",
        "https://ipapi.co/{$ip}/json/",
        "https://ip-api.io/json/{$ip}"
    ];
    
    foreach ($apis as $url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            $data = json_decode($response, true);
            if ($data) {
                // ip-api.com 格式
                if (isset($data['country']) && isset($data['regionName'])) {
                    $region = $data['regionName'] ?? '';
                    $city = $data['city'] ?? '';
                    $full = trim($region . ' ' . $city);
                    if (empty($full)) $full = $city ?: $region;
                    return [
                        'country' => $data['country'] ?? '',
                        'region' => $region,
                        'city' => $city,
                        'isp' => $data['isp'] ?? '',
                        'full' => $full ?: '未知地区'
                    ];
                }
                // ipapi.co 格式
                if (isset($data['country_name']) && isset($data['region'])) {
                    $region = $data['region'] ?? '';
                    $city = $data['city'] ?? '';
                    $full = trim($region . ' ' . $city);
                    if (empty($full)) $full = $city ?: $region;
                    return [
                        'country' => $data['country_name'] ?? '',
                        'region' => $region,
                        'city' => $city,
                        'isp' => $data['org'] ?? '',
                        'full' => $full ?: '未知地区'
                    ];
                }
            }
        }
    }
    return ['country' => '', 'region' => '', 'city' => '', 'isp' => '', 'full' => '未知地区'];
}

function getBrowserInfo() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $browser = '未知';
    $version = '';
    
    // 微信浏览器
    if (strpos($userAgent, 'MicroMessenger') !== false) {
        $browser = '微信浏览器';
        if (preg_match('/MicroMessenger\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // QQ浏览器
    if (strpos($userAgent, 'QQBrowser') !== false) {
        $browser = 'QQ浏览器';
        if (preg_match('/QQBrowser\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // 搜狗浏览器
    if (strpos($userAgent, 'MetaSr') !== false || strpos($userAgent, 'Sogou') !== false) {
        $browser = '搜狗浏览器';
        if (preg_match('/SogouMobileBrowser\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        } elseif (preg_match('/MetaSr\s([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // 猎豹浏览器
    if (strpos($userAgent, 'LBBROWSER') !== false) {
        $browser = '猎豹浏览器';
        if (preg_match('/LBBROWSER\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // UC浏览器
    if (strpos($userAgent, 'UCBrowser') !== false || strpos($userAgent, 'UCWEB') !== false) {
        $browser = 'UC浏览器';
        if (preg_match('/UCBrowser\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        } elseif (preg_match('/UCWEB([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // 夸克浏览器
    if (strpos($userAgent, 'Quark') !== false) {
        $browser = '夸克浏览器';
        if (preg_match('/Quark\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // 360浏览器
    if (strpos($userAgent, '360EE') !== false || strpos($userAgent, '360SE') !== false) {
        $browser = '360浏览器';
        if (strpos($userAgent, '360EE') !== false) {
            $version = '极速版';
        } elseif (strpos($userAgent, '360SE') !== false) {
            $version = '安全版';
        }
        if (preg_match('/Chrome\/([\d.]+)/', $userAgent, $matches)) {
            $version .= ' (Chrome内核 ' . $matches[1] . ')';
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // 百度浏览器
    if (strpos($userAgent, 'baidubrowser') !== false) {
        $browser = '百度浏览器';
        if (preg_match('/baidubrowser\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // Edge浏览器
    if (strpos($userAgent, 'Edg/') !== false) {
        $browser = 'Edge浏览器';
        if (preg_match('/Edg\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // Opera浏览器
    if (strpos($userAgent, 'OPR/') !== false || strpos($userAgent, 'Opera') !== false) {
        $browser = 'Opera浏览器';
        if (preg_match('/OPR\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        } elseif (preg_match('/Opera\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // Safari浏览器
    if (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
        $browser = 'Safari浏览器';
        if (preg_match('/Version\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // Chrome浏览器
    if (strpos($userAgent, 'Chrome') !== false) {
        $browser = 'Chrome浏览器';
        if (preg_match('/Chrome\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    // Firefox浏览器
    if (strpos($userAgent, 'Firefox') !== false) {
        $browser = 'Firefox浏览器';
        if (preg_match('/Firefox\/([\d.]+)/', $userAgent, $matches)) {
            $version = $matches[1];
        }
        return ['name' => $browser, 'version' => $version];
    }
    
    return ['name' => $browser, 'version' => $version];
}

function getPlatformInfo() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $platform = '未知';
    
    // Windows
    if (strpos($userAgent, 'Windows NT 10.0') !== false) {
        $platform = 'Windows 10/11';
    } elseif (strpos($userAgent, 'Windows NT 6.3') !== false) {
        $platform = 'Windows 8.1';
    } elseif (strpos($userAgent, 'Windows NT 6.2') !== false) {
        $platform = 'Windows 8';
    } elseif (strpos($userAgent, 'Windows NT 6.1') !== false) {
        $platform = 'Windows 7';
    } elseif (strpos($userAgent, 'Windows NT 5.1') !== false) {
        $platform = 'Windows XP';
    } elseif (strpos($userAgent, 'Windows NT 6.0') !== false) {
        $platform = 'Windows Vista';
    } elseif (strpos($userAgent, 'Windows') !== false) {
        $platform = 'Windows';
    } 
    // macOS
    elseif (strpos($userAgent, 'Mac OS X') !== false || strpos($userAgent, 'Macintosh') !== false) {
        $platform = 'macOS';
        if (preg_match('/Mac OS X ([0-9_]+)/', $userAgent, $matches)) {
            $version = str_replace('_', '.', $matches[1]);
            $platform .= ' ' . $version;
        }
    } 
    // iOS
    elseif (strpos($userAgent, 'iPhone') !== false) {
        $platform = 'iOS (iPhone)';
        if (preg_match('/OS ([\d_]+)/', $userAgent, $matches)) {
            $platform .= ' ' . str_replace('_', '.', $matches[1]);
        }
    } elseif (strpos($userAgent, 'iPad') !== false) {
        $platform = 'iOS (iPad)';
        if (preg_match('/OS ([\d_]+)/', $userAgent, $matches)) {
            $platform .= ' ' . str_replace('_', '.', $matches[1]);
        }
    } 
    // Android
    elseif (strpos($userAgent, 'Android') !== false) {
        $platform = 'Android';
        if (preg_match('/Android ([\d.]+)/', $userAgent, $matches)) {
            $platform .= ' ' . $matches[1];
        }
    } 
    // Linux发行版
    elseif (strpos($userAgent, 'Linux') !== false) {
        if (strpos($userAgent, 'Ubuntu') !== false) {
            $platform = 'Linux (Ubuntu)';
        } elseif (strpos($userAgent, 'Debian') !== false) {
            $platform = 'Linux (Debian)';
        } elseif (strpos($userAgent, 'Fedora') !== false) {
            $platform = 'Linux (Fedora)';
        } elseif (strpos($userAgent, 'CentOS') !== false) {
            $platform = 'Linux (CentOS)';
        } elseif (strpos($userAgent, 'Arch') !== false) {
            $platform = 'Linux (Arch)';
        } elseif (strpos($userAgent, 'openSUSE') !== false) {
            $platform = 'Linux (openSUSE)';
        } elseif (strpos($userAgent, 'Mint') !== false) {
            $platform = 'Linux (Mint)';
        } else {
            $platform = 'Linux';
        }
    } 
    // Chrome OS
    elseif (strpos($userAgent, 'Chrome OS') !== false) {
        $platform = 'Chrome OS';
    }
    
    return $platform;
}

// ===== 获取用户IP =====
$ip = getUserIP();

// ===== 获取IP归属地 =====
$location = getIPLocation($ip);

// ===== 获取浏览器信息 =====
$browser = getBrowserInfo();

// ===== 获取平台信息 =====
$platform = getPlatformInfo();

// ===== 构建返回数据 =====
$result = [
    'success' => true,
    'ip' => $ip,
    'location' => $location,
    'browser' => $browser,
    'platform' => $platform
];

echo json_encode($result);
?>