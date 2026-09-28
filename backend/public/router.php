<?php
// PHP 内置服务器专用路由（php -S 127.0.0.1:8000 -t public public/router.php）
//
// 注意：启动必须带 -t public，否则文档根目录是 backend/，
// 静态文件（/uploads/...）会被映射到 backend/uploads/... 而全部 404。
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    // 必须先 urldecode：中文/空格文件名在 REQUEST_URI 里是百分号编码的
    $decoded = urldecode((string) $path);
    $file = __DIR__ . $decoded;

    if ($decoded !== '/' && is_file($file)) {
        // 自己输出静态文件：避免 php -S 静态处理在 Windows 下对中文路径/未知 MIME 的兼容问题
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $mimes = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls'  => 'application/vnd.ms-excel',
            'csv'  => 'text/csv; charset=utf-8',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'svg'  => 'image/svg+xml',
            'ico'  => 'image/x-icon',
            'pdf'  => 'application/pdf',
            'zip'  => 'application/zip',
            'txt'  => 'text/plain; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'css'  => 'text/css; charset=utf-8',
            'js'   => 'application/javascript; charset=utf-8',
            'html' => 'text/html; charset=utf-8',
        ];
        $type = $mimes[$ext] ?? (function_exists('mime_content_type') ? (mime_content_type($file) ?: 'application/octet-stream') : 'application/octet-stream');

        $base = basename($file);
        // 内联展示（图片/PDF），其余类型附件下载；文件名用 RFC 5987 filename* 保证中文正确
        $inline = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'ico', 'pdf', 'txt'], true);
        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $base) ?: 'download';
        header('Content-Type: ' . $type);
        header('Content-Length: ' . filesize($file));
        header(
            'Content-Disposition: ' . ($inline ? 'inline' : 'attachment')
            . '; filename="' . $fallback . '"'
            . "; filename*=UTF-8''" . rawurlencode($base)
        );
        header('Cache-Control: no-cache');
        readfile($file);
        return true;
    }
}
require __DIR__ . '/index.php';
