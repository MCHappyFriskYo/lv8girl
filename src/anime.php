<?php
session_start();
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: anime_list.php');
    exit;
}
$anime_id = (int)$_GET['id'];

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

// 获取新番信息
$stmt = $pdo->prepare("SELECT * FROM anime WHERE id = ?");
$stmt->execute([$anime_id]);
$anime = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$anime) {
    die('新番不存在');
}

// 获取关联的讨论帖子
$stmt = $pdo->prepare("
    SELECT d.*, u.username, u.avatar,
           (SELECT COUNT(*) FROM likes WHERE post_id = d.id) as like_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = d.id) as comment_count
    FROM discussions d
    JOIN users u ON d.user_id = u.id
    WHERE d.anime_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$anime_id]);
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';
$current_user_id = $_SESSION['user_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($anime['title']); ?> - lv8girl</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Noto Sans SC',sans-serif; }
        :root { --primary:#3d9e4a; --primary-dark:#2e7d32; --primary-light:#6abf6e; --secondary:#ffb347; --accent-blue:#5a9cff; --accent-purple:#b47aff; --accent-pink:#ff7b9c; --bg-body:linear-gradient(145deg,#f0f7f0 0%,#e6f0e6 100%); --bg-surface:#ffffff; --bg-nav:rgba(255,255,255,0.9); --text-primary:#2c3e2c; --text-secondary:#5f6b5f; --text-hint:#8f9f8f; --border-light:#e6ede6; --border:#d0e0d0; --shadow:0 8px 20px rgba(0,0,0,0.06); --hover-shadow:0 12px 28px rgba(61,158,74,0.2); --font-weight-light:400; --font-weight-regular:500; --font-weight-bold:700; --font-weight-black:900; }
        body.dark-mode { --primary:#6b8e6b; --primary-dark:#4a6b4a; --primary-light:#8aad8a; --secondary:#d9a066; --accent-blue:#6688aa; --accent-purple:#8a7a9c; --accent-pink:#b57a8a; --bg-body:linear-gradient(145deg,#1e261e 0%,#232d23 100%); --bg-surface:#2c342c; --bg-nav:#2c342ccc; --text-primary:#e0e8e0; --text-secondary:#b0bcb0; --text-hint:#8a958a; --border-light:#3a453a; --border:#4d5a4d; --shadow:0 8px 20px rgba(0,0,0,0.5); --hover-shadow:0 12px 28px rgba(107,142,107,0.3); }
        body { background:var(--bg-body); min-height:100vh; transition:background 0.3s; }
        .app-wrapper { max-width:1200px; margin:0 auto; display:flex; flex-direction:column; min-height:100vh; }
        .top-nav { background:var(--bg-nav); backdrop-filter:blur(10px); border-bottom:1px solid var(--border); padding:0 32px; height:70px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
        .nav-left { display:flex; align-items:center; gap:40px; }
        .logo { font-size:1.8rem; font-weight:var(--font-weight-black); background:linear-gradient(135deg,var(--primary),var(--accent-blue)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .logo span { font-size:0.8rem; background:var(--secondary); color:var(--primary-dark); padding:4px 10px; border-radius:30px; margin-left:10px; }
        .nav-menu { display:flex; gap:20px; }
        .nav-menu a { color:var(--text-primary); text-decoration:none; padding:8px 12px; border-radius:30px; transition:0.2s; }
        .nav-menu a:hover { background:linear-gradient(135deg,var(--primary-light),var(--accent-blue)); color:white; }
        .nav-right { display:flex; align-items:center; gap:20px; }
        .user-actions { display:flex; align-items:center; gap:15px; position:relative; }
        .user-actions a { color:var(--text-primary); text-decoration:none; padding:6px 16px; border-radius:30px; background:var(--bg-surface); border:1px solid var(--border); }
        .user-menu { position:relative; cursor:pointer; }
        .user-name { background:var(--bg-surface); border:1px solid var(--border); border-radius:30px; padding:6px 18px; font-weight:bold; }
        .dropdown { position:absolute; top:120%; right:0; background:var(--bg-surface); border:1px solid var(--border); border-radius:20px; min-width:180px; opacity:0; visibility:hidden; transition:0.3s; z-index:10; }
        .user-menu:hover .dropdown { opacity:1; visibility:visible; }
        .dropdown a { display:block; padding:12px 20px; text-decoration:none; border-bottom:1px solid var(--border-light); color:var(--text-primary); }
        .theme-toggle { background:var(--bg-surface); border:1px solid var(--border); width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; }

        .main-content { padding:30px 32px; flex:1; }
        .anime-header { background:var(--bg-surface); border-radius:30px; padding:30px; margin-bottom:30px; display:flex; gap:30px; flex-wrap:wrap; }
        .anime-cover { width:200px; height:250px; object-fit:cover; border-radius:20px; background:linear-gradient(135deg,var(--primary-light),var(--accent-purple)); }
        .anime-info { flex:1; }
        .anime-info h1 { font-size:2.2rem; font-weight:900; color:var(--text-primary); margin-bottom:15px; }
        .anime-meta { display:flex; gap:30px; color:var(--text-hint); margin-bottom:20px; }
        .anime-description { color:var(--text-secondary); line-height:1.7; margin-bottom:20px; }
        .anime-status { display:inline-block; background:var(--secondary); color:var(--primary-dark); padding:5px 20px; border-radius:40px; font-weight:bold; }
        .post-section { background:var(--bg-surface); border-radius:30px; padding:30px; }
        .post-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
        .post-header h2 { font-size:1.6rem; font-weight:900; background:linear-gradient(135deg,var(--primary),var(--accent-purple)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .post-list { list-style:none; }
        .post-item { display:flex; gap:20px; padding:20px; border-bottom:1px solid var(--border-light); }
        .post-item:last-child { border-bottom:none; }
        .post-avatar { width:50px; height:50px; border-radius:50%; overflow:hidden; background:linear-gradient(135deg,var(--primary-light),var(--accent-purple)); flex-shrink:0; }
        .post-avatar img { width:100%; height:100%; object-fit:cover; }
        .post-content { flex:1; }
        .post-title { font-size:1.2rem; font-weight:bold; color:var(--text-primary); margin-bottom:5px; }
        .post-title a { color:inherit; text-decoration:none; }
        .post-meta { display:flex; gap:20px; color:var(--text-hint); font-size:0.85rem; margin-bottom:8px; }
        .post-excerpt { color:var(--text-secondary); margin-bottom:8px; }
        .post-stats { display:flex; gap:20px; color:var(--text-hint); font-size:0.85rem; }
        .btn { background:linear-gradient(135deg,var(--primary),var(--accent-blue)); border:none; border-radius:40px; padding:8px 20px; color:white; font-weight:bold; text-decoration:none; display:inline-block; }
        .footer { margin-top:40px; padding:24px 32px; border-top:1px solid var(--border); color:var(--text-hint); text-align:center; }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <nav class="top-nav">
            <div class="nav-left">
                <div class="logo">lv8girl<span>新番</span></div>
                <div class="nav-menu">
                    <a href="index.php">首页</a>
                    <a href="anime_list.php">新番</a>
                    <a href="#">漫画</a>
                    <a href="#">游戏</a>
                    <a href="#">图库</a>
                </div>
            </div>
            <div class="nav-right">
                <div class="user-actions">
                    <?php if ($is_logged_in): ?>
                        <div class="user-menu">
                            <span class="user-name"><?php echo htmlspecialchars($username); ?> ▼</span>
                            <div class="dropdown">
                                <?php if ($current_user_id == 1): ?><a href="admin_anime.php">新番管理</a><?php endif; ?>
                                <a href="profile.php">个人主页</a>
                                <a href="my_posts.php">我的帖子</a>
                                <a href="favorites.php">收藏夹</a>
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

        <div class="main-content">
            <div class="anime-header">
                <img class="anime-cover" src="<?php echo $anime['cover'] && file_exists($anime['cover']) ? $anime['cover'] : 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%233d9e4a\' /%3E%3Ctext x=\'25\' y=\'65\' fill=\'white\' font-size=\'40\'%3E📷%3C/text%3E%3C/svg%3E'; ?>" alt="cover">
                <div class="anime-info">
                    <h1><?php echo htmlspecialchars($anime['title']); ?></h1>
                    <div class="anime-meta">
                        <span>季度：<?php echo htmlspecialchars($anime['season']); ?></span>
                        <span>首播：<?php echo $anime['broadcast_date'] ? date('Y-m-d', strtotime($anime['broadcast_date'])) : '未知'; ?></span>
                        <span class="anime-status"><?php echo $anime['status']; ?></span>
                    </div>
                    <div class="anime-description">
                        <?php echo nl2br(htmlspecialchars($anime['description'])); ?>
                    </div>
                    <a href="post_discussion.php?anime_id=<?php echo $anime['id']; ?>" class="btn">发表讨论</a>
                </div>
            </div>

            <div class="post-section">
                <div class="post-header">
                    <h2>讨论区 · <?php echo count($posts); ?> 个帖子</h2>
                </div>

                <?php if (empty($posts)): ?>
                    <div style="text-align:center; padding:40px; color:var(--text-hint);">暂无讨论，快来发表第一篇吧！</div>
                <?php else: ?>
                    <div class="post-list">
                        <?php foreach ($posts as $post): ?>
                            <div class="post-item">
                                <div class="post-avatar">
                                    <?php if ($post['avatar'] && file_exists($post['avatar'])): ?>
                                        <img src="<?php echo htmlspecialchars($post['avatar']); ?>" alt="avatar">
                                    <?php else: ?>
                                        <?php echo strtoupper(mb_substr($post['username'],0,1)); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="post-content">
                                    <div class="post-title"><a href="post.php?id=<?php echo $post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a></div>
                                    <div class="post-meta">
                                        <span>作者：<?php echo htmlspecialchars($post['username']); ?></span>
                                        <span>时间：<?php echo date('Y-m-d H:i', strtotime($post['created_at'])); ?></span>
                                    </div>
                                    <div class="post-excerpt">
                                        <?php echo htmlspecialchars(mb_substr(strip_tags($post['content']),0,100)) . (mb_strlen($post['content'])>100?'...':''); ?>
                                    </div>
                                    <div class="post-stats">
                                        <span>👍 <?php echo $post['like_count']; ?></span>
                                        <span>💬 <?php echo $post['comment_count']; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <footer class="footer">
            © 2025 lv8girl · 绿坝娘二次元社区
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