<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// 数据库配置
$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';
$db_pass = 'yourpasswd';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
    $file = $_FILES['avatar'];
    
    // 检查上传错误
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = '上传失败，错误码：' . $file['error'];
    } else {
        // 验证文件类型
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            $error = '只允许上传 JPEG、PNG、GIF 或 WEBP 格式的图片';
        } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB 限制
            $error = '图片大小不能超过2MB';
        } else {
            // 创建上传目录
            $upload_dir = 'uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // 生成唯一文件名，使用用户ID和时间戳
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                // 获取旧头像路径
                $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $old_avatar = $stmt->fetchColumn();
                
                // 更新数据库
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
                $stmt->execute([$target_path, $user_id]);
                
                // 删除旧头像（如果不是默认头像且文件存在）
                if ($old_avatar && file_exists($old_avatar)) {
                    unlink($old_avatar);
                }
                
                $success = '头像更新成功！';
            } else {
                $error = '保存文件失败，请检查目录权限';
            }
        }
    }
    
    // 重定向回个人主页，附带消息
    $redirect = 'profile.php' . ($user_id ? '' : '') . '?' . 
                ($success ? 'success=' . urlencode($success) : 'error=' . urlencode($error));
    header("Location: $redirect");
    exit;
} else {
    header('Location: profile.php');
    exit;
}