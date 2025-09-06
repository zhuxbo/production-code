<?php
$uri = $_SERVER['REQUEST_URI'];  // 包含路径和参数

// 判断是否需要保留原始路径
if (preg_match('#^/(pdd|tb)(/.*)?$#i', $uri) || preg_match('#^/tid/[^/]+#i', $uri)) {
    $target = '/user' . $uri;
} else {
    $target = '/user';
}

echo '<!doctype html>'
   . '<html lang="zh-CN">'
   . '  <head>'
   . '    <meta charset="utf-8">'
   . '    <title>Redirecting…</title>'
   . '  </head>'
   . '  <body>'
   . '    <script>window.location.replace(' . json_encode($target) . ');</script>'
   . '    <noscript>正在跳转到 <a href="' . htmlspecialchars($target, ENT_QUOTES) . '">' 
   . htmlspecialchars($target, ENT_QUOTES) . '</a> …</noscript>'
   . '  </body>'
   . '</html>';
exit;
/**
 * ===================================================================
 * 一个简单的 PHP 路由器 (index.php)
 * ===================================================================
 */

// -------------------------------------------------------------------
//  1. 配置区域: 定义路由白名单
// -------------------------------------------------------------------
// 格式: '请求的 URI 路径' => '要加载的服务器 PHP 文件名'
$allowedRoutes = [
    '/'         => 'home.php', 
    '/product'  => 'product.php',
    '/contact'  => 'contact.php',
    '/about-us' => 'about.php',
];

// -------------------------------------------------------------------
//  2. 路由处理逻辑 (通常无需修改)
// -------------------------------------------------------------------

// 获取请求的 URI 路径，不包含 ? 后面的查询参数
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// --- 检查路径是否在我们的路由白名单中 ---
if (array_key_exists($requestPath, $allowedRoutes)) {
    
    // 从白名单中获取对应的文件名
    $fileToInclude = $allowedRoutes[$requestPath];
    
    // 构建文件的绝对物理路径
    // __DIR__ 是一个 PHP 魔术常量，代表当前 index.php 文件所在的目录
    $filePath = __DIR__ . '/' . $fileToInclude;

    // --- 检查对应的物理文件是否存在 ---
    if (file_exists($filePath)) {
        // 文件存在，加载并执行它
        require $filePath;
    } else {
        // 文件不存在 (例如，路由配置了，但忘了创建文件)
        // 这是一种服务器内部错误，但为了安全，我们同样返回 404
        http_response_code(404);
        // 您可以创建一个漂亮的 404 页面文件
        if (file_exists(__DIR__ . '/404.php')) {
            require __DIR__ . '/404.php';
        } else {
            echo "<h1>404 Not Found</h1>";
            echo "The requested resource could not be found.";
        }
    }
} else {
    // --- 如果请求的路径不在白名单中，直接返回 404 ---
    http_response_code(404);
    if (file_exists(__DIR__ . '/404.php')) {
        require __DIR__ . '/404.php';
    } else {
        echo "<h1>404 Not Found</h1>";
        echo "The requested page is not a valid route.";
    }
}

// 确保脚本执行完毕
exit;