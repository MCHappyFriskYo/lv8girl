<?php
session_start();

// 数据库配置
$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';
$db_pass = 'yourpasswd'; // ⚠️ 请修改为实际密码

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $pdo = null;
}

// 定义登录状态变量
$isLoggedIn = isset($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

// 更新当前用户的最后活动时间（如果已登录）
if ($isLoggedIn && $pdo) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
        $stmt->execute([$current_user_id]);
    } catch (PDOException $e) {
        // 忽略错误
    }
}

// 获取在线用户数（最近5分钟内有活动）
$online_count = 0;
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE last_active > DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
        $online_count = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $online_count = 0;
    }
}

// 获取帖子列表（仅显示已审核的帖子，并按置顶状态排序）
$posts = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT 
                d.*, 
                u.username, 
                u.avatar,
                COALESCE(l.like_count, 0) AS like_count,
                COALESCE(c.comment_count, 0) AS comment_count
            FROM discussions d 
            JOIN users u ON d.user_id = u.id 
            LEFT JOIN (
                SELECT post_id, COUNT(*) AS like_count 
                FROM likes 
                GROUP BY post_id
            ) l ON d.id = l.post_id
            LEFT JOIN (
                SELECT post_id, COUNT(*) AS comment_count 
                FROM comments 
                GROUP BY post_id
            ) c ON d.id = c.post_id
            WHERE d.status = 'approved'
            ORDER BY d.is_pinned DESC, d.created_at DESC 
            LIMIT 30
        ");
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $posts = [];
    }
}

// 统计帖子总数（仅已审核）
$post_count = 0;
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM discussions WHERE status = 'approved'");
        $post_count = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $post_count = 0;
    }
}

// 统计用户总数
$user_count = 0;
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $user_count = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $user_count = 0;
    }
}

// 获取未读私信数（仅登录用户）
$unread_count = 0;
if ($isLoggedIn && $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM private_messages WHERE to_user_id = ? AND is_read = 0");
        $stmt->execute([$current_user_id]);
        $unread_count = $stmt->fetchColumn();
    } catch (PDOException $e) {
        $unread_count = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>lv8girl · 绿坝娘二次元论坛</title>
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
        /* 头部 */
        .header {
            margin-bottom: 30px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 20px;
        }
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
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
        .user-area {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .user-area a {
            color: var(--text-soft);
            text-decoration: none;
            padding: 6px 16px;
            border-radius: 30px;
            background: var(--surface-light);
            transition: 0.2s;
        }
        .user-area a:hover {
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
            min-width: 160px;
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
        .welcome-message {
            background: var(--surface-light);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 5px solid var(--accent);
        }
        .welcome-message p {
            color: var(--text-soft);
        }
        .welcome-message a {
            color: var(--accent);
            text-decoration: none;
        }
        .qq-group {
            background: var(--surface-light);
            padding: 10px 20px;
            border-radius: 12px;
            display: inline-block;
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }

        /* 主布局 */
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

        /* 帖子列表 */
        .post-list {
            background: var(--surface);
            border-radius: 12px;
            border: 1px solid var(--border);
            overflow: hidden;
        }
        .post-item {
            display: flex;
            padding: 20px;
            border-bottom: 1px solid var(--border);
            transition: 0.2s;
        }
        .post-item:hover {
            background: var(--surface-light);
        }
        .post-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient);
            margin-right: 20px;
            flex-shrink: 0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        .post-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .post-content {
            flex: 1;
        }
        .post-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .post-author {
            font-weight: 600;
            color: var(--accent);
        }
        .post-time {
            color: var(--text-hint);
            font-size: 0.85rem;
        }
        .post-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .post-title a {
            color: var(--text);
            text-decoration: none;
        }
        .post-title a:hover {
            color: var(--accent);
        }
        .pinned-tag {
            background: var(--accent);
            color: var(--primary-dark);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 30px;
        }
        .post-excerpt {
            color: var(--text-soft);
            margin-bottom: 12px;
            font-size: 0.95rem;
        }
        .post-meta {
            display: flex;
            gap: 20px;
            color: var(--text-hint);
            font-size: 0.85rem;
        }

        /* 右侧统计卡片 */
        .stats-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
        }
        .stats-header {
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }
        .stats-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--accent);
        }
        .stats-grid {
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .stat-item {
            flex: 1;
        }
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary);
            line-height: 1.2;
        }
        .stat-label {
            color: var(--text-hint);
            font-size: 0.9rem;
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
            .top-bar {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- 头部 -->
        <div class="header">
            <div class="top-bar">
                <div class="logo">lv8girl<span>绿坝娘</span></div>
                <div class="user-area">
                    <?php if ($isLoggedIn): ?>
                        <a href="messages.php">私信<?php if ($unread_count > 0): ?><span style="background:#ff6b6b; color:white; border-radius:50%; padding:2px 6px; font-size:0.7rem; margin-left:5px;"><?php echo $unread_count; ?></span><?php endif; ?></a>
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
            <div class="welcome-message">
                <p>欢迎来到 lv8girl 论坛，一个 ACG 爱好者的聚集地。</p>
            </div>
            <div class="qq-group">
                🍀 绿坝娘 · 守护你的二次元
            </div>
        </div>

        <!-- 主内容 -->
        <div class="main-layout">
            <!-- 左侧帖子列表 -->
            <div class="content-left">
                <div class="post-list">
                    <?php if (empty($posts)): ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-hint);">
                            暂无帖子，快去发表第一篇吧！
                        </div>
                    <?php else: ?>
                        <?php foreach ($posts as $post): 
                            $avatar_html = '';
                            if ($post['avatar'] && file_exists($post['avatar'])) {
                                $avatar_html = '<img src="'.htmlspecialchars($post['avatar']).'" alt="avatar">';
                            } else {
                                $initial = strtoupper(mb_substr($post['username'], 0, 1));
                                $avatar_html = '<span>'.$initial.'</span>';
                            }
                            $excerpt = mb_substr(strip_tags($post['content']), 0, 100) . (mb_strlen($post['content']) > 100 ? '...' : '');
                        ?>
                        <div class="post-item">
                            <div class="post-avatar">
                                <?php echo $avatar_html; ?>
                            </div>
                            <div class="post-content">
                                <div class="post-header">
                                    <span class="post-author"><?php echo htmlspecialchars($post['username']); ?></span>
                                    <span class="post-time"><?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></span>
                                </div>
                                <div class="post-title">
                                    <?php if ($post['is_pinned']): ?>
                                        <span class="pinned-tag">📌 置顶</span>
                                    <?php endif; ?>
                                    <a href="post.php?id=<?php echo $post['id']; ?>">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                </div>
                                <div class="post-excerpt">
                                    <?php echo htmlspecialchars($excerpt); ?>
                                </div>
                                <div class="post-meta">
                                    <span>👍 <?php echo number_format($post['like_count']); ?></span>
                                    <span>💬 <?php echo number_format($post['comment_count']); ?></span>
                                    <span>👁️ <?php echo number_format($post['views']); ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 右侧边栏 - 统计卡片 -->
            <div class="content-right">
                <div class="stats-card">
                    <div class="stats-header">
                        <span class="stats-title">📊 论坛统计</span>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($post_count); ?></div>
                            <div class="stat-label">帖子总数</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo number_format($user_count); ?></div>
                            <div class="stat-label">注册用户</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $online_count; ?></div>
                            <div class="stat-label">实时在线</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 页脚 -->
        <div class="footer">
            <div>© 2025 lv8girl · 绿坝娘二次元论坛</div>
            <div>
                <a href="about.php">关于</a>
                <a href="rules.php">站规</a>
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

