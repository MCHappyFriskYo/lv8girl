<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$current_user_id = $_SESSION['user_id'];
$is_admin = ($current_user_id == 1); // 管理员ID为1

// 获取帖子ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $redirect = isset($_GET['from']) && $_GET['from'] == 'admin' ? 'admin.php' : 'my_posts.php';
    header("Location: $redirect?error=无效的帖子ID");
    exit;
}
$post_id = (int)$_GET['id'];
$from = isset($_GET['from']) && $_GET['from'] == 'admin' ? 'admin' : 'my_posts';

// 连接数据库
$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';               // 数据库用户名
$db_pass = 'yourpasswd';        // 数据库密码（已修改）

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $redirect = ($from == 'admin') ? 'admin.php' : 'my_posts.php';
    header("Location: $redirect?error=数据库连接失败");
    exit;
}

// 查询帖子，如果非管理员则验证所有权
$sql = "SELECT * FROM discussions WHERE id = ?";
$params = [$post_id];
if (!$is_admin) {
    $sql .= " AND user_id = ?";
    $params[] = $current_user_id;
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    $redirect = ($from == 'admin') ? 'admin.php' : 'my_posts.php';
    header("Location: $redirect?error=帖子不存在或无权限删除");
    exit;
}

// 如果有图片，删除图片文件
if ($post['image_path'] && file_exists($post['image_path'])) {
    unlink($post['image_path']);
}

// 删除帖子
$stmt = $pdo->prepare("DELETE FROM discussions WHERE id = ?");
$stmt->execute([$post_id]);

// 重定向回来源页面
if ($from == 'admin') {
    header('Location: admin.php?msg=帖子已删除');
} else {
    header('Location: my_posts.php?deleted=1');
}
exit;