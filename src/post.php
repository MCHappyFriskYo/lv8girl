<?php
session_start();

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

// 获取帖子ID
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$post_id) {
    header('Location: index.php');
    exit;
}

// 更新浏览量（使用 session 防止重复计数）
if (!isset($_SESSION['viewed_posts']) || !in_array($post_id, $_SESSION['viewed_posts'])) {
    $stmt = $pdo->prepare("UPDATE discussions SET views = views + 1 WHERE id = ?");
    $stmt->execute([$post_id]);
    
    if (!isset($_SESSION['viewed_posts'])) {
        $_SESSION['viewed_posts'] = [];
    }
    $_SESSION['viewed_posts'][] = $post_id;
}

// 获取帖子信息及作者
$stmt = $pdo->prepare("
    SELECT d.*, u.id as author_id, u.username, u.avatar, u.role
    FROM discussions d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.id = ?
");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    die('帖子不存在');
}

// 获取作者的帖子总数
$stmt = $pdo->prepare("SELECT COUNT(*) FROM discussions WHERE user_id = ?");
$stmt->execute([$post['author_id']]);
$author_post_count = $stmt->fetchColumn();

// 获取点赞数
$stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
$stmt->execute([$post_id]);
$like_count = $stmt->fetchColumn();

// 获取当前用户是否已点赞
$user_liked = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM likes WHERE post_id = ? AND user_id = ?");
    $stmt->execute([$post_id, $_SESSION['user_id']]);
    $user_liked = $stmt->fetch() ? true : false;
}

// 获取评论列表
$stmt = $pdo->prepare("
    SELECT c.*, u.username, u.avatar 
    FROM comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.post_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$post_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 处理点赞
if (isset($_POST['action']) && $_POST['action'] === 'like' && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("INSERT IGNORE INTO likes (post_id, user_id) VALUES (?, ?)");
    $stmt->execute([$post_id, $_SESSION['user_id']]);
    header("Location: post.php?id=$post_id");
    exit;
}

// 处理评论
$comment_error = '';
if (isset($_POST['action']) && $_POST['action'] === 'comment' && isset($_SESSION['user_id'])) {
    $content = trim($_POST['content'] ?? '');
    if (empty($content)) {
        $comment_error = '评论内容不能为空';
    } else {
        $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$post_id, $_SESSION['user_id'], $content]);
        header("Location: post.php?id=$post_id");
        exit;
    }
}

// 当前登录用户信息
$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

// 角色中文显示
$role_map = [
    'admin' => '管理员',
    'user' => '正常用户',
    'banned' => '封禁用户'
];
$author_role_text = $role_map[$post['role']] ?? $post['role'];
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - lv8girl</title>
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
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        /* 头部导航 */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 20px;
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
        .nav-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .nav-right a {
            color: var(--text-soft);
            text-decoration: none;
            padding: 6px 16px;
            border-radius: 30px;
            background: var(--surface-light);
            transition: 0.2s;
        }
        .nav-right a:hover {
            background: var(--primary);
            color: white;
        }
        .user-menu {
            position: relative;
            cursor: pointer;
        }
        .user-name {
            display: flex;
            align-items: center;
            gap: 5px;
            background: var(--surface-light);
            padding: 6px 16px;
            border-radius: 30px;
            color: var(--text);
        }
        .dropdown {
            position: absolute;
            top: 120%;
            right: 0;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            min-width: 140px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: 0.2s;
            z-index: 100;
        }
        .user-menu:hover .dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        .dropdown a {
            display: block;
            padding: 10px 16px;
            color: var(--text);
            text-decoration: none;
            border-bottom: 1px solid var(--border);
            background: transparent;
        }
        .dropdown a:last-child { border-bottom: none; }
        .dropdown a:hover { background: var(--surface-light); }
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

        /* 两栏布局 */
        .main-layout {
            display: flex;
            gap: 30px;
        }
        .content-left {
            flex: 2;
        }
        .content-right {
            flex: 1;
        }

        /* 帖子内容卡片 */
        .post-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .post-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        .post-author-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .post-author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .post-meta {
            flex: 1;
        }
        .post-author-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--accent);
            text-decoration: none;
        }
        .post-author-name:hover {
            text-decoration: underline;
        }
        .post-time {
            color: var(--text-hint);
            font-size: 0.85rem;
        }
        .post-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--text);
        }
        .post-image {
            margin: 20px 0;
            max-width: 100%;
            border-radius: 12px;
            overflow: hidden;
        }
        .post-image img {
            width: 100%;
            max-height: 500px;
            object-fit: contain;
            background: var(--surface-light);
        }
        .post-content {
            color: var(--text);
            font-size: 1.1rem;
            line-height: 1.7;
            margin-bottom: 20px;
            white-space: pre-wrap;
        }
        .post-actions {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .like-btn {
            background: var(--gradient);
            border: none;
            border-radius: 40px;
            padding: 8px 25px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            border: none;
        }
        .like-btn:hover {
            transform: scale(1.02);
        }
        .like-btn.liked {
            background: #ff6b6b;
        }
        .like-count {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text);
        }
        .view-count {
            margin-left: auto;
            color: var(--text-hint);
            font-size: 0.95rem;
        }

        /* 右侧作者卡片 */
        .author-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
        }
        .author-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--gradient);
            margin: 0 auto 15px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 600;
        }
        .author-avatar-large img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .author-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 5px;
        }
        .author-meta {
            color: var(--text-hint);
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .author-uid {
            color: var(--text-soft);
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        .author-stats {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin: 15px 0;
            padding-top: 15px;
            border-top: 1px solid var(--border-light);
        }
        .author-stat-item {
            text-align: center;
        }
        .author-stat-number {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--primary);
        }
        .author-stat-label {
            color: var(--text-hint);
            font-size: 0.8rem;
        }
        .view-profile {
            display: inline-block;
            background: var(--gradient);
            color: white;
            text-decoration: none;
            padding: 8px 20px;
            border-radius: 40px;
            font-weight: 600;
            transition: 0.2s;
        }
        .view-profile:hover {
            transform: scale(1.02);
        }

        /* 评论区 */
        .comments-section {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .comments-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--accent);
            margin-bottom: 20px;
        }
        .comment-form {
            margin-bottom: 30px;
        }
        .comment-form textarea {
            width: 100%;
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 15px;
            font-size: 1rem;
            color: var(--text);
            resize: vertical;
            min-height: 100px;
            margin-bottom: 10px;
        }
        .comment-form button {
            background: var(--gradient);
            border: none;
            border-radius: 40px;
            padding: 10px 30px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
        }
        .comment-form button:hover {
            transform: scale(1.02);
        }
        .comment-error {
            color: #ff6b6b;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        .comment-item {
            display: flex;
            gap: 15px;
            padding: 20px 0;
            border-bottom: 1px solid var(--border);
        }
        .comment-item:last-child {
            border-bottom: none;
        }
        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient);
            overflow: hidden;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .comment-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .comment-content {
            flex: 1;
        }
        .comment-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 5px;
        }
        .comment-author {
            font-weight: 600;
            color: var(--accent);
            text-decoration: none;
        }
        .comment-author:hover {
            text-decoration: underline;
        }
        .comment-time {
            color: var(--text-hint);
            font-size: 0.8rem;
        }
        .comment-text {
            color: var(--text-soft);
            line-height: 1.5;
        }
        .no-comments {
            text-align: center;
            color: var(--text-hint);
            padding: 30px 0;
        }
        .login-prompt {
            text-align: center;
            color: var(--text-hint);
            margin-bottom: 20px;
        }
        .login-prompt a {
            color: var(--accent);
            text-decoration: none;
        }

        /* 页脚 */
        .footer {
            margin-top: 40px;
            padding: 20px 0;
            border-top: 1px solid var(--border);
            text-align: center;
            color: var(--text-hint);
            font-size: 0.9rem;
        }
        .footer a {
            color: var(--text-hint);
            text-decoration: none;
            margin: 0 10px;
        }
        .footer a:hover {
            color: var(--accent);
        }

        @media (max-width: 800px) {
            .main-layout {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 头部导航 -->
        <div class="header">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div class="nav-right">
                <?php if ($is_logged_in): ?>
                    <div class="user-menu">
                        <span class="user-name"><?php echo htmlspecialchars($username); ?> ▼</span>
                        <div class="dropdown">
                            <?php if ($user_role === 'admin'): ?>
                                <a href="admin.php">管理面板</a>
                            <?php endif; ?>
                            <a href="profile.php">个人主页</a>
                            <a href="post_discussion.php">发表新帖</a>
                            <a href="my_posts.php">我的帖子</a>
                            <a href="#">收藏夹</a>
                            <a href="#">设置</a>
                            <a href="logout.php">登出</a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php">登录</a>
                    <a href="register.php">注册</a>
                <?php endif; ?>
                <div class="theme-toggle" id="themeToggle">🌓</div>
            </div>
        </div>

        <!-- 两栏主内容 -->
        <div class="main-layout">
            <!-- 左侧：帖子内容 -->
            <div class="content-left">
                <div class="post-card">
                    <div class="post-header">
                        <a href="profile.php?id=<?php echo $post['author_id']; ?>" class="post-author-avatar">
                            <?php if ($post['avatar'] && file_exists($post['avatar'])): ?>
                                <img src="<?php echo htmlspecialchars($post['avatar']); ?>" alt="avatar">
                            <?php else: ?>
                                <?php echo strtoupper(mb_substr($post['username'], 0, 1)); ?>
                            <?php endif; ?>
                        </a>
                        <div class="post-meta">
                            <a href="profile.php?id=<?php echo $post['author_id']; ?>" class="post-author-name">
                                <?php echo htmlspecialchars($post['username']); ?>
                            </a>
                            <div class="post-time"><?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></div>
                        </div>
                    </div>
                    <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>

                    <?php if ($post['image_path'] && file_exists($post['image_path'])): ?>
                        <div class="post-image">
                            <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="post image">
                        </div>
                    <?php endif; ?>

                    <div class="post-content">
                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                    </div>

                    <div class="post-actions">
                        <?php if ($is_logged_in): ?>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="like">
                                <button type="submit" class="like-btn <?php echo $user_liked ? 'liked' : ''; ?>">
                                    <?php echo $user_liked ? '✓ 已点赞' : '👍 点赞'; ?>
                                </button>
                            </form>
                        <?php else: ?>
                            <a href="login.php" class="like-btn">👍 登录后点赞</a>
                        <?php endif; ?>
                        <span class="like-count"><?php echo $like_count; ?> 人点赞</span>
                        <span class="view-count">👁️ <?php echo number_format($post['views']); ?> 次阅读</span>
                    </div>
                </div>

                <!-- 评论区 -->
                <div class="comments-section">
                    <h2 class="comments-title">评论 (<?php echo count($comments); ?>)</h2>

                    <?php if ($is_logged_in): ?>
                        <form method="post" class="comment-form">
                            <input type="hidden" name="action" value="comment">
                            <textarea name="content" placeholder="写下你的评论..." required></textarea>
                            <?php if ($comment_error): ?>
                                <div class="comment-error"><?php echo htmlspecialchars($comment_error); ?></div>
                            <?php endif; ?>
                            <button type="submit">发表评论</button>
                        </form>
                    <?php else: ?>
                        <div class="login-prompt">
                            <a href="login.php">登录</a> 后即可评论
                        </div>
                    <?php endif; ?>

                    <?php if (empty($comments)): ?>
                        <div class="no-comments">暂无评论，快来抢沙发吧～</div>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                            <div class="comment-item">
                                <a href="profile.php?id=<?php echo $comment['user_id']; ?>" class="comment-avatar">
                                    <?php if ($comment['avatar'] && file_exists($comment['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($comment['avatar']); ?>" alt="avatar">
                                    <?php else: ?>
                                        <?php echo strtoupper(mb_substr($comment['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </a>
                                <div class="comment-content">
                                    <div class="comment-header">
                                        <a href="profile.php?id=<?php echo $comment['user_id']; ?>" class="comment-author">
                                            <?php echo htmlspecialchars($comment['username']); ?>
                                        </a>
                                        <span class="comment-time"><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></span>
                                    </div>
                                    <div class="comment-text"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 右侧：作者信息 -->
            <div class="content-right">
                <div class="author-card">
                    <div class="author-avatar-large">
                        <?php if ($post['avatar'] && file_exists($post['avatar'])): ?>
                            <img src="<?php echo htmlspecialchars($post['avatar']); ?>" alt="avatar">
                        <?php else: ?>
                            <?php echo strtoupper(mb_substr($post['username'], 0, 1)); ?>
                        <?php endif; ?>
                    </div>
                    <div class="author-name"><?php echo htmlspecialchars($post['username']); ?></div>
                    <div class="author-meta">身份：<?php echo $author_role_text; ?></div>
                    <div class="author-uid">UID: <?php echo $post['author_id']; ?></div>
                    <div class="author-stats">
                        <div class="author-stat-item">
                            <div class="author-stat-number"><?php echo number_format($author_post_count); ?></div>
                            <div class="author-stat-label">帖子</div>
                        </div>
                        <!-- 可以添加其他统计，如粉丝数等 -->
                    </div>
                    <a href="profile.php?id=<?php echo $post['author_id']; ?>" class="view-profile">查看个人主页</a>
                </div>
            </div>
        </div>

        <!-- 页脚 -->
        <div class="footer">
            <div>© 2025 lv8girl · 绿坝娘二次元论坛</div>
            <div>
                <a href="#">关于</a>
                <a href="#">帮助</a>
                <a href="#">隐私</a>
                <a href="#">投稿</a>
                <a href="https://icp.gov.moe/?keyword=20260911" target="_blank">萌ICP备20260911号</a>
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