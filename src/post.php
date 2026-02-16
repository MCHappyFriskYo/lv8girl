<?php
session_start();

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

// 获取帖子ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}
$post_id = (int)$_GET['id'];

// 获取帖子信息及作者
$stmt = $pdo->prepare("
    SELECT d.*, u.username, u.avatar 
    FROM discussions d 
    JOIN users u ON d.user_id = u.id 
    WHERE d.id = ?
");
$stmt->execute([$post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    die('帖子不存在');
}

// 获取点赞数
$stmt = $pdo->prepare("SELECT COUNT(*) FROM likes WHERE post_id = ?");
$stmt->execute([$post_id]);
$like_count = $stmt->fetchColumn();

// 处理点赞
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'like') {
    if (!isset($_SESSION['user_id'])) {
        // 未登录，可以设置错误信息，但为了简化，直接刷新
        header("Location: post.php?id=$post_id");
        exit;
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO likes (post_id, user_id) VALUES (?, ?)");
            $stmt->execute([$post_id, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            // 重复点赞忽略
        }
        header("Location: post.php?id=$post_id");
        exit;
    }
}

// 处理评论或回复
$comment_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'comment' || $_POST['action'] === 'reply')) {
    if (!isset($_SESSION['user_id'])) {
        $comment_error = '请先登录后再评论';
    } else {
        $content = trim($_POST['content'] ?? '');
        $parent_id = isset($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        if (empty($content)) {
            $comment_error = '评论内容不能为空';
        } else {
            $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content, parent_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$post_id, $_SESSION['user_id'], $content, $parent_id]);
            header("Location: post.php?id=$post_id");
            exit;
        }
    }
}

// 获取收藏状态
$is_favorited = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND post_id = ?");
    $stmt->execute([$_SESSION['user_id'], $post_id]);
    $is_favorited = $stmt->fetch() ? true : false;
}

$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';

// 获取所有评论（用于构建树）
$stmt = $pdo->prepare("
    SELECT c.*, u.username, u.avatar 
    FROM comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.post_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$post_id]);
$comments_flat = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 构建评论树（按 parent_id 分组）
$comments_by_parent = [];
foreach ($comments_flat as $comment) {
    $comments_by_parent[$comment['parent_id'] ?? 0][] = $comment;
}

// 递归函数输出评论树
function renderComments($parent_id, $comments_by_parent, $current_user_id) {
    if (!isset($comments_by_parent[$parent_id])) {
        return;
    }
    foreach ($comments_by_parent[$parent_id] as $comment) {
        ?>
        <div class="comment-item" style="margin-left: <?php echo $parent_id ? 40 : 0; ?>px;">
            <div class="comment-avatar">
                <?php if ($comment['avatar'] && file_exists($comment['avatar'])): ?>
                    <img src="<?php echo htmlspecialchars($comment['avatar']); ?>" alt="avatar">
                <?php else: ?>
                    <?php echo strtoupper(mb_substr($comment['username'], 0, 1)); ?>
                <?php endif; ?>
            </div>
            <div class="comment-content">
                <div class="comment-header">
                    <span class="comment-author"><?php echo htmlspecialchars($comment['username']); ?></span>
                    <span class="comment-time"><?php echo date('Y-m-d H:i', strtotime($comment['created_at'])); ?></span>
                </div>
                <div class="comment-text"><?php echo nl2br(htmlspecialchars($comment['content'])); ?></div>
                <div class="comment-actions">
                    <a href="#" onclick="showReplyForm(<?php echo $comment['id']; ?>); return false;" style="color: var(--primary); font-size: 0.85rem; margin-right: 15px;">回复</a>
                    <?php if ($current_user_id == $comment['user_id'] || $current_user_id == 1): ?>
                        <a href="delete_comment.php?id=<?php echo $comment['id']; ?>&post_id=<?php echo $_GET['id']; ?>" onclick="return confirm('确定删除此评论吗？')" style="color: var(--accent-pink); font-size: 0.85rem;">删除</a>
                    <?php endif; ?>
                </div>

                <!-- 回复表单（初始隐藏） -->
                <div id="reply-form-<?php echo $comment['id']; ?>" style="display: none; margin-top: 15px;">
                    <form method="post" style="display: flex; gap: 10px;">
                        <input type="hidden" name="action" value="reply">
                        <input type="hidden" name="parent_id" value="<?php echo $comment['id']; ?>">
                        <textarea name="content" placeholder="写下你的回复..." style="flex: 1; background: var(--bg-nav); border: 1px solid var(--border); border-radius: 20px; padding: 8px 12px; color: var(--text-primary);" required></textarea>
                        <button type="submit" style="background: linear-gradient(135deg, var(--primary), var(--accent-blue)); border: none; border-radius: 30px; padding: 8px 20px; color: white; font-weight: bold; cursor: pointer;">回复</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        // 递归显示子评论
        renderComments($comment['id'], $comments_by_parent, $current_user_id);
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($post['title']); ?> - lv8girl</title>
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
            padding: 0;
            font-weight: var(--font-weight-regular);
            line-height: 1.5;
            color: var(--text-primary);
        }

        .app-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            background: transparent;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* ===== 顶部导航栏 ===== */
        .top-nav {
            background: var(--bg-nav);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            transition: background-color 0.3s, border-color 0.3s;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 40px;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: var(--font-weight-black);
            background: linear-gradient(135deg, var(--primary), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 1px;
            white-space: nowrap;
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

        .nav-menu {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .nav-menu a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 8px 12px;
            font-size: 1rem;
            font-weight: var(--font-weight-regular);
            transition: all 0.2s;
            border-radius: 30px;
        }

        .nav-menu a:hover {
            background: linear-gradient(135deg, var(--primary-light), var(--accent-blue));
            color: white;
        }

        .nav-menu a.active {
            background: var(--primary);
            color: white;
            font-weight: var(--font-weight-bold);
            box-shadow: 0 4px 10px rgba(61, 158, 74, 0.3);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .search-box {
            display: flex;
            align-items: center;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 40px;
            padding: 4px 4px 4px 20px;
            width: 260px;
            transition: all 0.2s;
        }

        .search-box:focus-within {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(61, 158, 74, 0.2);
        }

        .search-box input {
            background: transparent;
            border: none;
            outline: none;
            color: var(--text-primary);
            font-size: 0.9rem;
            width: 100%;
            font-weight: var(--font-weight-light);
        }

        .search-box input::placeholder {
            color: var(--text-hint);
        }

        .search-box button {
            background: linear-gradient(135deg, var(--primary), var(--accent-blue));
            border: none;
            border-radius: 40px;
            padding: 8px 20px;
            color: white;
            font-weight: var(--font-weight-bold);
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
        }

        .search-box button:hover {
            background: linear-gradient(135deg, var(--primary-light), var(--accent-purple));
            transform: scale(1.02);
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
        }

        .user-actions a {
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: var(--font-weight-regular);
            padding: 6px 16px;
            border-radius: 30px;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            transition: all 0.2s;
        }

        .user-actions a:hover {
            background: linear-gradient(135deg, var(--secondary), var(--accent-pink));
            color: var(--primary-dark);
            border-color: transparent;
        }

        /* 用户下拉菜单 */
        .user-menu {
            position: relative;
            cursor: pointer;
        }

        .user-name {
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 30px;
            padding: 6px 18px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            transition: all 0.2s;
        }

        .user-name:hover {
            background: linear-gradient(135deg, var(--secondary), var(--accent-pink));
            color: var(--primary-dark);
            border-color: transparent;
        }

        .dropdown {
            position: absolute;
            top: 120%;
            right: 0;
            background: var(--bg-surface);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
            min-width: 180px;
            overflow: hidden;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s;
            z-index: 10;
        }

        .user-menu:hover .dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown a {
            display: block;
            padding: 12px 20px;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.95rem;
            transition: background 0.2s;
            border-bottom: 1px solid var(--border-light);
        }

        .dropdown a:last-child {
            border-bottom: none;
        }

        .dropdown a:hover {
            background: linear-gradient(135deg, var(--primary-light), var(--accent-blue));
            color: white;
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

        /* ===== 主内容区：两栏布局 ===== */
        .main-layout {
            display: flex;
            padding: 30px 32px;
            gap: 30px;
            flex: 1;
        }

        .content-flow {
            flex: 2;
            min-width: 0;
        }

        .right-sidebar {
            width: 340px;
            flex-shrink: 0;
        }

        /* ===== 帖子详情样式 ===== */
        .post-detail {
            background: var(--bg-surface);
            border-radius: 30px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 30px;
            margin-bottom: 30px;
        }

        .post-header {
            margin-bottom: 20px;
        }

        .post-header h1 {
            font-size: 2rem;
            font-weight: var(--font-weight-black);
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .post-meta {
            display: flex;
            align-items: center;
            gap: 20px;
            color: var(--text-hint);
            font-size: 0.9rem;
            flex-wrap: wrap;
        }

        .post-author {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .author-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-light), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .post-image {
            margin: 20px 0;
            max-width: 100%;
            border-radius: 20px;
            overflow: hidden;
        }

        .post-image img {
            width: 100%;
            max-height: 500px;
            object-fit: contain;
            background: #f0f0f0;
        }

        .post-content {
            color: var(--text-primary);
            font-size: 1.1rem;
            line-height: 1.7;
            margin-bottom: 30px;
            white-space: pre-wrap;
        }

        .post-actions-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 20px 0;
            border-top: 1px solid var(--border-light);
            border-bottom: 1px solid var(--border-light);
            flex-wrap: wrap;
        }

        .like-btn {
            background: linear-gradient(135deg, var(--accent-pink), #ff4d6d);
            border: none;
            border-radius: 40px;
            padding: 10px 25px;
            color: white;
            font-weight: var(--font-weight-bold);
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .like-btn:hover {
            transform: scale(1.02);
            box-shadow: var(--hover-shadow);
        }

        .favorite-btn {
            background: linear-gradient(135deg, var(--secondary), var(--accent-pink));
            border: none;
            border-radius: 40px;
            padding: 10px 25px;
            color: white;
            font-weight: var(--font-weight-bold);
            cursor: pointer;
            transition: all 0.2s;
        }

        .favorite-btn.favorited {
            background: linear-gradient(135deg, #ff4d6d, #ff7b9c);
        }

        .favorite-btn:hover {
            transform: scale(1.02);
            box-shadow: var(--hover-shadow);
        }

        .like-count {
            font-size: 1.1rem;
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            margin-left: 10px;
        }

        .error-message {
            color: var(--accent-pink);
            font-weight: var(--font-weight-bold);
            margin-top: 5px;
        }

        /* 评论区 */
        .comments-section {
            background: var(--bg-surface);
            border-radius: 30px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            padding: 30px;
        }

        .comments-section h2 {
            font-size: 1.5rem;
            font-weight: var(--font-weight-black);
            color: var(--text-primary);
            margin-bottom: 20px;
        }

        .comment-form {
            margin-bottom: 30px;
        }

        .comment-form textarea {
            width: 100%;
            background: var(--bg-nav);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 15px;
            font-size: 1rem;
            color: var(--text-primary);
            resize: vertical;
            min-height: 100px;
            margin-bottom: 10px;
        }

        .comment-form button {
            background: linear-gradient(135deg, var(--primary), var(--accent-blue));
            border: none;
            border-radius: 40px;
            padding: 10px 30px;
            color: white;
            font-weight: var(--font-weight-bold);
            cursor: pointer;
            transition: all 0.2s;
        }

        .comment-form button:hover {
            background: linear-gradient(135deg, var(--primary-light), var(--accent-purple));
            transform: scale(1.02);
        }

        .comment-item {
            display: flex;
            gap: 15px;
            padding: 20px 0;
            border-bottom: 1px solid var(--border-light);
        }

        .comment-item:last-child {
            border-bottom: none;
        }

        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
            background: linear-gradient(135deg, var(--primary-light), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .comment-author {
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
        }

        .comment-time {
            font-size: 0.8rem;
            color: var(--text-hint);
        }

        .comment-text {
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .comment-actions {
            font-size: 0.85rem;
        }

        .comment-actions a {
            text-decoration: none;
        }

        .no-comments {
            text-align: center;
            color: var(--text-hint);
            padding: 30px 0;
        }

        /* 右侧边栏卡片 */
        .side-card {
            background: var(--bg-surface);
            border-radius: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
            margin-bottom: 24px;
            transition: all 0.3s;
        }

        .side-card:hover {
            box-shadow: var(--hover-shadow);
            border-color: var(--primary);
        }

        .side-header {
            padding: 16px 18px;
            background: linear-gradient(135deg, var(--bg-nav), var(--bg-surface));
            border-bottom: 1px solid var(--border);
            font-size: 1.1rem;
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .rank-list {
            padding: 8px 0;
        }

        .rank-item {
            display: flex;
            align-items: center;
            padding: 12px 18px;
            border-bottom: 1px solid var(--border-light);
            transition: background 0.2s;
        }

        .rank-item:hover {
            background: var(--bg-surface);
        }

        .rank-index {
            width: 30px;
            height: 30px;
            border-radius: 8px;
            background: linear-gradient(135deg, var(--border-light), var(--border));
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: var(--font-weight-bold);
            color: var(--text-secondary);
            margin-right: 12px;
        }

        .rank-index.top-3 {
            background: linear-gradient(135deg, var(--secondary), var(--accent-pink));
            color: var(--primary-dark);
        }

        .rank-content {
            flex: 1;
        }

        .rank-title {
            font-weight: var(--font-weight-regular);
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .rank-title a {
            color: inherit;
            text-decoration: none;
        }

        .rank-title a:hover {
            color: var(--primary);
        }

        .rank-meta {
            font-size: 0.75rem;
            color: var(--text-hint);
        }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            padding: 16px;
        }

        .image-item {
            background: linear-gradient(135deg, var(--primary-light), var(--accent-purple));
            aspect-ratio: 1/1;
            border-radius: 12px;
            overflow: hidden;
        }

        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .footer {
            margin-top: 40px;
            padding: 24px 32px;
            border-top: 1px solid var(--border);
            color: var(--text-hint);
            font-size: 0.9rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-links a {
            color: var(--text-hint);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        /* 响应式 */
        @media screen and (max-width: 1024px) {
            .main-layout {
                flex-direction: column;
            }
            .right-sidebar {
                width: 100%;
            }
        }

        @media screen and (max-width: 768px) {
            .top-nav {
                flex-direction: column;
                height: auto;
                padding: 16px;
                gap: 16px;
            }
            .nav-left {
                width: 100%;
                justify-content: space-between;
            }
            .nav-right {
                width: 100%;
                justify-content: flex-end;
            }
            .search-box {
                width: 100%;
            }
        }
    </style>
    <script>
        function showReplyForm(commentId) {
            var form = document.getElementById('reply-form-' + commentId);
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
            } else {
                form.style.display = 'none';
            }
        }
    </script>
</head>
<body>
    <div class="app-wrapper">
        <!-- 顶部导航栏（与主页一致） -->
        <nav class="top-nav">
            <div class="nav-left">
                <div class="logo">lv8girl</div>
                <div class="nav-menu">
                    <a href="index.php">首页</a>
                    <a href="#">番剧</a>
                    <a href="#">漫画</a>
                    <a href="#">游戏</a>
                    <a href="#">图库</a>
                    <a href="#">讨论</a>
                </div>
            </div>
            <div class="nav-right">
                <div class="search-box">
                    <input type="text" placeholder="搜索...">
                    <button>搜索</button>
                </div>
                <div class="user-actions">
                    <?php if ($is_logged_in): ?>
                        <div class="user-menu">
                            <span class="user-name"><?php echo htmlspecialchars($username); ?> ▼</span>
                            <div class="dropdown">
                                <?php if ($current_user_id == 1): ?>
                                    <a href="admin.php">管理面板</a>
                                <?php endif; ?>
                                <a href="profile.php">个人主页</a>
                                <a href="my_posts.php">我的帖子</a>
                                <a href="favorites.php">收藏夹</a>
                                <a href="#">设置</a>
                                <a href="logout.php">登出</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php">登录</a>
                        <a href="register.html">注册</a>
                    <?php endif; ?>
                    <div class="theme-toggle" id="themeToggle">🌓</div>
                </div>
            </div>
        </nav>

        <!-- 主内容区：两栏布局 -->
        <div class="main-layout">
            <!-- 左侧内容流 -->
            <div class="content-flow">
                <!-- 帖子详情 -->
                <div class="post-detail">
                    <div class="post-header">
                        <h1><?php echo htmlspecialchars($post['title']); ?></h1>
                        <div class="post-meta">
                            <div class="post-author">
                                <div class="author-avatar">
                                    <?php if ($post['avatar'] && file_exists($post['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($post['avatar']); ?>" alt="avatar">
                                    <?php else: ?>
                                        <?php echo strtoupper(mb_substr($post['username'], 0, 1)); ?>
                                    <?php endif; ?>
                                </div>
                                <span><?php echo htmlspecialchars($post['username']); ?></span>
                            </div>
                            <span>发布于：<?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></span>
                        </div>
                    </div>

                    <?php if ($post['image_path'] && file_exists($post['image_path'])): ?>
                        <div class="post-image">
                            <img src="<?php echo htmlspecialchars($post['image_path']); ?>" alt="post image">
                        </div>
                    <?php endif; ?>

                    <div class="post-content">
                        <?php echo nl2br(htmlspecialchars($post['content'])); ?>
                    </div>

                    <!-- 操作栏：点赞、收藏、计数 -->
                    <div class="post-actions-bar">
                        <!-- 点赞表单 -->
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="action" value="like">
                            <button type="submit" class="like-btn">👍 点赞</button>
                        </form>

                        <!-- 收藏表单（提交到 favorite.php） -->
                        <form method="post" action="favorite.php" style="display: inline;">
                            <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">
                            <input type="hidden" name="action" value="<?php echo $is_favorited ? 'unfavorite' : 'favorite'; ?>">
                            <button type="submit" class="favorite-btn <?php echo $is_favorited ? 'favorited' : ''; ?>">
                                <?php echo $is_favorited ? '❤️ 已收藏' : '🤍 收藏'; ?>
                            </button>
                        </form>

                        <span class="like-count"><?php echo $like_count; ?> 人点赞</span>
                    </div>
                </div>

                <!-- 评论区 -->
                <div class="comments-section">
                    <h2>评论 (<?php echo count($comments_flat); ?>)</h2>

                    <!-- 顶级评论表单 -->
                    <?php if ($is_logged_in): ?>
                        <form method="post" class="comment-form">
                            <input type="hidden" name="action" value="comment">
                            <textarea name="content" placeholder="写下你的评论..." required></textarea>
                            <?php if ($comment_error): ?>
                                <div class="error-message"><?php echo htmlspecialchars($comment_error); ?></div>
                            <?php endif; ?>
                            <button type="submit">发表评论</button>
                        </form>
                    <?php else: ?>
                        <p style="color: var(--text-hint); margin-bottom: 20px;"><a href="login.php" style="color: var(--primary);">登录</a>后即可评论</p>
                    <?php endif; ?>

                    <!-- 评论列表树 -->
                    <div class="comment-tree">
                        <?php
                        if (empty($comments_by_parent[0])) {
                            echo '<div class="no-comments">暂无评论，快来抢沙发吧～</div>';
                        } else {
                            renderComments(0, $comments_by_parent, $current_user_id);
                        }
                        ?>
                    </div>
                </div>
            </div>

            <!-- 右侧边栏（与主页保持一致） -->
            <div class="right-sidebar">
                <!-- 卡片：24小时热门 -->
                <div class="side-card">
                    <div class="side-header">📈 24小时热门</div>
                    <div class="rank-list">
                        <?php
                        // 获取24小时内发布的帖子，按点赞数排序
                        $stmt = $pdo->prepare("
                            SELECT d.id, d.title, COUNT(l.id) as like_count
                            FROM discussions d
                            LEFT JOIN likes l ON d.id = l.post_id
                            WHERE d.created_at >= NOW() - INTERVAL 1 DAY
                            GROUP BY d.id
                            ORDER BY like_count DESC, d.created_at DESC
                            LIMIT 5
                        ");
                        $stmt->execute();
                        $hot_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        // 如果24小时内没有帖子，则显示全局热门
                        if (empty($hot_posts)) {
                            $stmt = $pdo->prepare("
                                SELECT d.id, d.title, COUNT(l.id) as like_count
                                FROM discussions d
                                LEFT JOIN likes l ON d.id = l.post_id
                                GROUP BY d.id
                                ORDER BY like_count DESC, d.created_at DESC
                                LIMIT 5
                            ");
                            $stmt->execute();
                            $hot_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        }

                        $rank = 1;
                        foreach ($hot_posts as $hot):
                        ?>
                        <div class="rank-item">
                            <span class="rank-index <?php echo $rank <= 3 ? 'top-3' : ''; ?>"><?php echo $rank++; ?></span>
                            <div class="rank-content">
                                <div class="rank-title">
                                    <a href="post.php?id=<?php echo $hot['id']; ?>">
                                        <?php echo htmlspecialchars($hot['title']); ?>
                                    </a>
                                </div>
                                <div class="rank-meta"><?php echo $hot['like_count']; ?> 点赞</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- 卡片：最新图片（头像和讨论图片） -->
                <div class="side-card">
                    <div class="side-header">📷 最新图片</div>
                    <div class="image-grid">
                        <?php
                        $stmt = $pdo->prepare("
                            (SELECT 'avatar' as type, avatar as path, created_at FROM users WHERE avatar IS NOT NULL)
                            UNION ALL
                            (SELECT 'discussion' as type, image_path as path, created_at FROM discussions WHERE image_path IS NOT NULL)
                            ORDER BY created_at DESC
                            LIMIT 4
                        ");
                        $stmt->execute();
                        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (count($images) > 0):
                            foreach ($images as $img):
                                $file_path = $img['path'];
                                if (file_exists($file_path)):
                        ?>
                        <div class="image-item">
                            <img src="<?php echo htmlspecialchars($file_path); ?>" alt="最新图片">
                        </div>
                        <?php
                                else:
                        ?>
                        <div class="image-item" style="background: var(--primary-light); display: flex; align-items: center; justify-content: center; color: white;">
                            📷
                        </div>
                        <?php
                                endif;
                            endforeach;
                        else:
                            for ($i = 0; $i < 4; $i++):
                        ?>
                        <div class="image-item" style="background: var(--primary-light); display: flex; align-items: center; justify-content: center; color: white;">
                            📷
                        </div>
                        <?php
                            endfor;
                        endif;
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 底部 -->
        <footer class="footer">
            <div>© 2025 lv8girl · 绿坝娘二次元社区</div>
            <div class="footer-links">
                <a href="#">关于</a>
                <a href="#">帮助</a>
                <a href="#">隐私</a>
                <a href="#">投稿</a>
            </div>
        </footer>
    </div>

    <script>
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        themeToggle.addEventListener('click', () => {
            body.classList.toggle('dark-mode');
            themeToggle.textContent = body.classList.contains('dark-mode') ? '☀️' : '🌓';
        });
    </script>
</body>
</html>