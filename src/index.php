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

$is_logged_in = isset($_SESSION['user_id']);
$current_user_id = $_SESSION['user_id'] ?? 0;
$username = $_SESSION['username'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>lv8girl · 绿坝娘二次元社区</title>
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
            --card-gradient: linear-gradient(145deg, #ffffff, #f8fff8);
            --feature-gradient: linear-gradient(135deg, #f0fff0, #e0f0e0);
            --input-bg: #f5faf5;
            
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
            --card-gradient: linear-gradient(145deg, #2c342c, #252e25);
            --feature-gradient: linear-gradient(135deg, #2c342c, #232b23);
            --input-bg: #3a453a;
        }

        body {
            background: var(--bg-body);
            min-height: 100vh;
            transition: background 0.3s;
            padding: 0;
            font-weight: var(--font-weight-regular);
            line-height: 1.5;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        .app-wrapper {
            max-width: 1400px;
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

        /* ===== 内容组件 ===== */
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
            position: relative;
            display: inline-block;
        }

        .section-header h2::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--secondary), var(--accent-pink));
            border-radius: 4px;
        }

        .view-more {
            color: var(--text-hint);
            text-decoration: none;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.2s;
        }

        .view-more:hover {
            color: var(--primary);
        }

        /* 特色大卡片 */
        .feature-card {
            background: var(--feature-gradient);
            border-radius: 24px;
            padding: 24px;
            margin-bottom: 40px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 30px;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            pointer-events: none;
        }

        .feature-content {
            flex: 2;
            min-width: 280px;
        }

        .feature-tag {
            background: linear-gradient(135deg, var(--secondary), var(--accent-pink));
            color: var(--primary-dark);
            font-size: 0.8rem;
            font-weight: var(--font-weight-bold);
            padding: 4px 12px;
            border-radius: 30px;
            display: inline-block;
            margin-bottom: 12px;
        }

        .feature-title {
            font-size: 2rem;
            font-weight: var(--font-weight-black);
            background: linear-gradient(135deg, var(--primary), var(--accent-blue));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .feature-desc {
            color: var(--text-secondary);
            margin-bottom: 20px;
            line-height: 1.6;
            font-weight: var(--font-weight-light);
        }

        .feature-stats {
            display: flex;
            gap: 30px;
            color: var(--text-hint);
            font-size: 0.9rem;
        }

        .feature-stats span {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .feature-cover {
            flex: 1;
            min-width: 200px;
            height: 160px;
            background: linear-gradient(135deg, var(--primary-light), var(--accent-blue));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            box-shadow: 0 8px 0 var(--primary-dark);
        }

        /* 卡片网格 */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 48px;
        }

        .card {
            background: var(--card-gradient);
            border-radius: 20px;
            border: 1px solid var(--border);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
        }

        .card:hover {
            transform: translateY(-6px);
            box-shadow: var(--hover-shadow);
            border-color: var(--primary);
        }

        .card-cover {
            width: 100%;
            height: 140px;
            background: linear-gradient(135deg, var(--primary-light), var(--accent-blue));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            position: relative;
            font-weight: var(--font-weight-bold);
            overflow: hidden;
        }

        .card-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .card-cover .placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary-light), var(--accent-blue));
        }

        .card-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: linear-gradient(135deg, var(--secondary), var(--accent-pink));
            color: var(--primary-dark);
            font-size: 0.7rem;
            font-weight: var(--font-weight-bold);
            padding: 4px 10px;
            border-radius: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card-content {
            padding: 18px;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .card-title a {
            color: inherit;
            text-decoration: none;
        }

        .card-title a:hover {
            color: var(--primary);
        }

        .card-meta {
            display: flex;
            gap: 16px;
            color: var(--text-hint);
            font-size: 0.8rem;
            margin-bottom: 12px;
            font-weight: var(--font-weight-light);
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid var(--border-light);
        }

        .card-author {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .author-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, var(--primary-light), var(--accent-purple));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.8rem;
        }

        .author-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .author-name {
            font-size: 0.8rem;
            color: var(--text-primary);
            font-weight: var(--font-weight-regular);
        }

        .card-stats {
            display: flex;
            gap: 12px;
            color: var(--text-hint);
            font-size: 0.8rem;
        }

        /* 时间轴（用于最新新番讨论） */
        .timeline-list {
            margin-bottom: 30px;
        }
        .timeline-item {
            display: flex;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid var(--border-light);
        }

        .timeline-time {
            min-width: 80px;
            color: var(--text-hint);
            font-size: 0.85rem;
            font-weight: var(--font-weight-light);
        }

        .timeline-content {
            flex: 1;
        }

        .timeline-title {
            font-weight: var(--font-weight-bold);
            color: var(--text-primary);
            margin-bottom: 4px;
            font-size: 1rem;
        }

        .timeline-title a {
            color: inherit;
            text-decoration: none;
        }
        .timeline-title a:hover {
            color: var(--primary);
        }

        .timeline-meta {
            color: var(--text-hint);
            font-size: 0.8rem;
            font-weight: var(--font-weight-light);
        }

        .timeline-preview {
            color: var(--text-hint);
            font-size: 0.85rem;
            margin-top: 5px;
        }

        /* 右侧边栏卡片 */
        .side-card {
            background: var(--card-gradient);
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
            .card-grid {
                grid-template-columns: 1fr;
            }
            .feature-card {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="app-wrapper">
        <!-- 顶部导航栏 -->
        <nav class="top-nav">
            <div class="nav-left">
                <div class="logo">lv8girl<span>绿坝娘</span></div>
                <div class="nav-menu">
                    <a href="index.php" class="active">首页</a>
                    <a href="anime_list.php">新番</a>
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
                <!-- 特色大卡片 -->
                <div class="feature-card">
                    <div class="feature-content">
                        <span class="feature-tag">🍀 绿坝娘专题</span>
                        <h1 class="feature-title">花季护航 · 绿坝娘<br>守护你的二次元</h1>
                        <p class="feature-desc">绿坝娘主题二次元社区，收录最新番剧、漫画、游戏资讯，以及同人创作和漫展情报。注册即可参与讨论和投稿。</p>
                        <div class="feature-stats">
                            <span>📊 今日更新 24 条</span>
                            <span>👥 在线同好 187 人</span>
                        </div>
                    </div>
                    <div class="feature-cover">🍀</div>
                </div>

                <!-- 热门讨论区块（只显示未关联新番的普通帖子） -->
                <div class="section-header">
                    <h2>热门讨论</h2>
                    <a href="post_discussion.php" class="view-more">发表新帖 +</a>
                </div>
                <div class="card-grid">
                    <?php
                    // 获取最新4条未关联新番的讨论，并统计点赞数和评论数
                    $stmt = $pdo->prepare("
                        SELECT 
                            d.*, 
                            u.username, 
                            u.avatar,
                            (SELECT COUNT(*) FROM likes WHERE post_id = d.id) as like_count,
                            (SELECT COUNT(*) FROM comments WHERE post_id = d.id) as comment_count
                        FROM discussions d
                        JOIN users u ON d.user_id = u.id
                        WHERE d.anime_id IS NULL
                        ORDER BY d.created_at DESC
                        LIMIT 4
                    ");
                    $stmt->execute();
                    $discussions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($discussions) > 0) {
                        foreach ($discussions as $discussion) {
                            // 处理封面：如果有图片则显示图片，否则显示图标
                            $cover_html = '';
                            if ($discussion['image_path'] && file_exists($discussion['image_path'])) {
                                $cover_html = '<img src="' . htmlspecialchars($discussion['image_path']) . '" alt="cover">';
                            } else {
                                $cover_html = '<div class="placeholder">📸</div>';
                            }

                            // 处理作者头像
                            $avatar_html = '';
                            if ($discussion['avatar'] && file_exists($discussion['avatar'])) {
                                $avatar_html = '<img src="' . htmlspecialchars($discussion['avatar']) . '" alt="avatar">';
                            } else {
                                // 没有头像则显示首字母
                                $initial = strtoupper(mb_substr($discussion['username'], 0, 1));
                                $avatar_html = '<div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center;">' . $initial . '</div>';
                            }
                            ?>
                            <div class="card">
                                <div class="card-cover">
                                    <?php echo $cover_html; ?>
                                    <span class="card-badge">🔥 热门</span>
                                </div>
                                <div class="card-content">
                                    <div class="card-title">
                                        <a href="post.php?id=<?php echo $discussion['id']; ?>">
                                            <?php echo htmlspecialchars($discussion['title']); ?>
                                        </a>
                                    </div>
                                    <div class="card-meta">
                                        <span>👤 <?php echo htmlspecialchars($discussion['username']); ?></span>
                                        <span>📅 <?php echo date('Y-m-d', strtotime($discussion['created_at'])); ?></span>
                                    </div>
                                    <div class="card-footer">
                                        <div class="card-author">
                                            <div class="author-avatar">
                                                <?php echo $avatar_html; ?>
                                            </div>
                                            <span class="author-name"><?php echo htmlspecialchars($discussion['username']); ?></span>
                                        </div>
                                        <div class="card-stats">
                                            <span>👍 <?php echo $discussion['like_count']; ?></span>
                                            <span>💬 <?php echo $discussion['comment_count']; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    } else {
                        echo '<div style="grid-column: 1/-1; text-align: center; color: var(--text-hint); padding: 40px;">暂无普通讨论，快来发表第一篇吧！</div>';
                    }
                    ?>
                </div>

                <!-- 最新新番讨论（只显示关联了新番的帖子） -->
                <div class="section-header" style="margin-top: 20px;">
                    <h2>最新新番讨论</h2>
                    <a href="anime_list.php" class="view-more">更多新番 →</a>
                </div>
                <div class="timeline-list">
                    <?php
                    // 获取最近5条关联了新番的帖子
                    $stmt = $pdo->prepare("
                        SELECT d.*, u.username, u.avatar, a.title as anime_title, a.id as anime_id
                        FROM discussions d
                        JOIN users u ON d.user_id = u.id
                        LEFT JOIN anime a ON d.anime_id = a.id
                        WHERE d.anime_id IS NOT NULL
                        ORDER BY d.created_at DESC
                        LIMIT 5
                    ");
                    $stmt->execute();
                    $recent_anime_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    if (count($recent_anime_posts) > 0):
                        foreach ($recent_anime_posts as $post):
                            $preview = mb_substr(strip_tags($post['content']), 0, 80) . (mb_strlen($post['content']) > 80 ? '...' : '');
                            $time_diff = date('Y-m-d H:i', strtotime($post['created_at']));
                    ?>
                    <div class="timeline-item">
                        <div class="timeline-time"><?php echo $time_diff; ?></div>
                        <div class="timeline-content">
                            <div class="timeline-title">
                                <a href="post.php?id=<?php echo $post['id']; ?>">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                                <span class="anime-tag" style="background: var(--secondary); color: var(--primary-dark); padding: 2px 8px; border-radius: 30px; font-size: 0.7rem; font-weight: bold; margin-left: 8px;">
                                    <?php echo htmlspecialchars($post['anime_title']); ?>
                                </span>
                            </div>
                            <div class="timeline-meta">
                                <span>👤 <?php echo htmlspecialchars($post['username']); ?></span>
                                <span>💬 0</span>
                            </div>
                            <div class="timeline-preview"><?php echo htmlspecialchars($preview); ?></div>
                        </div>
                    </div>
                    <?php
                        endforeach;
                    else:
                    ?>
                    <div style="text-align: center; color: var(--text-hint); padding: 20px;">暂无新番讨论，快来发表第一篇吧！</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 右侧边栏 -->
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