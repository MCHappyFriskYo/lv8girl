<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

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

// 获取用户收藏的帖子，连表获取帖子信息和作者信息
$stmt = $pdo->prepare("
    SELECT d.*, u.username, u.avatar,
           (SELECT COUNT(*) FROM likes WHERE post_id = d.id) as like_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = d.id) as comment_count
    FROM favorites f
    JOIN discussions d ON f.post_id = d.id
    JOIN users u ON d.user_id = u.id
    WHERE f.user_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$user_id]);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的收藏 - lv8girl</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700;900&display=swap" rel="stylesheet">
    <style>
        /* 复制 index.php 中的基础样式，或直接引入公共样式文件 */
        /* 这里仅保留必要样式，实际使用时建议统一管理 */
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Noto Sans SC', sans-serif; }
        :root { --primary:#3d9e4a; --primary-dark:#2e7d32; --primary-light:#6abf6e; --secondary:#ffb347; --accent-blue:#5a9cff; --accent-purple:#b47aff; --accent-pink:#ff7b9c; --bg-body:linear-gradient(145deg,#f0f7f0 0%,#e6f0e6 100%); --bg-surface:#ffffff; --bg-nav:rgba(255,255,255,0.9); --text-primary:#2c3e2c; --text-secondary:#5f6b5f; --text-hint:#8f9f8f; --border-light:#e6ede6; --border:#d0e0d0; --shadow:0 8px 20px rgba(0,0,0,0.06); --hover-shadow:0 12px 28px rgba(61,158,74,0.2); --font-weight-light:400; --font-weight-regular:500; --font-weight-bold:700; --font-weight-black:900; }
        body.dark-mode { --primary:#6b8e6b; --primary-dark:#4a6b4a; --primary-light:#8aad8a; --secondary:#d9a066; --accent-blue:#6688aa; --accent-purple:#8a7a9c; --accent-pink:#b57a8a; --bg-body:linear-gradient(145deg,#1e261e 0%,#232d23 100%); --bg-surface:#2c342c; --bg-nav:#2c342ccc; --text-primary:#e0e8e0; --text-secondary:#b0bcb0; --text-hint:#8a958a; --border-light:#3a453a; --border:#4d5a4d; --shadow:0 8px 20px rgba(0,0,0,0.5); --hover-shadow:0 12px 28px rgba(107,142,107,0.3); }
        body { background:var(--bg-body); transition:background 0.3s; color:var(--text-primary); }
        .container { max-width:1200px; margin:0 auto; padding:20px; }
        .mini-nav { display:flex; align-items:center; justify-content:space-between; margin-bottom:30px; }
        .logo { font-size:1.8rem; font-weight:var(--font-weight-black); background:linear-gradient(135deg,var(--primary),var(--accent-blue)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .logo span { font-size:0.8rem; background:var(--secondary); color:var(--primary-dark); padding:4px 10px; border-radius:30px; margin-left:10px; }
        .theme-toggle { background:var(--bg-surface); border:1px solid var(--border); color:var(--text-primary); font-size:1.3rem; width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; }
        h1 { font-size:2rem; font-weight:var(--font-weight-black); background:linear-gradient(135deg,var(--primary),var(--accent-purple)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; margin-bottom:30px; }
        .card-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:24px; }
        .card { background:var(--bg-surface); border-radius:20px; border:1px solid var(--border); overflow:hidden; box-shadow:var(--shadow); transition:all 0.3s; }
        .card:hover { transform:translateY(-6px); box-shadow:var(--hover-shadow); border-color:var(--primary); }
        .card-cover { width:100%; height:140px; background:linear-gradient(135deg,var(--primary-light),var(--accent-blue)); display:flex; align-items:center; justify-content:center; color:white; font-size:2rem; position:relative; overflow:hidden; }
        .card-cover img { width:100%; height:100%; object-fit:cover; }
        .card-cover .placeholder { width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,var(--primary-light),var(--accent-blue)); }
        .card-badge { position:absolute; top:12px; right:12px; background:linear-gradient(135deg,var(--secondary),var(--accent-pink)); color:var(--primary-dark); font-size:0.7rem; font-weight:var(--font-weight-bold); padding:4px 10px; border-radius:30px; }
        .card-content { padding:18px; }
        .card-title { font-size:1.1rem; font-weight:var(--font-weight-bold); color:var(--text-primary); margin-bottom:8px; }
        .card-title a { color:inherit; text-decoration:none; }
        .card-title a:hover { color:var(--primary); }
        .card-meta { display:flex; gap:16px; color:var(--text-hint); font-size:0.8rem; margin-bottom:12px; }
        .card-footer { display:flex; justify-content:space-between; align-items:center; padding-top:12px; border-top:1px solid var(--border-light); }
        .card-author { display:flex; align-items:center; gap:8px; }
        .author-avatar { width:28px; height:28px; border-radius:50%; overflow:hidden; background:linear-gradient(135deg,var(--primary-light),var(--accent-purple)); display:flex; align-items:center; justify-content:center; color:white; font-weight:bold; }
        .author-avatar img { width:100%; height:100%; object-fit:cover; }
        .author-name { font-size:0.8rem; color:var(--text-primary); }
        .card-stats { display:flex; gap:12px; color:var(--text-hint); font-size:0.8rem; }
        .no-data { text-align:center; color:var(--text-hint); padding:60px; background:var(--bg-surface); border-radius:30px; }
        .footer-links { margin-top:30px; text-align:center; }
        .footer-links a { color:var(--text-hint); margin:0 10px; text-decoration:none; }
        .footer-links a:hover { color:var(--primary); }
        @media screen and (max-width:768px){ .card-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="mini-nav">
            <div class="logo">lv8girl<span>绿坝娘</span></div>
            <div class="theme-toggle" id="themeToggle">🌓</div>
        </div>

        <h1>我的收藏</h1>

        <?php if (empty($favorites)): ?>
            <div class="no-data">
                <p>你还没有收藏任何帖子</p>
                <p><a href="index.php" style="color:var(--primary);">去首页看看</a></p>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($favorites as $post): 
                    // 处理封面
                    $cover_html = '';
                    if ($post['image_path'] && file_exists($post['image_path'])) {
                        $cover_html = '<img src="' . htmlspecialchars($post['image_path']) . '" alt="cover">';
                    } else {
                        $cover_html = '<div class="placeholder">📸</div>';
                    }
                    // 处理头像
                    $avatar_html = '';
                    if ($post['avatar'] && file_exists($post['avatar'])) {
                        $avatar_html = '<img src="' . htmlspecialchars($post['avatar']) . '" alt="avatar">';
                    } else {
                        $initial = strtoupper(mb_substr($post['username'], 0, 1));
                        $avatar_html = '<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center;">' . $initial . '</div>';
                    }
                ?>
                <div class="card">
                    <div class="card-cover">
                        <?php echo $cover_html; ?>
                    </div>
                    <div class="card-content">
                        <div class="card-title">
                            <a href="post.php?id=<?php echo $post['id']; ?>">
                                <?php echo htmlspecialchars($post['title']); ?>
                            </a>
                        </div>
                        <div class="card-meta">
                            <span>👤 <?php echo htmlspecialchars($post['username']); ?></span>
                            <span>📅 <?php echo date('Y-m-d', strtotime($post['created_at'])); ?></span>
                        </div>
                        <div class="card-footer">
                            <div class="card-author">
                                <div class="author-avatar">
                                    <?php echo $avatar_html; ?>
                                </div>
                                <span class="author-name"><?php echo htmlspecialchars($post['username']); ?></span>
                            </div>
                            <div class="card-stats">
                                <span>👍 <?php echo $post['like_count']; ?></span>
                                <span>💬 <?php echo $post['comment_count']; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="footer-links">
            <a href="index.php">返回首页</a>
            <a href="profile.php">个人主页</a>
        </div>
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