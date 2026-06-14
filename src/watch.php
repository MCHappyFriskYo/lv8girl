<?php
// watch.php - 视频播放页面（固定播放器大小）
session_start();
require_once 'config.php';

$lb = isset($_GET['lb']) ? trim($_GET['lb']) : '';
if (empty($lb)) die('视频不存在');

$stmt = $pdo->prepare("SELECT * FROM videos WHERE lb = ?");
$stmt->execute([$lb]);
$video = $stmt->fetch();
if (!$video) die('视频不存在');

$video_id = $video['id'];
$pdo->prepare("UPDATE videos SET plays = plays + 1 WHERE id = ?")->execute([$video_id]);

$isLoggedIn = isset($_SESSION['handle']);
$currentUser = $isLoggedIn ? $_SESSION['handle'] : '';
$currentDisplay = $isLoggedIn ? ($_SESSION['display_name'] ?? $_SESSION['handle']) : '';

// 获取 UP 主的用户信息
$stmt = $pdo->prepare("SELECT handle, display_name, avatar FROM users WHERE display_name = ?");
$stmt->execute([$video['up_name']]);
$upUser = $stmt->fetch();
if (!$upUser) {
    $upUser = ['handle' => '', 'display_name' => $video['up_name'], 'avatar' => null];
}

// 推荐视频
$stmt = $pdo->prepare("SELECT * FROM videos WHERE up_name = ? AND id != ? LIMIT 4");
$stmt->execute([$video['up_name'], $video_id]);
$relatedVideos = $stmt->fetchAll();
if (count($relatedVideos) < 2) {
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE id != ? ORDER BY RAND() LIMIT 4");
    $stmt->execute([$video_id]);
    $randVideos = $stmt->fetchAll();
    $relatedVideos = array_merge($relatedVideos, $randVideos);
    $relatedVideos = array_slice($relatedVideos, 0, 4);
}

// API 处理（评论、弹幕）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    if ($action === 'get_comments') {
        $stmt = $pdo->prepare("SELECT * FROM comments WHERE video_id = ? ORDER BY created_at DESC");
        $stmt->execute([$video_id]);
        echo json_encode(['success' => true, 'comments' => $stmt->fetchAll()]);
        exit;
    } elseif ($action === 'post_comment') {
        if (!$isLoggedIn) exit(json_encode(['success' => false, 'error' => '请先登录']));
        $content = trim($_POST['content'] ?? '');
        if (empty($content)) exit(json_encode(['success' => false, 'error' => '内容不能为空']));
        $stmt = $pdo->prepare("INSERT INTO comments (video_id, user_handle, user_display, content) VALUES (?, ?, ?, ?)");
        $stmt->execute([$video_id, $currentUser, $currentDisplay, $content]);
        echo json_encode(['success' => true, 'comment' => [
            'user_display' => $currentDisplay,
            'user_handle' => $currentUser,
            'content' => $content,
            'created_at' => date('Y-m-d H:i:s')
        ]]);
        exit;
    } elseif ($action === 'get_danmu') {
        $stmt = $pdo->prepare("SELECT * FROM danmu WHERE video_id = ? ORDER BY time ASC");
        $stmt->execute([$video_id]);
        echo json_encode(['success' => true, 'danmu' => $stmt->fetchAll()]);
        exit;
    } elseif ($action === 'post_danmu') {
        if (!$isLoggedIn) exit(json_encode(['success' => false, 'error' => '请先登录']));
        $content = trim($_POST['content'] ?? '');
        $time = floatval($_POST['time'] ?? 0);
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['color'] ?? '') ? $_POST['color'] : '#ffffff';
        if (empty($content)) exit(json_encode(['success' => false, 'error' => '弹幕不能为空']));
        $stmt = $pdo->prepare("INSERT INTO danmu (video_id, user_handle, content, time, color) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$video_id, $currentUser, $content, $time, $color]);
        echo json_encode(['success' => true, 'danmu' => ['id' => $pdo->lastInsertId(), 'content' => $content, 'time' => $time, 'color' => $color]]);
        exit;
    }
    exit(json_encode(['success' => false, 'error' => '无效的 action']));
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?= htmlspecialchars($video['title']) ?> - lv8girl 少女世界</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(145deg, #eaf7e4 0%, #d3e8cc 100%); font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; color: #1c2c1a; padding: 0 0 40px; }
        .navbar { background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(100, 150, 90, 0.2); padding: 12px 0; position: sticky; top: 0; z-index: 100; }
        .nav-container { max-width: 1300px; margin: 0 auto; padding: 0 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
        .logo-area { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo-icon { background: linear-gradient(135deg, #9be29b, #50b36e); width: 38px; height: 38px; border-radius: 16px; display: flex; align-items: center; justify-content: center; }
        .logo-icon i { font-size: 22px; color: white; }
        .logo-text { font-size: 24px; font-weight: 800; background: linear-gradient(135deg, #5acd73, #2e7d4c); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .back-home { background: rgba(100, 150, 90, 0.15); padding: 8px 18px; border-radius: 40px; color: #3a874b; text-decoration: none; font-weight: 500; }
        .container { max-width: 1300px; margin: 28px auto 0; padding: 0 28px; display: grid; grid-template-columns: 1fr 340px; gap: 32px; }
        .main-content { min-width: 0; }

        /* 播放器固定大小：最大宽度 900px，保持 16:9 */
        .player-card {
            max-width: 900px;
            margin: 0 auto 24px;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            border-radius: 28px;
            overflow: hidden;
            position: relative;
        }
        .video-wrapper {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%; /* 16:9 比例 */
            background: #000;
        }
        video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: block;
        }
        .danmu-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            overflow: hidden;
        }
        .danmu-item {
            position: absolute;
            white-space: nowrap;
            font-size: 22px;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.6);
            animation: danmuMove linear forwards;
            pointer-events: none;
            z-index: 50;
        }
        @keyframes danmuMove {
            from { transform: translateX(100%); }
            to { transform: translateX(-100%); }
        }

        .danmu-send-bar {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px);
            border-radius: 60px;
            padding: 8px 8px 8px 20px;
            margin-top: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            border: 1px solid rgba(100, 150, 90, 0.3);
        }
        .danmu-send-bar input { flex: 1; background: transparent; border: none; padding: 12px 0; font-size: 14px; outline: none; }
        .danmu-send-bar select { background: rgba(240, 248, 235, 0.8); border: 1px solid rgba(100, 150, 90, 0.4); border-radius: 40px; padding: 8px 16px; }
        .btn-send-danmu { background: linear-gradient(95deg, #69d588, #3fa359); border: none; padding: 8px 24px; border-radius: 40px; color: white; font-weight: 600; cursor: pointer; }
        .info-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border-radius: 28px; padding: 20px 24px; margin-bottom: 28px; border: 1px solid rgba(100, 150, 90, 0.25); }
        .video-title { font-size: 22px; font-weight: 700; color: #2a5233; margin-bottom: 12px; }
        .video-meta { display: flex; gap: 24px; font-size: 13px; color: #5b8a51; margin-bottom: 16px; flex-wrap: wrap; }
        .video-desc { color: #44633b; line-height: 1.5; border-top: 1px solid rgba(100,150,90,0.3); padding-top: 16px; margin-top: 8px; }
        .comment-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border-radius: 28px; padding: 24px; border: 1px solid rgba(100,150,90,0.25); }
        .section-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; border-left: 4px solid #7bc87b; padding-left: 14px; color: #2a5233; }
        .comment-list { max-height: 500px; overflow-y: auto; margin-bottom: 20px; }
        .comment-item { padding: 14px 0; border-bottom: 1px solid rgba(100, 150, 90, 0.2); }
        .comment-user { font-weight: 700; color: #3d874b; font-size: 14px; margin-bottom: 6px; }
        .comment-content { font-size: 14px; line-height: 1.4; margin-bottom: 6px; color: #2c4528; }
        .comment-time { font-size: 11px; color: #8bb282; }
        .comment-form { display: flex; gap: 12px; margin-top: 12px; }
        .comment-form input { flex: 1; padding: 12px 18px; background: rgba(240,248,235,0.8); border: 1px solid rgba(100,150,90,0.4); border-radius: 60px; outline: none; }
        .empty-tip { text-align: center; padding: 30px 10px; color: #9bbd90; }
        .sidebar { min-width: 0; }
        .up-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border-radius: 28px; padding: 20px; margin-bottom: 28px; text-align: center; border: 1px solid rgba(100,150,90,0.25); }
        .up-avatar-large { width: 80px; height: 80px; border-radius: 60px; margin: 0 auto 12px; object-fit: cover; border: 3px solid #62cd82; }
        .up-name { font-size: 20px; font-weight: 700; color: #2a5233; text-decoration: none; }
        .up-name:hover { text-decoration: underline; color: #3e9e4a; }
        .related-card { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border-radius: 28px; padding: 20px; border: 1px solid rgba(100,150,90,0.25); }
        .related-item { display: flex; gap: 12px; margin-bottom: 16px; cursor: pointer; text-decoration: none; color: inherit; }
        .related-cover { width: 100px; height: 56px; background: #d9e8d4; border-radius: 12px; object-fit: cover; }
        .related-title { font-size: 13px; font-weight: 600; -webkit-line-clamp: 2; overflow: hidden; }
        .related-up { font-size: 11px; color: #8bb282; margin-top: 4px; }
        .toast-msg { position: fixed; bottom: 25px; left: 25px; background: #1f321b; color: #e3ffdb; padding: 10px 24px; border-radius: 60px; font-size: 13px; z-index: 999; opacity: 0; transition: 0.2s; pointer-events: none; }
        @media (max-width: 900px) {
            .container { grid-template-columns: 1fr; }
            .danmu-send-bar { flex-wrap: wrap; }
            .player-card { max-width: 100%; }
        }
    </style>
</head>
<body>
<div class="navbar">
    <div class="nav-container">
        <a href="index.php" class="logo-area"><div class="logo-icon"><i class="fas fa-leaf"></i></div><div class="logo-text">lv8girl</div></a>
        <a href="index.php" class="back-home"><i class="fas fa-arrow-left"></i> 返回首页</a>
    </div>
</div>

<div class="container">
    <div class="main-content">
        <div class="player-card">
            <div class="video-wrapper">
                <video id="videoPlayer" controls autoplay playsinline>
                    <source src="<?= htmlspecialchars($video['file_path']) ?>" type="video/mp4">
                    您的浏览器不支持视频播放。
                </video>
                <div id="danmuLayer" class="danmu-canvas"></div>
            </div>
        </div>
        <div class="danmu-send-bar">
            <input type="text" id="danmuText" placeholder="发一条友善的弹幕吧 ~" maxlength="200">
            <select id="danmuColor">
                <option value="#ffffff">白色</option><option value="#ff99cc">粉色</option><option value="#99ff99">绿色</option><option value="#ffcc66">橙色</option><option value="#66ccff">蓝色</option>
            </select>
            <button id="sendDanmuBtn" class="btn-send-danmu"><i class="fas fa-paper-plane"></i> 发射</button>
        </div>
        <div class="info-card">
            <div class="video-title"><?= htmlspecialchars($video['title']) ?></div>
            <div class="video-meta">
                <span><i class="fas fa-user"></i> UP主：<a href="profile.php?handle=<?= urlencode($upUser['handle']) ?>" style="color:#3a874b; text-decoration:none;"><?= htmlspecialchars($video['up_name']) ?></a></span>
                <span><i class="fas fa-play-circle"></i> 播放：<?= htmlspecialchars($video['plays']) ?></span>
                <span><i class="fas fa-tag"></i> 分类：<?= htmlspecialchars($video['category'] ?? '其他') ?></span>
                <span><i class="fas fa-hashtag"></i> LB号：<?= htmlspecialchars($video['lb']) ?></span>
            </div>
            <div class="video-desc"><?= nl2br(htmlspecialchars($video['description'] ?? '暂无简介')) ?></div>
        </div>
        <div class="comment-card">
            <div class="section-title"><i class="fas fa-comments"></i> 评论区</div>
            <div id="commentList" class="comment-list"><div class="empty-tip">加载评论中...</div></div>
            <?php if ($isLoggedIn): ?>
                <div class="comment-form"><input type="text" id="commentInput" placeholder="写一条评论..." maxlength="500"><button id="postCommentBtn" class="btn-send-danmu" style="background:#62cd82;">发表</button></div>
            <?php else: ?>
                <div class="empty-tip">❤️ <a href="login.php" style="color:#6fcf97;">登录</a> 后参与评论</div>
            <?php endif; ?>
        </div>
    </div>
    <div class="sidebar">
        <div class="up-card">
            <a href="profile.php?handle=<?= urlencode($upUser['handle']) ?>">
                <?php if ($upUser['avatar'] && file_exists($upUser['avatar'])): ?>
                    <img class="up-avatar-large" src="<?= htmlspecialchars($upUser['avatar']) ?>" alt="avatar">
                <?php else: ?>
                    <div class="up-avatar-large" style="display: flex; align-items: center; justify-content: center; background: #d9e8d4; font-size: 36px; color: #5b8a51;">
                        <i class="fas fa-user-astronaut"></i>
                    </div>
                <?php endif; ?>
            </a>
            <a href="profile.php?handle=<?= urlencode($upUser['handle']) ?>" class="up-name"><?= htmlspecialchars($video['up_name']) ?></a>
        </div>
        <div class="related-card">
            <div class="section-title"><i class="fas fa-list-ul"></i> 相关推荐</div>
            <?php foreach ($relatedVideos as $rel): ?>
                <a href="watch.php?lb=<?= $rel['lb'] ?>" class="related-item">
                    <img class="related-cover" src="<?= htmlspecialchars($rel['thumbnail_path'] ?? $rel['cover_url'] ?? 'https://picsum.photos/id/100/100/56') ?>" loading="lazy">
                    <div><div class="related-title"><?= htmlspecialchars($rel['title']) ?></div><div class="related-up"><?= htmlspecialchars($rel['up_name']) ?> · <?= htmlspecialchars($rel['plays']) ?>播放</div></div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div id="toastMsg" class="toast-msg"></div>

<script>
    const video = document.getElementById('videoPlayer');
    const danmuLayer = document.getElementById('danmuLayer');
    const videoId = <?= json_encode($video_id) ?>;
    const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

    let danmuPool = [], shownDanmu = new Set();
    function showToast(msg, isError=false) {
        const toast = document.getElementById('toastMsg');
        toast.innerText = msg;
        toast.style.opacity = '1';
        toast.style.background = isError ? '#5a2e2a' : '#1f321b';
        setTimeout(() => toast.style.opacity = '0', 2500);
    }
    function loadDanmu() {
        fetch(`?action=get_danmu&id=${videoId}`).then(r=>r.json()).then(d=>{ if(d.success){ danmuPool = d.danmu.map(dd=>({id:dd.id,content:dd.content,time:parseFloat(dd.time),color:dd.color})); danmuPool.sort((a,b)=>a.time-b.time); } }).catch(e=>console.error(e));
    }
    function sendDanmu(content,color,time) {
        if(!isLoggedIn){ showToast('请先登录再发送弹幕',true); return; }
        if(!content.trim()){ showToast('弹幕内容不能为空',true); return; }
        const fd = new FormData(); fd.append('content',content); fd.append('color',color); fd.append('time',time);
        fetch(`?action=post_danmu&id=${videoId}`,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success){ showToast('弹幕发送成功 ✨'); danmuPool.push({id:d.danmu.id,content:d.danmu.content,time:d.danmu.time,color:d.danmu.color}); danmuPool.sort((a,b)=>a.time-b.time); } else { showToast(d.error||'发送失败',true); } });
    }
    let lastCheckTime=-1;
    function checkAndShowDanmu() { if(!video) return; const ct = video.currentTime; if(Math.abs(ct-lastCheckTime)<0.2) return; lastCheckTime=ct; const toShow = danmuPool.filter(d=>!shownDanmu.has(d.id) && d.time<=ct+0.3); for(let d of toShow){ shownDanmu.add(d.id); createDanmuElement(d.content,d.color); } }
    function createDanmuElement(text,color) { const div = document.createElement('div'); div.className='danmu-item'; div.innerText=text; div.style.color=color; div.style.fontSize='24px'; const top=10+Math.random()*75; div.style.top=top+'%'; const duration=5+Math.min(5,text.length/10); div.style.animationDuration=duration+'s'; danmuLayer.appendChild(div); div.addEventListener('animationend',()=>div.remove()); }
    function loadComments(){ fetch(`?action=get_comments&id=${videoId}`).then(r=>r.json()).then(d=>{ if(d.success) renderComments(d.comments); else document.getElementById('commentList').innerHTML='<div class="empty-tip">评论加载失败</div>'; }).catch(()=>document.getElementById('commentList').innerHTML='<div class="empty-tip">网络错误</div>'); }
    function renderComments(comments){ const container=document.getElementById('commentList'); if(!comments.length){ container.innerHTML='<div class="empty-tip">✨ 还没有评论，来做第一个发言的人吧 ✨</div>'; return; } let html=''; comments.forEach(c=>{ html+=`<div class="comment-item"><div class="comment-user"><i class="fas fa-user-circle"></i> ${escapeHtml(c.user_display)} (${escapeHtml(c.user_handle)})</div><div class="comment-content">${escapeHtml(c.content)}</div><div class="comment-time">${c.created_at}</div></div>`; }); container.innerHTML=html; }
    function postComment(content){ if(!isLoggedIn){ showToast('请先登录',true); return; } if(!content.trim()){ showToast('评论不能为空',true); return; } const fd=new FormData(); fd.append('content',content); fetch(`?action=post_comment&id=${videoId}`,{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success){ showToast('评论发表成功'); loadComments(); document.getElementById('commentInput').value=''; } else showToast(d.error||'评论失败',true); }); }
    function escapeHtml(str){ return str.replace(/[&<>]/g,function(m){ if(m==='&') return '&amp;'; if(m==='<') return '&lt;'; if(m==='>') return '&gt;'; return m;}); }
    document.getElementById('sendDanmuBtn')?.addEventListener('click',()=>{ const text=document.getElementById('danmuText').value.trim(); const color=document.getElementById('danmuColor').value; if(!text){ showToast('弹幕内容不能为空',true); return; } sendDanmu(text,color,video.currentTime); document.getElementById('danmuText').value=''; });
    document.getElementById('postCommentBtn')?.addEventListener('click',()=>{ postComment(document.getElementById('commentInput').value); });
    document.getElementById('commentInput')?.addEventListener('keypress',(e)=>{ if(e.key==='Enter') postComment(e.target.value); });
    document.getElementById('danmuText')?.addEventListener('keypress',(e)=>{ if(e.key==='Enter') document.getElementById('sendDanmuBtn').click(); });
    video.addEventListener('timeupdate',checkAndShowDanmu);
    loadDanmu(); loadComments(); setTimeout(()=>{ if(video.paused) video.play().catch(e=>{}); },500);
</script>
</body>
</html>