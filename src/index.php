<?php
// index.php - 主页（完整版，含管理员入口，用户名可点击进入个人主页）
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['handle']);
$userDisplayName = $isLoggedIn ? ($_SESSION['display_name'] ?? $_SESSION['handle']) : '';

// 读取视频数据
$stmt = $pdo->query("SELECT * FROM videos ORDER BY RAND() LIMIT 8");
$recommendVideos = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM videos ORDER BY CAST(plays AS UNSIGNED) DESC LIMIT 5");
$rankVideos = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT up_name, COUNT(*) as video_count, SUM(CAST(plays AS UNSIGNED)) as total_plays 
    FROM videos 
    GROUP BY up_name 
    ORDER BY total_plays DESC, video_count DESC 
    LIMIT 3
");
$recommendUps = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>lv8girl · 少女专属视频站</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* 样式与之前相同，为确保完整，这里保留完整样式（可复用之前的CSS） */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(145deg, #eaf7e4 0%, #d3e8cc 100%); font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; color: #1c2c1a; scroll-behavior: smooth; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #d9e8d4; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #85c47c; border-radius: 10px; }
        .container { max-width: 1300px; margin: 0 auto; padding: 0 28px; }
        .navbar { background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(100, 150, 90, 0.2); position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02); }
        .nav-wrapper { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; padding: 14px 0; gap: 16px; }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon { background: linear-gradient(135deg, #9be29b, #50b36e); width: 42px; height: 42px; border-radius: 18px; display: flex; align-items: center; justify-content: center; box-shadow: 0 8px 18px rgba(72, 160, 72, 0.2); }
        .logo-icon i { font-size: 26px; color: white; }
        .logo-text { font-size: 28px; font-weight: 800; background: linear-gradient(135deg, #5acd73, #2e7d4c); -webkit-background-clip: text; background-clip: text; color: transparent; letter-spacing: -0.3px; }
        .logo-text span { font-size: 18px; font-weight: 500; background: none; color: #4fae6b; }
        .nav-links { display: flex; gap: 32px; }
        .nav-links a { font-weight: 600; font-size: 15px; color: #2b4527; text-decoration: none; transition: 0.2s; position: relative; }
        .nav-links a:hover { color: #429b55; }
        .nav-links .active { color: #429b55; }
        .nav-links .active::after { content: ''; position: absolute; bottom: -8px; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, #8fdfa5, #2e7d4c); border-radius: 3px; }
        .search-bar { display: flex; align-items: center; background: rgba(235, 245, 230, 0.8); border-radius: 60px; padding: 6px 18px; width: 270px; backdrop-filter: blur(4px); border: 1px solid rgba(100, 150, 90, 0.3); transition: all 0.2s; }
        .search-bar:focus-within { background: rgba(255, 255, 255, 0.95); box-shadow: 0 0 0 3px rgba(100, 200, 100, 0.2); }
        .search-bar i { color: #6bac6b; }
        .search-bar input { border: none; background: transparent; padding: 10px 10px; font-size: 14px; outline: none; width: 100%; color: #1e351b; }
        .user-area { display: flex; align-items: center; gap: 20px; }
        .auth-buttons { display: flex; gap: 12px; }
        .btn-login, .btn-register, .btn-submit-video, .btn-admin { padding: 6px 20px; border-radius: 40px; font-weight: 600; font-size: 14px; transition: 0.2s; cursor: pointer; text-decoration: none; }
        .btn-login { background: transparent; border: 1px solid #80c272; color: #3a874b; }
        .btn-login:hover { background: #e2f5dd; border-color: #5fa855; }
        .btn-register { background: linear-gradient(95deg, #69d588, #3fa359); border: none; color: white; box-shadow: 0 4px 10px rgba(70, 150, 70, 0.2); }
        .btn-register:hover { transform: translateY(-2px); box-shadow: 0 8px 18px rgba(70, 150, 70, 0.3); }
        .btn-submit-video { background: #eef7ea; border: 1px solid #a3d69e; color: #3d834d; }
        .btn-submit-video:hover { background: #dff0da; }
        .btn-admin { background: #eef7ea; border: 1px solid #ffcc66; color: #b8860b; }
        .btn-admin:hover { background: #fff2dd; }
        .user-greeting { font-weight: 600; color: #3a874b; background: rgba(100, 180, 100, 0.12); padding: 6px 14px; border-radius: 40px; text-decoration: none; display: inline-block; }
        .avatar { width: 40px; height: 40px; background: linear-gradient(145deg, #bde2b3, #8fcb84); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; text-decoration: none; }
        .banner-area { margin: 32px 0 40px; border-radius: 32px; overflow: hidden; position: relative; box-shadow: 0 18px 32px -12px rgba(0,0,0,0.15); }
        .banner-img { width: 100%; height: 220px; object-fit: cover; background: linear-gradient(120deg, #c5e3b5, #a8d49b); }
        @media (min-width: 768px) { .banner-img { height: 280px; } }
        .banner-text { position: absolute; bottom: 28px; left: 32px; background: rgba(255, 255, 245, 0.85); backdrop-filter: blur(8px); padding: 8px 24px; border-radius: 60px; font-weight: 700; color: #2a7846; font-size: 1rem; border-left: 5px solid #6fcf97; }
        .content-grid { display: grid; grid-template-columns: 1fr 320px; gap: 36px; margin: 20px 0 60px; }
        .video-section h2 { font-size: 22px; font-weight: 700; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; color: #2a5233; }
        .video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 28px; }
        .video-card { background: #ffffff; border-radius: 28px; overflow: hidden; transition: all 0.3s cubic-bezier(0.2, 0.9, 0.4, 1.1); box-shadow: 0 8px 20px rgba(0, 0, 0, 0.02), 0 2px 4px rgba(0,0,0,0.02); cursor: pointer; text-decoration: none; display: block; color: inherit; }
        .video-card:hover { transform: translateY(-8px); box-shadow: 0 28px 36px -16px rgba(70, 130, 70, 0.28); }
        .card-cover { position: relative; aspect-ratio: 16 / 9; overflow: hidden; background: #eaf3e6; }
        .card-cover img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .video-card:hover .card-cover img { transform: scale(1.05); }
        .play-icon { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0, 0, 0, 0.6); backdrop-filter: blur(8px); width: 48px; height: 48px; border-radius: 60px; display: flex; align-items: center; justify-content: center; opacity: 0; transition: 0.2s; color: white; font-size: 22px; }
        .video-card:hover .play-icon { opacity: 1; }
        .card-info { padding: 16px 16px 20px; }
        .video-title { font-weight: 700; font-size: 15px; line-height: 1.4; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .up-info { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .up-avatar { width: 26px; height: 26px; background: #e2f3dc; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #409a54; }
        .up-name { font-size: 13px; font-weight: 500; color: #6f9667; }
        .stats { display: flex; gap: 16px; font-size: 12px; color: #98b68e; }
        .sidebar-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(8px); border-radius: 32px; padding: 22px 20px; margin-bottom: 28px; box-shadow: 0 8px 20px rgba(0,0,0,0.02); border: 1px solid rgba(100, 150, 90, 0.2); }
        .sidebar-title { font-weight: 700; font-size: 18px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; border-left: 4px solid #7bc87b; padding-left: 14px; color: #2a5233; }
        .rank-list { list-style: none; }
        .rank-item { display: flex; align-items: center; gap: 14px; padding: 12px 0; border-bottom: 1px solid rgba(150, 190, 130, 0.3); cursor: pointer; transition: 0.1s; }
        .rank-item:hover { background: rgba(180, 220, 160, 0.2); border-radius: 20px; padding-left: 8px; }
        .rank-num { width: 32px; font-weight: 800; font-size: 20px; color: #6fbc6f; }
        .rank-title { font-weight: 500; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .rank-play { font-size: 11px; color: #92b587; }
        .recommend-up { display: flex; align-items: center; gap: 14px; margin-bottom: 20px; padding: 4px 8px; border-radius: 60px; transition: 0.1s; }
        .recommend-up:hover { background: #eef7ea; }
        .rec-avatar { width: 48px; height: 48px; background: #d1eac9; border-radius: 60px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #409a54; }
        .rec-detail h4 { font-size: 15px; font-weight: 700; }
        .btn-follow { margin-left: auto; background: #ecf7e8; border: none; padding: 5px 14px; border-radius: 60px; font-size: 12px; font-weight: 500; color: #509e5a; cursor: pointer; transition: 0.1s; }
        .btn-follow:hover { background: #d4eacc; }
        .footer { background: rgba(255, 255, 255, 0.7); backdrop-filter: blur(8px); border-top: 1px solid #d9e8d4; padding: 36px 0; text-align: center; font-size: 13px; color: #86a97c; }
        .empty-placeholder { text-align: center; padding: 50px 20px; background: rgba(255,255,240,0.6); border-radius: 36px; color: #a1c497; font-size: 15px; }
        .toast-msg { position: fixed; bottom: 25px; left: 25px; background: #1f321b; color: #e3ffdb; padding: 10px 24px; border-radius: 60px; font-size: 13px; z-index: 999; opacity: 0; transition: 0.2s; pointer-events: none; box-shadow: 0 6px 14px rgba(0,0,0,0.1); }
        @media (max-width: 900px) { .content-grid { grid-template-columns: 1fr; } .nav-wrapper { flex-direction: column; } .search-bar { width: 100%; } .nav-links { justify-content: center; } .user-area { justify-content: flex-end; flex-wrap: wrap; } }
        @media (max-width: 550px) { .video-grid { grid-template-columns: 1fr; } .container { padding: 0 18px; } }
    </style>
</head>
<body>
<div class="navbar">
    <div class="container">
        <div class="nav-wrapper">
            <div class="logo-area">
                <div class="logo-icon"><i class="fas fa-leaf"></i></div>
                <div class="logo-text">lv8girl <span>· 少女频道</span></div>
            </div>
            <div class="nav-links">
                <a href="#" class="active">首页</a>
                <a href="#">番剧</a>
                <a href="#">直播</a>
                <a href="#">游戏</a>
                <a href="#">舞蹈</a>
                <a href="#">广播剧</a>
            </div>
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="搜索少女番、萌物...">
            </div>
            <div class="user-area">
                <?php if ($isLoggedIn): ?>
                    <?php if (isAdmin($pdo)): ?>
                        <a href="admin.php" class="btn-admin"><i class="fas fa-cog"></i> 管理后台</a>
                    <?php endif; ?>
                    <a href="submit_video.php" class="btn-submit-video"><i class="fas fa-upload"></i> 投稿</a>
                    <a href="profile.php" class="user-greeting" style="text-decoration: none;"><i class="fas fa-heart"></i> <?= htmlspecialchars($userDisplayName) ?></a>
                    <a href="profile.php" class="avatar" style="text-decoration: none;"><i class="fas fa-cat"></i></a>
                <?php else: ?>
                    <div class="auth-buttons">
                        <a href="login.php" class="btn-login">登录</a>
                        <a href="register.php" class="btn-register">注册</a>
                    </div>
                    <i class="far fa-bell"></i>
                    <i class="far fa-comment-dots"></i>
                    <div class="avatar"><i class="fas fa-cat"></i></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="banner-area">
        <img class="banner-img" src="https://picsum.photos/id/29/1400/300" alt="banner" onerror="this.src='https://picsum.photos/id/169/1400/300'">
        <div class="banner-text">
            <i class="fas fa-seedling"></i> 春日创刊祭 · lv8girl专属特典
        </div>
    </div>

    <div class="content-grid">
        <div class="video-section">
            <h2><i class="fas fa-play-circle"></i> 为你推荐 · 今日糖分超标</h2>
            <div class="video-grid">
                <?php if (empty($recommendVideos)): ?>
                    <div class="empty-placeholder">✨ 还没有视频，等待少女们投稿 ✨</div>
                <?php else: ?>
                    <?php foreach ($recommendVideos as $video): ?>
                        <a href="watch.php?lb=<?= $video['lb'] ?>" class="video-card">
                            <div class="card-cover">
                                <img src="<?= htmlspecialchars($video['thumbnail_path'] ?? $video['cover_url'] ?? 'https://picsum.photos/id/100/300/180') ?>" alt="封面" loading="lazy">
                                <div class="play-icon"><i class="fas fa-play"></i></div>
                            </div>
                            <div class="card-info">
                                <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
                                <div class="up-info">
                                    <div class="up-avatar"><i class="fas fa-seedling"></i></div>
                                    <span class="up-name"><?= htmlspecialchars($video['up_name']) ?></span>
                                </div>
                                <div class="stats">
                                    <span><i class="fas fa-play-circle"></i> <?= htmlspecialchars($video['plays']) ?></span>
                                    <span><i class="fas fa-comment-dots"></i> <?= htmlspecialchars($video['danmu']) ?></span>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <aside>
            <div class="sidebar-card">
                <div class="sidebar-title"><i class="fas fa-chart-line"></i> 全站少女榜</div>
                <?php if (empty($rankVideos)): ?>
                    <div class="empty-placeholder" style="padding: 30px;">还没有热门视频～</div>
                <?php else: ?>
                    <ul class="rank-list">
                        <?php $rank_num = 1; foreach ($rankVideos as $video): ?>
                            <li class="rank-item" onclick="window.location.href='watch.php?lb=<?= $video['lb'] ?>'">
                                <div class="rank-num"><?= $rank_num++ ?></div>
                                <div class="rank-info">
                                    <div class="rank-title"><?= htmlspecialchars($video['title']) ?></div>
                                    <div class="rank-play"><i class="fas fa-chart-simple"></i> <?= htmlspecialchars($video['plays']) ?>播放</div>
                                </div>
                                <i class="fas fa-chevron-right" style="color:#a3cf97;"></i>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <div class="sidebar-card">
                <div class="sidebar-title"><i class="fas fa-heart"></i> 推荐UP主</div>
                <?php if (empty($recommendUps)): ?>
                    <div class="empty-placeholder" style="padding: 30px;">还没有UP主推荐～</div>
                <?php else: ?>
                    <?php foreach ($recommendUps as $up): ?>
                        <div class="recommend-up">
                            <div class="rec-avatar"><i class="fas fa-star"></i></div>
                            <div class="rec-detail">
                                <h4><?= htmlspecialchars($up['up_name']) ?></h4>
                                <p style="font-size:11px; color:#89ba7d;">视频 <?= $up['video_count'] ?> 个 · 总播放 <?= number_format($up['total_plays']) ?></p>
                            </div>
                            <button class="btn-follow" onclick="event.stopPropagation(); showToast('💚 已关注 <?= htmlspecialchars($up['up_name']) ?>'); this.innerText='✓ 已关注'; this.disabled=true;">+ 关注</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<footer class="footer">
    <div class="container">
        <p>© 2026 lv8girl · 少女满载 绿野仙踪</p>
    </div>
</footer>
<div class="toast-msg" id="toastMsg">✨ 少女加载完毕</div>

<script>
    let toastTimeout;
    function showToast(msg) {
        const toast = document.getElementById('toastMsg');
        if (!toast) return;
        clearTimeout(toastTimeout);
        toast.innerText = msg;
        toast.style.opacity = '1';
        toastTimeout = setTimeout(() => toast.style.opacity = '0', 2300);
    }
    document.querySelector('.search-bar input')?.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
            let val = e.target.value.trim();
            showToast(val ? `🔍 搜索“${val}” 开发中~` : `🌱 输入你想看的少女内容叭`);
        }
    });
    document.querySelectorAll('.nav-links a').forEach(link => {
        link.addEventListener('click', (e) => {
            if (link.getAttribute('href') === '#') {
                e.preventDefault();
                showToast(`✨ “${link.innerText}” 分区即将开放~`);
            }
        });
    });
    document.querySelector('.banner-area')?.addEventListener('click', () => showToast('🍃 春日祭典·lv8girl限定活动即将开启'));
</script>
</body>
</html>