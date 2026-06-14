<?php
// profile.php - 个人主页（支持查看他人，本人可编辑）
session_start();
require_once 'config.php';

// 获取要查看的用户 handle
$viewHandle = isset($_GET['handle']) ? trim($_GET['handle']) : ($_SESSION['handle'] ?? '');
if (empty($viewHandle)) {
    header('Location: login.php');
    exit;
}

$isOwnProfile = (isset($_SESSION['handle']) && $_SESSION['handle'] === $viewHandle);

$stmt = $pdo->prepare("SELECT * FROM users WHERE handle = ?");
$stmt->execute([$viewHandle]);
$user = $stmt->fetch();
if (!$user) die('用户不存在');

// 如果是自己的主页，处理头像上传和资料编辑（代码与之前相同）
$avatarMsg = '';
$infoMsg = '';
if ($isOwnProfile) {
    // 头像上传
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['avatar'])) {
        $uploadDir = __DIR__ . '/uploads/avatars/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if ($file['error'] === UPLOAD_ERR_OK && in_array($file['type'], $allowedTypes)) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = $viewHandle . '_' . uniqid() . '.' . $ext;
            $dest = $uploadDir . $newName;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $avatarPath = 'uploads/avatars/' . $newName;
                $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE handle = ?");
                $stmt->execute([$avatarPath, $viewHandle]);
                $user['avatar'] = $avatarPath;
                $avatarMsg = '头像已更新';
            } else {
                $avatarMsg = '上传失败';
            }
        } else {
            $avatarMsg = '请选择 jpg/png/webp 图片';
        }
    }

    // 资料修改
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $display_name = trim($_POST['display_name']);
        $email = trim($_POST['email']);
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        $updates = [];
        $params = [];
        if (!empty($display_name) && $display_name !== $user['display_name']) {
            $updates[] = "display_name = ?";
            $params[] = $display_name;
        }
        if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL) && $email !== $user['email']) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND handle != ?");
            $stmt->execute([$email, $viewHandle]);
            if ($stmt->fetch()) {
                $infoMsg = '邮箱已被其他用户使用';
            } else {
                $updates[] = "email = ?";
                $params[] = $email;
            }
        }
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                $infoMsg = '密码至少6位';
            } elseif ($new_password !== $confirm_password) {
                $infoMsg = '两次新密码不一致';
            } elseif (!password_verify($current_password, $user['password'])) {
                $infoMsg = '当前密码错误';
            } else {
                $updates[] = "password = ?";
                $params[] = password_hash($new_password, PASSWORD_DEFAULT);
            }
        }
        if (empty($infoMsg) && !empty($updates)) {
            $params[] = $viewHandle;
            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE handle = ?";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($params)) {
                $infoMsg = '资料已更新';
                if (in_array('display_name', $updates)) $_SESSION['display_name'] = $display_name;
                $stmt = $pdo->prepare("SELECT * FROM users WHERE handle = ?");
                $stmt->execute([$viewHandle]);
                $user = $stmt->fetch();
            } else {
                $infoMsg = '更新失败';
            }
        } elseif (empty($infoMsg)) {
            $infoMsg = '没有修改任何内容';
        }
    }
}

// 获取投稿视频和评论（分页）
$page = isset($_GET['video_page']) ? max(1, intval($_GET['video_page'])) : 1;
$limit = 6;
$offset = ($page - 1) * $limit;
$stmt = $pdo->prepare("SELECT * FROM videos WHERE up_name = ? ORDER BY id DESC LIMIT $offset, $limit");
$stmt->execute([$user['display_name']]);
$userVideos = $stmt->fetchAll();
$totalVideosStmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE up_name = ?");
$totalVideosStmt->execute([$user['display_name']]);
$totalVideos = $totalVideosStmt->fetchColumn();
$videoPages = ceil($totalVideos / $limit);

$commentPage = isset($_GET['comment_page']) ? max(1, intval($_GET['comment_page'])) : 1;
$commentLimit = 10;
$commentOffset = ($commentPage - 1) * $commentLimit;
$stmt = $pdo->prepare("SELECT comments.*, videos.title as video_title, videos.lb FROM comments LEFT JOIN videos ON comments.video_id = videos.id WHERE comments.user_handle = ? ORDER BY comments.id DESC LIMIT $commentOffset, $commentLimit");
$stmt->execute([$viewHandle]);
$userComments = $stmt->fetchAll();
$totalCommentsStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE user_handle = ?");
$totalCommentsStmt->execute([$viewHandle]);
$totalComments = $totalCommentsStmt->fetchColumn();
$commentPages = ceil($totalComments / $commentLimit);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($user['display_name']) ?> 的个人主页 - lv8girl</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* 样式与之前相同（为节省篇幅省略，实际使用时请复制完整样式） */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: linear-gradient(145deg, #eaf7e4 0%, #d3e8cc 100%); font-family: 'Inter', 'Segoe UI', system-ui, sans-serif; color: #1c2c1a; padding: 0 0 40px; }
        .navbar { background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(12px); border-bottom: 1px solid rgba(100, 150, 90, 0.2); padding: 12px 0; position: sticky; top: 0; z-index: 100; }
        .nav-container { max-width: 1300px; margin: 0 auto; padding: 0 28px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px; }
        .logo-area { display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .logo-icon { background: linear-gradient(135deg, #9be29b, #50b36e); width: 38px; height: 38px; border-radius: 16px; display: flex; align-items: center; justify-content: center; }
        .logo-icon i { font-size: 22px; color: white; }
        .logo-text { font-size: 24px; font-weight: 800; background: linear-gradient(135deg, #5acd73, #2e7d4c); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .back-home { background: rgba(100, 150, 90, 0.15); padding: 8px 18px; border-radius: 40px; color: #3a874b; text-decoration: none; font-weight: 500; }
        .container { max-width: 1200px; margin: 32px auto; padding: 0 28px; }
        .profile-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(10px); border-radius: 32px; padding: 32px; margin-bottom: 32px; display: flex; flex-wrap: wrap; gap: 32px; align-items: center; border: 1px solid rgba(100,150,90,0.25); }
        .avatar-section { text-align: center; }
        .avatar-img { width: 120px; height: 120px; border-radius: 60px; object-fit: cover; background: #d9e8d4; border: 3px solid #62cd82; }
        .avatar-upload { margin-top: 12px; }
        .avatar-upload label { background: #eaf5e6; padding: 6px 16px; border-radius: 60px; font-size: 13px; cursor: pointer; display: inline-block; }
        .avatar-upload input { display: none; }
        .info-section { flex: 1; }
        .info-section h1 { font-size: 28px; color: #2a5233; margin-bottom: 8px; }
        .info-section .handle { color: #6f9e6b; margin-bottom: 16px; font-size: 14px; }
        .info-section .meta { display: flex; gap: 24px; font-size: 14px; color: #5b8a51; margin-top: 8px; }
        .edit-form { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(10px); border-radius: 28px; padding: 24px; margin-bottom: 32px; border: 1px solid rgba(100,150,90,0.25); }
        .edit-form h3 { margin-bottom: 20px; color: #2a5233; display: flex; align-items: center; gap: 8px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #3a6345; }
        .form-group input { width: 100%; padding: 10px 16px; border-radius: 60px; border: 1px solid #cde0c4; background: rgba(240,248,235,0.8); outline: none; }
        .form-group input:focus { border-color: #6fcf97; }
        .btn-submit { background: #62cd82; border: none; padding: 10px 24px; border-radius: 40px; color: white; font-weight: 600; cursor: pointer; margin-right: 12px; }
        .btn-logout { background: #f8d7da; border: none; padding: 10px 24px; border-radius: 40px; color: #721c24; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; margin-bottom: 32px; }
        .video-card { background: rgba(255,255,255,0.85); border-radius: 24px; overflow: hidden; transition: 0.2s; text-decoration: none; color: inherit; }
        .video-card:hover { transform: translateY(-6px); box-shadow: 0 20px 30px -12px rgba(70,130,70,0.2); }
        .card-cover { aspect-ratio: 16/9; overflow: hidden; }
        .card-cover img { width: 100%; height: 100%; object-fit: cover; }
        .card-info { padding: 12px; }
        .video-title { font-weight: 600; font-size: 14px; margin-bottom: 6px; }
        .video-stats { font-size: 12px; color: #8bb282; }
        .section-title { font-size: 22px; font-weight: 700; margin: 32px 0 20px; color: #2a5233; border-left: 5px solid #6fcf97; padding-left: 16px; }
        .comment-list { background: rgba(255,255,255,0.85); border-radius: 28px; padding: 20px; }
        .comment-item { padding: 12px 0; border-bottom: 1px solid rgba(100,150,90,0.2); }
        .comment-video { font-weight: 600; color: #3a874b; margin-bottom: 4px; }
        .comment-content { font-size: 14px; color: #2c4528; margin-bottom: 4px; }
        .comment-time { font-size: 11px; color: #8bb282; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin: 20px 0; }
        .pagination a, .pagination span { background: rgba(255,255,255,0.7); padding: 6px 14px; border-radius: 40px; text-decoration: none; color: #3a874b; }
        .pagination .current { background: #62cd82; color: white; }
        .msg { background: #d4edda; color: #155724; padding: 10px 20px; border-radius: 60px; margin-bottom: 20px; }
        .empty { text-align: center; padding: 40px; color: #9bbd90; }
        @media (max-width: 800px) { .form-grid { grid-template-columns: 1fr; } .profile-card { flex-direction: column; text-align: center; } }
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
    <div class="profile-card">
        <div class="avatar-section">
            <?php if ($user['avatar'] && file_exists($user['avatar'])): ?>
                <img class="avatar-img" src="<?= htmlspecialchars($user['avatar']) ?>" alt="avatar">
            <?php else: ?>
                <div class="avatar-img" style="display: flex; align-items: center; justify-content: center; background: #d9e8d4; font-size: 48px; color: #5b8a51;">
                    <i class="fas fa-user-astronaut"></i>
                </div>
            <?php endif; ?>
            <?php if ($isOwnProfile): ?>
            <div class="avatar-upload">
                <form method="POST" enctype="multipart/form-data">
                    <label for="avatarInput"><i class="fas fa-camera"></i> 更换头像</label>
                    <input type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/webp" onchange="this.form.submit()">
                </form>
                <?php if ($avatarMsg): ?><div style="font-size:12px; margin-top:6px;"><?= htmlspecialchars($avatarMsg) ?></div><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="info-section">
            <h1><?= htmlspecialchars($user['display_name']) ?></h1>
            <div class="handle"><?= htmlspecialchars($user['handle']) ?></div>
            <div class="meta">
                <span><i class="fas fa-calendar-alt"></i> 加入于 <?= date('Y-m-d', strtotime($user['created_at'])) ?></span>
                <span><i class="fas fa-video"></i> 投稿 <?= $totalVideos ?> 个</span>
                <span><i class="fas fa-comment"></i> 评论 <?= $totalComments ?> 条</span>
            </div>
        </div>
    </div>

    <?php if ($isOwnProfile): ?>
    <div class="edit-form">
        <h3><i class="fas fa-user-edit"></i> 编辑个人资料</h3>
        <?php if ($infoMsg): ?><div class="msg"><?= htmlspecialchars($infoMsg) ?></div><?php endif; ?>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>显示名称</label>
                    <input type="text" name="display_name" value="<?= htmlspecialchars($user['display_name']) ?>" required>
                </div>
                <div class="form-group">
                    <label>邮箱</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
                <div class="form-group">
                    <label>当前密码（修改密码时需填写）</label>
                    <input type="password" name="current_password">
                </div>
                <div class="form-group">
                    <label>新密码（留空则不修改）</label>
                    <input type="password" name="new_password">
                </div>
                <div class="form-group">
                    <label>确认新密码</label>
                    <input type="password" name="confirm_password">
                </div>
            </div>
            <button type="submit" name="update_profile" class="btn-submit">保存修改</button>
            <a href="logout.php" class="btn-logout" onclick="return confirm('确定要退出登录吗？')"><i class="fas fa-sign-out-alt"></i> 退出登录</a>
        </form>
    </div>
    <?php endif; ?>

    <div class="section-title"><i class="fas fa-video"></i> 投稿视频</div>
    <?php if (empty($userVideos)): ?>
        <div class="empty">✨ 还没有投稿 ✨</div>
    <?php else: ?>
        <div class="video-grid">
            <?php foreach ($userVideos as $video): ?>
                <a href="watch.php?lb=<?= $video['lb'] ?>" class="video-card">
                    <div class="card-cover"><img src="<?= htmlspecialchars($video['thumbnail_path'] ?? $video['cover_url'] ?? 'https://picsum.photos/id/100/300/180') ?>" loading="lazy"></div>
                    <div class="card-info"><div class="video-title"><?= htmlspecialchars($video['title']) ?></div><div class="video-stats"><i class="fas fa-play-circle"></i> <?= $video['plays'] ?> &nbsp; <i class="fas fa-comment-dots"></i> <?= $video['danmu'] ?></div></div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php if ($videoPages > 1): ?>
            <div class="pagination"><?php for ($i=1;$i<=$videoPages;$i++): ?><?php if($i==$page):?><span class="current"><?=$i?></span><?php else:?><a href="?video_page=<?=$i?>&comment_page=<?=$commentPage?>"><?=$i?></a><?php endif; ?><?php endfor; ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="section-title"><i class="fas fa-comments"></i> 评论记录</div>
    <?php if (empty($userComments)): ?>
        <div class="empty">✨ 还没有发表过评论 ✨</div>
    <?php else: ?>
        <div class="comment-list">
            <?php foreach ($userComments as $comment): ?>
                <div class="comment-item">
                    <div class="comment-video"><a href="watch.php?lb=<?= $comment['lb'] ?>" target="_blank"><?= htmlspecialchars($comment['video_title']) ?></a></div>
                    <div class="comment-content"><?= nl2br(htmlspecialchars($comment['content'])) ?></div>
                    <div class="comment-time"><?= $comment['created_at'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if ($commentPages > 1): ?>
            <div class="pagination"><?php for ($i=1;$i<=$commentPages;$i++): ?><?php if($i==$commentPage):?><span class="current"><?=$i?></span><?php else:?><a href="?video_page=<?=$page?>&comment_page=<?=$i?>"><?=$i?></a><?php endif; ?><?php endfor; ?></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>