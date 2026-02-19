<?php
session_start();

// 必须登录才能访问
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

$error = '';
$success = '';

// 处理提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    // 验证
    if (empty($title) || empty($content)) {
        $error = '标题和内容不能为空';
    } else {
        // 处理图片上传
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
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
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                // 生成唯一文件名
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'post_' . time() . '_' . uniqid() . '.' . $ext;
                $target_path = $upload_dir . $filename;
                if (move_uploaded_file($file['tmp_name'], $target_path)) {
                    $image_path = $target_path;
                } else {
                    $error = '图片保存失败，请检查目录权限';
                }
            }
        }

        if (empty($error)) {
            // 插入数据库，状态默认为 'pending'（待审核）
            $stmt = $pdo->prepare("INSERT INTO discussions (user_id, title, content, image_path, status) VALUES (?, ?, ?, ?, 'pending')");
            $stmt->execute([$user_id, $title, $content, $image_path]);
            $success = '帖子已提交，等待管理员审核。正在跳转...';
            // 2秒后跳转到首页
            echo '<meta http-equiv="refresh" content="2;url=index.php">';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>发表新帖 - lv8girl</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --bg: #f0f7f0;
            --surface: #ffffff;
            --surface-light: #e8f0e8;
            --border: #d0e0d0;
            --border-light: #e0e8e0;
            --text: #2c3e2c;
            --text-soft: #5f6b5f;
            --text-hint: #8f9f8f;
            --primary: #3d9e4a;
            --primary-light: #6abf6e;
            --accent: #ffb347;
            --accent-dark: #d9a066;
            --gradient: linear-gradient(135deg, #3d9e4a, #5a9cff);
        }
        body.dark-mode {
            --bg: #1a1e1a;
            --surface: #1e261e;
            --surface-light: #2a3a2a;
            --border: #2a3a2a;
            --border-light: #3a4a3a;
            --text: #e0e8e0;
            --text-soft: #b0bcb0;
            --text-hint: #8a958a;
            --primary: #6b8e6b;
            --primary-light: #8aad8a;
            --accent: #ffb347;
            --accent-dark: #d9a066;
            --gradient: linear-gradient(135deg, #6b8e6b, #5a9cff);
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, 'Segoe UI', 'PingFang SC', 'Microsoft YaHei', sans-serif;
            line-height: 1.6;
            transition: background 0.3s, color 0.3s;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .post-wrapper {
            max-width: 800px;
            width: 100%;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 2rem;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logo span {
            font-size: 0.9rem;
            background: var(--accent);
            color: var(--surface);
            padding: 4px 12px;
            border-radius: 30px;
            margin-left: 10px;
            -webkit-text-fill-color: var(--surface);
        }
        .theme-toggle {
            background: var(--surface-light);
            border: none;
            color: var(--text);
            font-size: 1.3rem;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: 0.2s;
        }
        .theme-toggle:hover {
            background: var(--accent);
            color: var(--surface);
        }
        .post-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.04);
        }
        .post-card h2 {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 25px;
            text-align: center;
        }
        .error-message {
            background: var(--surface-light);
            border-left: 4px solid #ff6b6b;
            color: var(--text-soft);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success-message {
            background: var(--surface-light);
            border-left: 4px solid var(--primary);
            color: var(--text-soft);
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-soft);
            font-weight: 500;
        }
        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px 15px;
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 30px;
            color: var(--text);
            font-size: 1rem;
            outline: none;
            transition: border 0.2s;
        }
        textarea {
            border-radius: 20px;
            resize: vertical;
            min-height: 150px;
        }
        input:focus, textarea:focus {
            border-color: var(--accent);
        }
        input[type="file"] {
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 10px 15px;
            width: 100%;
            color: var(--text);
        }
        .file-note {
            color: var(--text-hint);
            font-size: 0.85rem;
            margin-top: 5px;
        }
        .btn {
            background: var(--gradient);
            border: none;
            border-radius: 40px;
            padding: 14px 30px;
            color: white;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
            width: 100%;
            margin-top: 10px;
        }
        .btn:hover {
            transform: scale(1.02);
        }
        .footer-links {
            margin-top: 20px;
            text-align: center;
        }
        .footer-links a {
            color: var(--text-hint);
            text-decoration: none;
            margin: 0 10px;
        }
        .footer-links a:hover {
            color: var(--accent);
        }
    </style>
</head>
<body>
    <div class="post-wrapper">
        <!-- 头部：logo + 主题切换 -->
        <div class="header">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div class="theme-toggle" id="themeToggle">🌓</div>
        </div>

        <!-- 发表帖子卡片 -->
        <div class="post-card">
            <h2>发表新帖</h2>

            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success-message"><?php echo htmlspecialchars($success); ?></div>
            <?php else: ?>
                <form method="post" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>标题</label>
                        <input type="text" name="title" placeholder="请输入标题" required value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label>内容</label>
                        <textarea name="content" placeholder="请输入帖子内容..." required><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>上传图片（可选，不超过2MB）</label>
                        <input type="file" name="image" accept="image/*">
                        <div class="file-note">支持 JPEG、PNG、GIF、WEBP 格式</div>
                    </div>
                    <button type="submit" class="btn">发 布</button>
                </form>
            <?php endif; ?>

            <div class="footer-links">
                <a href="index.php">返回首页</a>
                <a href="profile.php">个人主页</a>
            </div>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            themeToggle.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌓';
        });
    </script>
</body>
</html>