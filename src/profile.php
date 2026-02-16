<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';               // 数据库用户名
$db_pass = 'yourpasswd';        // 数据库密码（已修改）

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败：' . $e->getMessage());
}

// 获取要查看的用户ID，默认为当前登录用户
$view_user_id = isset($_GET['id']) ? (int)$_GET['id'] : $_SESSION['user_id'];
$current_user_id = $_SESSION['user_id'];
$is_owner = ($view_user_id == $current_user_id);

// 获取用户信息
$stmt = $pdo->prepare("SELECT id, username, email, created_at, avatar FROM users WHERE id = ?");
$stmt->execute([$view_user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die('用户不存在');
}

// 获取用户帖子总数
$stmt = $pdo->prepare("SELECT COUNT(*) FROM discussions WHERE user_id = ?");
$stmt->execute([$view_user_id]);
$post_count = $stmt->fetchColumn();

// 获取用户所有帖子获得的点赞总数
$stmt = $pdo->prepare("
    SELECT COUNT(l.id) 
    FROM likes l 
    JOIN discussions d ON l.post_id = d.id 
    WHERE d.user_id = ?
");
$stmt->execute([$view_user_id]);
$total_likes = $stmt->fetchColumn();

// 获取用户所有帖子获得的收藏总数
$stmt = $pdo->prepare("
    SELECT COUNT(f.id) 
    FROM favorites f 
    JOIN discussions d ON f.post_id = d.id 
    WHERE d.user_id = ?
");
$stmt->execute([$view_user_id]);
$total_favorites = $stmt->fetchColumn();

// 获取用户帖子列表（带点赞数、评论数）
$stmt = $pdo->prepare("
    SELECT d.*, 
           (SELECT COUNT(*) FROM likes WHERE post_id = d.id) as like_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = d.id) as comment_count
    FROM discussions d 
    WHERE d.user_id = ? 
    ORDER BY d.created_at DESC
");
$stmt->execute([$view_user_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
if (isset($_GET['success'])) {
    $message = '<div class="success-message">' . htmlspecialchars($_GET['success']) . '</div>';
} elseif (isset($_GET['error'])) {
    $message = '<div class="error-message">' . htmlspecialchars($_GET['error']) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?>的个人主页 - lv8girl</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Noto Sans SC', 'PingFang SC', 'Microsoft YaHei', 'Helvetica Neue', Arial, sans-serif;
        }

        :root {
            --primary: #3d9e4a;
            --primary-dark: #2e7d32;
            --primary-light: #6abf6e;
            --secondary: #ffb347;
            --accent-blue: #5a9cff;
            --accent-purple: #b47aff;
            --accent-pink: #ff7b9c;
            --bg-body: linear-gradient(145deg, #f0f7f0 0%, #e6f0e6 100%);
            --bg-surface: #ffffff;
            --bg-nav: rgba(255,255,255,0.9);
            --text-primary: #2c3e2c;
            --text-secondary: #5f6b5f;
            --text-hint: #8f9f8f;
            --border-light: #e6ede6;
            --border: #d0e0d0;
            --shadow: 0 8px 20px rgba(0,0,0,0.06);
            --hover-shadow: 0 12px 28px rgba(61, 158, 74, 0.2);
            
            --font-weight-light: 400;
            --font-weight-regular: 500;
            --font-weight-bold: 700;
            --font-weight-black: 900;
        }

        body.dark-mode {
            --primary: #6b8e6b;
            --primary-dark: #4a6b4a;
            --primary-light: #8aad8a;
            --secondary: #d9a066;
            --accent-blue: #6688aa;
            --accent-purple: #8a7a9c;
            --accent-pink: #b57a8a;
            --bg-body: linear-gradient(145deg, #1e261e 0%, #232d23 100%);
            --bg-surface: #2c342c;
            --bg-nav: #2c342ccc;
            --text-primary: #e0e8e0;
            --text-secondary: #b0bcb0;
            --text-hint: #8a958a;
            --border-light: #3a453a;
            --border: #4d5a4d;
            --shadow: 0 8px 20px rgba(0,0,0,0.5);
            --hover-shadow: 0 12px 28px rgba(107, 142, 107, 0.3);
        }

        body {
            background: var(--bg-body);
            min-height: 100vh;
            transition: background 0.3s;
            padding: 20px;
            font-weight: var(--font-weight-regular);
            line-height: 1.5;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .mini-nav {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: var(--font-weight-black);
            background: linear-gradient(135deg, var(--primary), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
        }

        .logo span {
            font-size: 0.8rem;
            background: var(--secondary);
            color: var(--primary-dark);
            padding: 4px 10px;
            border-radius: 30px;
            margin-left: 10px;
            font-weight: var(--font-weight-bold);
            -webkit-text-fill-color: var(--primary-dark);
        }

        .theme-toggle {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            color: var(--text-primary);
            font-size: 1.3rem;
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .theme-toggle:hover {
            background: linear-gradient(135deg, var(--secondary), var(--accent-pink));
            color: var(--primary-dark);
            transform: rotate(15deg) scale(1.1);
        }

        .message {
            margin-bottom: 20px;
        }
        .success-message {
            background: var(--primary-light);
            color: var(--primary-dark);
            padding: 12px 20px;
            border-radius: 30px;
            text-align: center;
            font-weight: var(--font-weight-bold);
        }
        .error-message {
            background: var(--accent-pink);
            color: white;
            padding: 12px 20px;
            border-radius: 30px;
            text-align: center;
            font-weight: var(--font-weight-bold);
        }

        .profile-card {
            background: var(--bg-surface);
            border-radius: 30px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            border: 4px solid var(--secondary);
            flex-shrink: 0;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info {
            flex: 1;
        }

        .profile-info h1 {
            font-size: 2.2rem;
            font-weight: var(--font-weight-black);
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .profile-info .email {
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .profile-info .joined {
            color: var(--text-hint);
            font-size: 0.9rem;
            margin-bottom: 16px;
        }

        .stats {
            display: flex;
            gap: 30px;
            margin-bottom: 16px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-number {
            font-size: 1.6rem;
            font-weight: var(--font-weight-black);
            color: var(--primary);
        }

        .stat-label {
            color: var(--text-hint);
            font-size: 0.85rem;
        }

        .btn-edit {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary), var(--accent-blue));
            border: none;
            border-radius: 40px;
            padding: 10px 30px;
            color: white;
            font-weight: var(--font-weight-bold);
            text-decoration: none;
            transition: all 0.2s;
            margin-top: 16px;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, var(--primary-light), var(--accent-purple));
            transform: scale(1.02);
            box-shadow: var(--hover-shadow);
        }

        /* 上传表单样式 */
        .avatar-upload-form {
            margin-top: 20px;
            padding: 20px;
            background: var(--bg-nav);
            border-radius: 20px;
            border: 1px dashed var(--border);
        }

        .file-input {
            margin-bottom: 10px;
        }

        .file-input input[type="file"] {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 8px 16px;
            width: 100%;
            color: var(--text-primary);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            font-size: 1.6rem;
            font-weight: var(--font-weight-black);
            background: linear-gradient(135deg, var(--primary), var(--accent-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .post-list {
            list-style: none;
        }

        .post-item {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            margin-bottom: 20px;
            padding: 20px;
            box-shadow: var(--shadow);
            transition: all 0.2s;
        }

        .post-item:hover {
            box-shadow: var(--hover-shadow);
            border-color: var(--primary);
        }

        .post-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .post-title {
            font-size: 1.2rem;
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .post-date {
            color: var(--text-hint);
            font-size: 0.85rem;
        }

        .post-preview {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 12px;
            line-height: 1.5;
        }

        .post-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .post-stats {
            display: flex;
            gap: 20px;
            color: var(--text-hint);
            font-size: 0.9rem;
        }

        .post-actions {
            display: flex;
            gap: 10px;
        }

        .btn-delete {
            background: linear-gradient(135deg, var(--accent-pink), #ff4d6d);
            border: none;
            border-radius: 30px;
            padding: 6px 18px;
            color: white;
            font-weight: var(--font-weight-bold);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .btn-delete:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(255, 75, 100, 0.3);
        }

        .btn-view {
            background: linear-gradient(135deg, var(--primary-light), var(--accent-blue));
            border: none;
            border-radius: 30px;
            padding: 6px 18px;
            color: white;
            font-weight: var(--font-weight-bold);
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }

        .btn-view:hover {
            transform: scale(1.05);
            box-shadow: var(--hover-shadow);
        }

        .no-posts {
            text-align: center;
            color: var(--text-hint);
            padding: 40px;
            background: var(--bg-surface);
            border-radius: 20px;
            border: 1px dashed var(--border);
        }

        .footer-links {
            margin-top: 30px;
            text-align: center;
        }

        .footer-links a {
            color: var(--text-hint);
            text-decoration: none;
            margin: 0 10px;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .toggle-upload {
            background: none;
            border: none;
            color: var(--primary);
            font-weight: var(--font-weight-bold);
            cursor: pointer;
            text-decoration: underline;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="mini-nav">
            <div class="logo">lv8girl</div>
            <div class="theme-toggle" id="themeToggle">🌓</div>
        </div>

        <?php echo $message; ?>

        <div class="profile-card">
            <div class="profile-avatar">
                <?php if ($user['avatar'] && file_exists($user['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="avatar">
                <?php else: ?>
                    <?php 
                    $initial = strtoupper(mb_substr($user['username'], 0, 1));
                    echo htmlspecialchars($initial);
                    ?>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                <div class="email"><?php echo htmlspecialchars($user['email']); ?></div>
                <div class="joined">加入时间：<?php echo date('Y年m月d日', strtotime($user['created_at'])); ?></div>
                <div class="stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $post_count; ?></div>
                        <div class="stat-label">帖子</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_likes; ?></div>
                        <div class="stat-label">获赞</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_favorites; ?></div>
                        <div class="stat-label">收藏</div>
                    </div>
                </div>
                <?php if ($is_owner): ?>
                    <button class="btn-edit" onclick="toggleUpload()">修改头像</button>
                    
                    <!-- 隐藏的上传表单 -->
                    <div id="uploadForm" style="display: none;" class="avatar-upload-form">
                        <form action="upload_avatar.php" method="post" enctype="multipart/form-data">
                            <div class="file-input">
                                <input type="file" name="avatar" accept="image/*" required>
                            </div>
                            <button type="submit" class="btn-edit">上传新头像</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="section-header">
            <h2><?php echo $is_owner ? '我的帖子' : htmlspecialchars($user['username']) . '的帖子'; ?></h2>
        </div>

        <?php if (empty($posts)): ?>
            <div class="no-posts">
                <p>还没有发布过帖子</p>
                <?php if ($is_owner): ?>
                    <p><a href="post_discussion.php" style="color: var(--primary);">点击发表第一篇</a></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <ul class="post-list">
                <?php foreach ($posts as $post): ?>
                    <li class="post-item">
                        <div class="post-header">
                            <div class="post-title">
                                <a href="post.php?id=<?php echo $post['id']; ?>" style="color: inherit; text-decoration: none;">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                                <?php if ($post['image_path']): ?>
                                    <span style="font-size: 1.2rem;">📷</span>
                                <?php endif; ?>
                            </div>
                            <div class="post-date"><?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></div>
                        </div>
                        <div class="post-preview">
                            <?php echo htmlspecialchars(mb_substr(strip_tags($post['content']), 0, 150)) . (mb_strlen($post['content']) > 150 ? '...' : ''); ?>
                        </div>
                        <div class="post-footer">
                            <div class="post-stats">
                                <span>👍 <?php echo $post['like_count']; ?></span>
                                <span>💬 <?php echo $post['comment_count']; ?></span>
                            </div>
                            <div class="post-actions">
                                <a href="post.php?id=<?php echo $post['id']; ?>" class="btn-view">查看</a>
                                <?php if ($is_owner): ?>
                                    <a href="delete_post.php?id=<?php echo $post['id']; ?>" class="btn-delete" onclick="return confirm('确定要删除这篇帖子吗？')">删除</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="footer-links">
            <a href="index.php">返回首页</a>
            <a href="post_discussion.php">发表新帖</a>
        </div>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            themeToggle.textContent = body.classList.contains('dark-mode') ? '☀️' : '🌓';
        });

        function toggleUpload() {
            var form = document.getElementById('uploadForm');
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</body>
</html>