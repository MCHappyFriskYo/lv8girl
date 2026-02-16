<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = isset($_POST['post_id']) ? (int)$_POST['post_id'] : 0;
$action = $_POST['action'] ?? '';

if (!$post_id || !in_array($action, ['favorite', 'unfavorite'])) {
    header('Location: index.php');
    exit;
}

// 数据库连接
$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';               // 数据库用户名
$db_pass = 'yourpasswd';        // 数据库密码（已修改）

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败');
}

if ($action === 'favorite') {
    // 添加收藏，忽略重复
    try {
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, post_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $post_id]);
    } catch (PDOException $e) {
        // 重复收藏忽略
    }
} else {
    // 取消收藏
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$user_id, $post_id]);
}

// 重定向回帖子页面
header("Location: post.php?id=$post_id");
exit;