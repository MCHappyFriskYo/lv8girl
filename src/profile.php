<?php
session_start();

$host = 'db';
$dbname = 'lv8girl';
$db_user = 'lv8girl';
$db_pass = 'yourpasswd'; // 请修改为实际密码

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('数据库连接失败');
}

// 获取要查看的用户ID，默认为当前登录用户
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : ($_SESSION['user_id'] ?? 0);
if (!$user_id) {
    header('Location: index.php');
    exit;
}

// 处理签名更新（仅本人操作）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_signature']) && $user_id == ($_SESSION['user_id'] ?? 0)) {
    $signature = trim($_POST['signature'] ?? '');
    $stmt = $pdo->prepare("UPDATE users SET signature = ? WHERE id = ?");
    $stmt->execute([$signature, $user_id]);
    header("Location: profile.php?id=$user_id&updated=1");
    exit;
}

// 获取用户信息（包含签名）
$stmt = $pdo->prepare("SELECT id, username, email, avatar, created_at, role, signature FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user) {
    die('用户不存在');
}

// 获取用户帖子（只显示已审核的）
$stmt = $pdo->prepare("SELECT * FROM discussions WHERE user_id = ? AND status = 'approved' ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$post_count = count($posts);

// 获取未读私信数（用于导航栏显示）
$unread_count = 0;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM private_messages WHERE to_user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unread_count = $stmt->fetchColumn();
}

$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $user_id;
$current_user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';
$user_role = $_SESSION['user_role'] ?? '';

// 处理成功/错误消息
$message = '';
if (isset($_GET['success'])) {
    $message = '<div class="success-message">' . htmlspecialchars($_GET['success']) . '</div>';
} elseif (isset($_GET['error'])) {
    $message = '<div class="error-message">' . htmlspecialchars($_GET['error']) . '</div>';
} elseif (isset($_GET['updated'])) {
    $message = '<div class="success-message">个性签名已更新！</div>';
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($user['username']); ?>的个人主页 - lv8girl</title>
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
            max-width: 1000px;
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

        /* 消息提示 */
        .success-message, .error-message {
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .success-message {
            background: var(--primary-light);
            color: var(--primary-dark);
        }
        .error-message {
            background: #ff6b6b;
            color: white;
        }

        /* 个人资料卡片 */
        .profile-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: var(--gradient);
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2.5rem;
            font-weight: 600;
            flex-shrink: 0;
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
            font-size: 2rem;
            margin-bottom: 10px;
            color: var(--accent);
        }
        .profile-info p {
            color: var(--text-soft);
            margin-bottom: 5px;
        }
        .profile-signature {
            margin-top: 10px;
            padding: 10px 0;
            border-top: 1px solid var(--border-light);
            color: var(--text-soft);
            font-style: italic;
        }
        .profile-stats {
            display: flex;
            gap: 30px;
            margin-top: 15px;
        }
        .stat-item {
            text-align: center;
        }
        .stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
        }
        .stat-label {
            color: var(--text-hint);
            font-size: 0.85rem;
        }
        .edit-btn {
            display: inline-block;
            background: var(--gradient);
            color: white;
            border: none;
            border-radius: 30px;
            padding: 8px 25px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 15px;
            text-decoration: none;
        }
        .edit-btn:hover {
            transform: scale(1.02);
        }
        .avatar-upload-form, .signature-edit-form {
            margin-top: 15px;
            padding: 15px;
            background: var(--surface-light);
            border-radius: 12px;
            border: 1px dashed var(--border);
        }
        .file-input {
            margin-bottom: 10px;
        }
        .file-input input[type="file"] {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 8px 16px;
            width: 100%;
            color: var(--text);
        }
        .signature-edit-form textarea {
            width: 100%;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 10px 15px;
            color: var(--text);
            resize: vertical;
            min-height: 80px;
            margin-bottom: 10px;
        }

        /* 帖子列表 */
        .section-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .section-title h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--accent);
        }
        .post-list {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
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
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient);
            margin-right: 15px;
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
            margin-bottom: 5px;
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
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .post-title a {
            color: var(--text);
            text-decoration: none;
        }
        .post-title a:hover {
            color: var(--accent);
        }
        .post-excerpt {
            color: var(--text-soft);
            font-size: 0.95rem;
            margin-bottom: 10px;
        }
        .post-meta {
            display: flex;
            gap: 20px;
            color: var(--text-hint);
            font-size: 0.85rem;
        }
        .post-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn-delete {
            background: #ff6b6b;
            color: white;
            border: none;
            border-radius: 20px;
            padding: 4px 15px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-delete:hover {
            opacity: 0.8;
        }
        .no-posts {
            padding: 40px;
            text-align: center;
            color: var(--text-hint);
        }
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
    </style>
</head>
<body>
    <div class="container">
        <!-- 头部导航 -->
        <div class="header">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div class="nav-right">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="messages.php">私信<?php if ($unread_count > 0): ?><span style="background:#ff6b6b; color:white; border-radius:50%; padding:2px 6px; font-size:0.7rem; margin-left:5px;"><?php echo $unread_count; ?></span><?php endif; ?></a>
                    <div class="user-menu">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['username']); ?> ▼</span>
                        <div class="dropdown">
                            <?php if ($_SESSION['user_role'] === 'admin'): ?>
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

        <?php echo $message; ?>

        <!-- 用户资料卡片 -->
        <div class="profile-card">
            <div class="profile-avatar">
                <?php if ($user['avatar'] && file_exists($user['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($user['avatar']); ?>" alt="avatar">
                <?php else: ?>
                    <?php echo strtoupper(mb_substr($user['username'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['username']); ?></h1>
                <p>邮箱：<?php echo htmlspecialchars($user['email']); ?></p>
                <p>注册时间：<?php echo date('Y-m-d', strtotime($user['created_at'])); ?></p>
                <?php if (!empty($user['signature'])): ?>
                    <div class="profile-signature">
                        ✍️ <?php echo nl2br(htmlspecialchars($user['signature'])); ?>
                    </div>
                <?php endif; ?>
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $post_count; ?></div>
                        <div class="stat-label">帖子</div>
                    </div>
                </div>

                <?php if ($is_owner): ?>
                    <button class="edit-btn" onclick="toggleSection('avatarForm')">修改头像</button>
                    <button class="edit-btn" onclick="toggleSection('signatureForm')" style="margin-left:10px;">编辑个性签名</button>
                    
                    <!-- 头像上传表单 -->
                    <div id="avatarForm" style="display: none;" class="avatar-upload-form">
                        <form action="upload_avatar.php" method="post" enctype="multipart/form-data">
                            <div class="file-input">
                                <input type="file" name="avatar" accept="image/*" required>
                            </div>
                            <button type="submit" class="edit-btn">上传新头像</button>
                        </form>
                    </div>

                    <!-- 个性签名编辑表单 -->
                    <div id="signatureForm" style="display: none;" class="signature-edit-form">
                        <form method="post">
                            <input type="hidden" name="update_signature" value="1">
                            <textarea name="signature" placeholder="写一句个性签名吧……"><?php echo htmlspecialchars($user['signature'] ?? ''); ?></textarea>
                            <button type="submit" class="edit-btn">保存签名</button>
                        </form>
                    </div>
                <?php else: ?>
                    <a href="send_message.php?to=<?php echo $user['id']; ?>" class="edit-btn" style="margin-top:10px;">📩 发送私信</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 帖子列表 -->
        <div class="section-title">
            <h2><?php echo $is_owner ? '我的帖子' : htmlspecialchars($user['username']) . '的帖子'; ?></h2>
        </div>

        <?php if (empty($posts)): ?>
            <div class="no-posts">
                <p>暂无帖子</p>
                <?php if ($is_owner): ?>
                    <p><a href="post_discussion.php" style="color: var(--accent);">去发表第一篇</a></p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="post-list">
                <?php foreach ($posts as $post): 
                    $avatar = $user['avatar'] && file_exists($user['avatar']) ? '<img src="'.htmlspecialchars($user['avatar']).'">' : strtoupper(mb_substr($user['username'],0,1));
                    $excerpt = mb_substr(strip_tags($post['content']), 0, 100) . (mb_strlen($post['content']) > 100 ? '...' : '');
                ?>
                <div class="post-item">
                    <div class="post-avatar">
                        <?php echo $avatar; ?>
                    </div>
                    <div class="post-content">
                        <div class="post-header">
                            <span class="post-author"><?php echo htmlspecialchars($user['username']); ?></span>
                            <span class="post-time"><?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></span>
                        </div>
                        <div class="post-title">
                            <a href="post.php?id=<?php echo $post['id']; ?>">
                                <?php echo htmlspecialchars($post['title']); ?>
                            </a>
                        </div>
                        <div class="post-excerpt"><?php echo htmlspecialchars($excerpt); ?></div>
                        <div class="post-meta">
                            <span>👍 0</span>
                            <span>💬 0</span>
                        </div>
                        <?php if ($is_owner): ?>
                            <div class="post-actions">
                                <a href="delete_post.php?id=<?php echo $post['id']; ?>" class="btn-delete" onclick="return confirm('确定删除？')">删除</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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

        function toggleSection(id) {
            var form = document.getElementById(id);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</body>
</html>
