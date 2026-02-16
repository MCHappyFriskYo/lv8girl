<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (empty($title) || empty($content)) {
        $error = '标题和内容不能为空';
    } else {
        // 处理图片上传
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/';
            // 如果目录不存在则创建
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $file_tmp = $_FILES['image']['tmp_name'];
            $file_name = time() . '_' . basename($_FILES['image']['name']);
            $target_path = $upload_dir . $file_name;
            // 验证图片类型
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $file_type = mime_content_type($file_tmp);
            if (in_array($file_type, $allowed_types)) {
                if (move_uploaded_file($file_tmp, $target_path)) {
                    $image_path = $target_path;
                } else {
                    $error = '图片上传失败';
                }
            } else {
                $error = '只允许上传 JPEG、PNG、GIF 或 WEBP 格式的图片';
            }
        }

        if (empty($error)) {
            // 连接数据库
            $host = 'db';
            $dbname = 'lv8girl';
            $username_db = 'lv8girl';               // 数据库用户名
            $password_db = 'yourpasswd';        // 数据库密码（已修改）

            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username_db, $password_db);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                $stmt = $pdo->prepare("INSERT INTO discussions (user_id, title, content, image_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $title, $content, $image_path]);

                // 成功后重定向到首页
                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                $error = '数据库错误，请稍后重试';
            }
        }
    }

    // 如果有错误，重定向回发表页面并显示错误
    if (!empty($error)) {
        header('Location: post_discussion.php?error=' . urlencode($error));
        exit;
    }
} else {
    // 非POST请求，返回发表页面
    header('Location: post_discussion.php');
    exit;
}