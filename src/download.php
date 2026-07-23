<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "config.php";

// 获取要下载的文件路径
$file = isset($_GET['file']) ? trim($_GET['file']) : '';
if (empty($file)) {
    exit("下载参数错误");
}

// 安全限制：只允许下载 pdf/ 目录下文件，防止跨目录遍历漏洞
$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'pdf' . DIRECTORY_SEPARATOR;
$realPath = realpath($baseDir . $file);

// 校验：文件必须在pdf文件夹内 + 文件存在
if (!$realPath || strpos($realPath, $baseDir) !== 0 || !is_file($realPath)) {
    exit("文件不存在，无法下载");
}

// 获取文件名
$fileName = basename($realPath);

// 输出下载头部，强制PDF下载
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($realPath));
header('Cache-Control: no-cache, must-revalidate');

// 读取文件输出
readfile($realPath);
exit;
