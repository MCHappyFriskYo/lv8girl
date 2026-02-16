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

$season_filter = isset($_GET['season']) ? $_GET['season'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT * FROM anime WHERE 1";
$params = [];

if (!empty($season_filter)) {
    $sql .= " AND season LIKE ?";
    $params[] = "%$season_filter%";
}
if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
}
if (!empty($search)) {
    $sql .= " AND title LIKE ?";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$anime_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$is_logged_in = isset($_SESSION['user_id']);
$username = $_SESSION['username'] ?? '';
$current_user_id = $_SESSION['user_id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <title>新番列表 - lv8girl</title>
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
        .nav-menu a.active { background:var(--primary); color:white; font-weight:bold; }
        .nav-right { display:flex; align-items:center; gap:20px; }
        .search-box { display:flex; align-items:center; background:var(--bg-surface); border:1px solid var(--border); border-radius:40px; padding:4px 4px 4px 20px; width:260px; }
        .search-box input { background:transparent; border:none; outline:none; color:var(--text-primary); width:100%; }
        .search-box button { background:linear-gradient(135deg,var(--primary),var(--accent-blue)); border:none; border-radius:40px; padding:8px 20px; color:white; font-weight:bold; cursor:pointer; }
        .user-actions { display:flex; align-items:center; gap:15px; position:relative; }
        .user-actions a { color:var(--text-primary); text-decoration:none; padding:6px 16px; border-radius:30px; background:var(--bg-surface); border:1px solid var(--border); }
        .user-menu { position:relative; cursor:pointer; }
        .user-name { background:var(--bg-surface); border:1px solid var(--border); border-radius:30px; padding:6px 18px; font-weight:bold; color:var(--text-primary); }
        .dropdown { position:absolute; top:120%; right:0; background:var(--bg-surface); border:1px solid var(--border); border-radius:20px; min-width:180px; opacity:0; visibility:hidden; transition:0.3s; z-index:10; }
        .user-menu:hover .dropdown { opacity:1; visibility:visible; }
        .dropdown a { display:block; padding:12px 20px; color:var(--text-primary); text-decoration:none; border-bottom:1px solid var(--border-light); }
        .theme-toggle { background:var(--bg-surface); border:1px solid var(--border); width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; }

        .main-content { padding:30px 32px; flex:1; }
        .filter-bar { background:var(--bg-surface); border-radius:30px; padding:20px; margin-bottom:30px; display:flex; gap:20px; flex-wrap:wrap; align-items:center; }
        .filter-item { display:flex; align-items:center; gap:8px; }
        .filter-item select, .filter-item input { padding:8px 15px; border-radius:30px; border:1px solid var(--border); background:var(--bg-nav); color:var(--text-primary); }
        .anime-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px,1fr)); gap:24px; }
        .anime-card { background:var(--bg-surface); border-radius:20px; overflow:hidden; border:1px solid var(--border); box-shadow:var(--shadow); transition:0.3s; }
        .anime-card:hover { transform:translateY(-6px); box-shadow:var(--hover-shadow); border-color:var(--primary); }
        .anime-cover { width:100%; height:200px; object-fit:cover; background:linear-gradient(135deg,var(--primary-light),var(--accent-purple)); }
        .anime-info { padding:16px; }
        .anime-title { font-size:1.2rem; font-weight:bold; color:var(--text-primary); margin-bottom:8px; }
        .anime-title a { color:inherit; text-decoration:none; }
        .anime-meta { display:flex; justify-content:space-between; color:var(--text-hint); font-size:0.85rem; margin-bottom:8px; }
        .anime-status { display:inline-block; background:var(--secondary); color:var(--primary-dark); padding:2px 10px; border-radius:30px; font-size:0.7rem; font-weight:bold; }
        .footer { margin-top:40px; padding:24px 32px; border-top:1px solid var(--border); color:var(--text-hint); text-align:center; }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <nav class="top-nav">
            <div class="nav-left">
                <div class="logo">lv8girl</div>
                <div class="nav-menu">
                    <a href="index.php">首页</a>
                    <a href="anime_list.php" class="active">新番</a>
                    <a href="#">漫画</a>
                    <a href="#">游戏</a>
                    <a href="#">图库</a>
                </div>
            </div>
            <div class="nav-right">
                <form class="search-box" method="get">
                    <input type="text" name="search" placeholder="搜索新番..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit">搜索</button>
                </form>
                <div class="user-actions">
                    <?php if ($is_logged_in): ?>
                        <div class="user-menu">
                            <span class="user-name"><?php echo htmlspecialchars($username); ?> ▼</span>
                            <div class="dropdown">
                                <?php if ($current_user_id == 1): ?><a href="admin.php">新番管理</a><?php endif; ?>
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
            <h1 style="font-size:2rem; font-weight:900; margin-bottom:20px;">📺 新番列表</h1>
            <div class="filter-bar">
                <div class="filter-item">
                    <label>季度：</label>
                    <select name="season" onchange="this.form.submit()">
    <option value="">全部</option>
    <option value="2026第一季度" <?php echo $season_filter=='2026第一季度'?'selected':''; ?>>2026第一季度</option>
    <option value="2026第二季度" <?php echo $season_filter=='2026第二季度'?'selected':''; ?>>2026第二季度</option>
    <option value="2026第三季度" <?php echo $season_filter=='2026第三季度'?'selected':''; ?>>2026第三季度</option>
    <option value="2026第四季度" <?php echo $season_filter=='2026第四季度'?'selected':''; ?>>2026第四季度</option>
    <!-- 可动态生成年份 -->
</select>
                </div>
                <div class="filter-item">
                    <label>状态：</label>
                    <select name="status" onchange="window.location.href='?season=<?php echo $season_filter; ?>&status='+this.value+'&search=<?php echo urlencode($search); ?>'">
                        <option value="">全部</option>
                        <option value="即将播出" <?php echo $status_filter=='即将播出'?'selected':''; ?>>即将播出</option>
                        <option value="播出中" <?php echo $status_filter=='播出中'?'selected':''; ?>>播出中</option>
                        <option value="已完结" <?php echo $status_filter=='已完结'?'selected':''; ?>>已完结</option>
                    </select>
                </div>
            </div>

            <?php if (empty($anime_list)): ?>
                <div style="text-align:center; padding:60px; color:var(--text-hint);">暂无新番数据</div>
            <?php else: ?>
                <div class="anime-grid">
                    <?php foreach ($anime_list as $anime): ?>
                        <div class="anime-card">
                            <a href="anime.php?id=<?php echo $anime['id']; ?>">
                                <img class="anime-cover" src="<?php echo $anime['cover'] && file_exists($anime['cover']) ? $anime['cover'] : 'data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 100 100\'%3E%3Crect width=\'100\' height=\'100\' fill=\'%23' . substr($primary,1) . '\' /%3E%3Ctext x=\'25\' y=\'65\' fill=\'white\' font-size=\'40\'%3E📷%3C/text%3E%3C/svg%3E'; ?>" alt="cover">
                            </a>
                            <div class="anime-info">
                                <div class="anime-title"><a href="anime.php?id=<?php echo $anime['id']; ?>"><?php echo htmlspecialchars($anime['title']); ?></a></div>
                                <div class="anime-meta">
                                    <span><?php echo htmlspecialchars($anime['season']); ?></span>
                                    <span class="anime-status"><?php echo $anime['status']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <footer class="footer">
    <div>© 2025 lv8girl · 绿坝娘二次元社区</div>
    <div class="footer-links">
        <a href="https://icp.gov.moe/?keyword=20260911" target="_blank">萌ICP备20260911号</a>
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
