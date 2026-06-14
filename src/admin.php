<?php
// admin.php - 功能完整的管理后台（现代化设计）
session_start();
require_once 'config.php';

if (!isset($_SESSION['handle']) || !isAdmin($pdo)) {
    header('Location: login.php');
    exit;
}

$msg = '';
$error = '';

// 处理各种操作（视频、用户、评论、弹幕的增删改）
// 视频编辑
if (isset($_POST['edit_video'])) {
    $id = intval($_POST['id']);
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $plays = trim($_POST['plays']);
    $danmu = trim($_POST['danmu']);
    $cover_url = trim($_POST['cover_url']);
    $stmt = $pdo->prepare("UPDATE videos SET title=?, description=?, category=?, plays=?, danmu=?, cover_url=? WHERE id=?");
    if ($stmt->execute([$title, $description, $category, $plays, $danmu, $cover_url, $id])) $msg = '视频已更新';
    else $error = '更新失败';
}
// 视频删除
if (isset($_GET['del_video'])) {
    $id = intval($_GET['del_video']);
    $stmt = $pdo->prepare("SELECT file_path, thumbnail_path FROM videos WHERE id=?");
    $stmt->execute([$id]);
    $video = $stmt->fetch();
    if ($video) {
        @unlink($video['file_path']);
        if ($video['thumbnail_path'] && strpos($video['thumbnail_path'], 'uploads/') === 0) @unlink($video['thumbnail_path']);
    }
    $pdo->prepare("DELETE FROM videos WHERE id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM comments WHERE video_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM danmu WHERE video_id=?")->execute([$id]);
    $msg = '视频已删除';
}
// 用户编辑 (基于 handle)
if (isset($_POST['edit_user'])) {
    $handle = $_POST['handle'];
    $display_name = trim($_POST['display_name']);
    $email = trim($_POST['email']);
    $is_admin = isset($_POST['is_admin']) ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE users SET display_name=?, email=?, is_admin=? WHERE handle=?");
    if ($stmt->execute([$display_name, $email, $is_admin, $handle])) $msg = '用户信息已更新';
    else $error = '更新失败';
}
// 用户删除
if (isset($_GET['del_user'])) {
    $handle = $_GET['del_user'];
    if ($handle !== $_SESSION['handle']) {
        $pdo->prepare("DELETE FROM users WHERE handle=?")->execute([$handle]);
        $msg = '用户已删除';
    } else $error = '不能删除自己';
}
// 重置密码
if (isset($_GET['reset_pwd'])) {
    $handle = $_GET['reset_pwd'];
    $new_hash = password_hash('123456', PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password=? WHERE handle=?")->execute([$new_hash, $handle]);
    $msg = '密码已重置为 123456';
}
// 评论删除
if (isset($_GET['del_comment'])) {
    $pdo->prepare("DELETE FROM comments WHERE id=?")->execute([intval($_GET['del_comment'])]);
    $msg = '评论已删除';
}
// 弹幕删除
if (isset($_GET['del_danmu'])) {
    $pdo->prepare("DELETE FROM danmu WHERE id=?")->execute([intval($_GET['del_danmu'])]);
    $msg = '弹幕已删除';
}
// 批量删除评论
if (isset($_POST['batch_delete_comments']) && isset($_POST['comment_ids'])) {
    $ids = array_map('intval', $_POST['comment_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM comments WHERE id IN ($placeholders)")->execute($ids);
    $msg = '已删除 ' . count($ids) . ' 条评论';
}
// 批量删除弹幕
if (isset($_POST['batch_delete_danmu']) && isset($_POST['danmu_ids'])) {
    $ids = array_map('intval', $_POST['danmu_ids']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $pdo->prepare("DELETE FROM danmu WHERE id IN ($placeholders)")->execute($ids);
    $msg = '已删除 ' . count($ids) . ' 条弹幕';
}

// 统计数据
$totalVideos = $pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalComments = $pdo->query("SELECT COUNT(*) FROM comments")->fetchColumn();
$totalDanmu = $pdo->query("SELECT COUNT(*) FROM danmu")->fetchColumn();

// 近7天模拟数据（实际可根据 created_at 统计，这里简化）
$weekStats = [
    'videos' => [4, 7, 3, 8, 12, 9, 5],
    'comments' => [23, 45, 38, 56, 72, 88, 64]
];

// 获取参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 12;
$offset = ($page - 1) * $limit;
$action = $_GET['action'] ?? 'dashboard';
$search = trim($_GET['search'] ?? '');
$sort = $_GET['sort'] ?? ($action === 'users' ? 'handle_asc' : 'id_desc');

// 排序映射
if ($action === 'videos') {
    $orderMap = [
        'id_desc' => 'id DESC', 'id_asc' => 'id ASC',
        'plays_desc' => 'CAST(plays AS UNSIGNED) DESC', 'title_asc' => 'title ASC'
    ];
    $orderBy = $orderMap[$sort] ?? 'id DESC';
} elseif ($action === 'users') {
    $orderMap = ['handle_asc' => 'handle ASC', 'handle_desc' => 'handle DESC', 'newest' => 'created_at DESC'];
    $orderBy = $orderMap[$sort] ?? 'handle ASC';
} else {
    $orderBy = 'id DESC';
}

$list = [];
$total = 0;
if ($action === 'videos') {
    $sql = "SELECT * FROM videos WHERE title LIKE ? OR up_name LIKE ? ORDER BY $orderBy LIMIT $offset, $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$search%", "%$search%"]);
    $list = $stmt->fetchAll();
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE title LIKE ? OR up_name LIKE ?");
    $totalStmt->execute(["%$search%", "%$search%"]);
    $total = $totalStmt->fetchColumn();
} elseif ($action === 'users') {
    $sql = "SELECT * FROM users WHERE handle LIKE ? OR display_name LIKE ? OR email LIKE ? ORDER BY $orderBy LIMIT $offset, $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
    $list = $stmt->fetchAll();
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE handle LIKE ? OR display_name LIKE ? OR email LIKE ?");
    $totalStmt->execute(["%$search%", "%$search%", "%$search%"]);
    $total = $totalStmt->fetchColumn();
} elseif ($action === 'comments') {
    $sql = "SELECT comments.*, videos.title as video_title FROM comments LEFT JOIN videos ON comments.video_id = videos.id WHERE comments.content LIKE ? ORDER BY comments.id DESC LIMIT $offset, $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$search%"]);
    $list = $stmt->fetchAll();
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM comments WHERE content LIKE ?");
    $totalStmt->execute(["%$search%"]);
    $total = $totalStmt->fetchColumn();
} elseif ($action === 'danmu') {
    $sql = "SELECT danmu.*, videos.title as video_title FROM danmu LEFT JOIN videos ON danmu.video_id = videos.id WHERE danmu.content LIKE ? ORDER BY danmu.id DESC LIMIT $offset, $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(["%$search%"]);
    $list = $stmt->fetchAll();
    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM danmu WHERE content LIKE ?");
    $totalStmt->execute(["%$search%"]);
    $total = $totalStmt->fetchColumn();
}

$totalPages = ceil($total / $limit);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>lv8girl 管理中心</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f0f7ec;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: #1a2c17;
        }

        /* 顶部导航 */
        .top-bar {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(100, 150, 90, 0.3);
            padding: 12px 32px;
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .logo-area {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }
        .logo-icon {
            background: linear-gradient(135deg, #7fcd7f, #3e9e4a);
            width: 40px;
            height: 40px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .logo-icon i { font-size: 24px; color: white; }
        .logo-text {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, #4fbf6a, #2a7844);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .top-actions {
            display: flex;
            gap: 20px;
            align-items: center;
        }
        .back-home {
            background: #eaf5e6;
            padding: 8px 18px;
            border-radius: 40px;
            color: #3a874b;
            text-decoration: none;
            font-weight: 500;
            transition: 0.2s;
        }
        .back-home:hover {
            background: #d4e8ce;
        }

        /* 主容器 */
        .admin-container {
            max-width: 1400px;
            margin: 32px auto;
            padding: 0 32px;
        }

        /* 统计卡片 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 28px;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.02);
            border: 1px solid rgba(100, 150, 90, 0.2);
            transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-4px); }
        .stat-info .stat-number { font-size: 36px; font-weight: 800; color: #2d7842; }
        .stat-info .stat-label { font-size: 14px; color: #6a9e5f; margin-top: 4px; }
        .stat-icon { font-size: 48px; color: #9ad48d; opacity: 0.7; }

        /* 图表区域 */
        .chart-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 28px;
            padding: 24px;
            margin-bottom: 32px;
            border: 1px solid rgba(100, 150, 90, 0.2);
        }
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #2a5233;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        canvas { max-height: 280px; }

        /* 管理选项卡 */
        .admin-tabs {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }
        .tab-btn {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(100, 150, 90, 0.3);
            padding: 10px 28px;
            border-radius: 40px;
            text-decoration: none;
            color: #3a874b;
            font-weight: 600;
            transition: 0.2s;
        }
        .tab-btn.active {
            background: #62cd82;
            color: white;
            border-color: #62cd82;
            box-shadow: 0 4px 10px rgba(98, 205, 130, 0.3);
        }

        /* 工具栏 */
        .toolbar {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 24px;
        }
        .search-box {
            display: flex;
            gap: 8px;
            background: white;
            border-radius: 60px;
            padding: 4px 4px 4px 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        }
        .search-box input {
            border: none;
            padding: 10px 0;
            width: 260px;
            outline: none;
            background: transparent;
        }
        .search-box button {
            background: #62cd82;
            border: none;
            border-radius: 40px;
            padding: 8px 24px;
            color: white;
            cursor: pointer;
        }
        .sort-select select {
            background: white;
            border: 1px solid #cce0c4;
            border-radius: 40px;
            padding: 8px 20px;
            outline: none;
        }

        /* 表格容器 */
        .data-table {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 28px;
            padding: 24px;
            overflow-x: auto;
            border: 1px solid rgba(100, 150, 90, 0.2);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 14px 12px;
            text-align: left;
            border-bottom: 1px solid rgba(100, 150, 90, 0.15);
        }
        th {
            color: #2d5a2b;
            font-weight: 600;
            font-size: 14px;
        }
        .video-thumb {
            width: 70px;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .badge-admin {
            background: #d4edda;
            color: #155724;
            padding: 4px 10px;
            border-radius: 60px;
            font-size: 12px;
            font-weight: 500;
        }
        .badge-user {
            background: #e2e3e5;
            color: #383d41;
            padding: 4px 10px;
            border-radius: 60px;
            font-size: 12px;
        }
        .btn-icon {
            background: transparent;
            border: none;
            cursor: pointer;
            font-size: 18px;
            margin: 0 6px;
            transition: 0.1s;
        }
        .btn-edit { color: #4cae4c; }
        .btn-delete { color: #d9534f; }
        .btn-view { color: #5bc0de; }
        .btn-key { color: #f0ad4e; }

        /* 分页 */
        .pagination {
            margin-top: 24px;
            display: flex;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            background: rgba(255,255,255,0.8);
            padding: 8px 16px;
            border-radius: 40px;
            text-decoration: none;
            color: #3a874b;
            border: 1px solid rgba(100,150,90,0.3);
        }
        .pagination .current {
            background: #62cd82;
            color: white;
            border-color: #62cd82;
        }

        /* 消息提示 */
        .msg-box, .error-box {
            padding: 14px 24px;
            border-radius: 60px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .msg-box { background: #d4edda; color: #155724; }
        .error-box { background: #f8d7da; color: #721c24; }

        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            border-radius: 32px;
            padding: 28px;
            max-width: 520px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
        }
        .modal-content h3 {
            font-size: 24px;
            margin-bottom: 20px;
            color: #2a5233;
        }
        .modal-content input, .modal-content textarea, .modal-content select {
            width: 100%;
            margin-bottom: 16px;
            padding: 12px 16px;
            border-radius: 40px;
            border: 1px solid #cde0c4;
            outline: none;
        }
        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 20px;
        }
        .btn-save {
            background: #62cd82;
            border: none;
            padding: 10px 28px;
            border-radius: 40px;
            color: white;
            cursor: pointer;
        }
        .btn-cancel {
            background: #f0f0f0;
            border: none;
            padding: 10px 28px;
            border-radius: 40px;
            cursor: pointer;
        }
        @media (max-width: 800px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 16px; }
            .toolbar { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="top-bar">
    <a href="index.php" class="logo-area">
        <div class="logo-icon"><i class="fas fa-leaf"></i></div>
        <div class="logo-text">lv8girl 管理中心</div>
    </a>
    <div class="top-actions">
        <a href="index.php" class="back-home"><i class="fas fa-arrow-left"></i> 返回首页</a>
    </div>
</div>

<div class="admin-container">
    <?php if ($msg): ?><div class="msg-box">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if ($action === 'dashboard'): ?>
        <!-- 仪表盘 -->
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-info"><div class="stat-number"><?= $totalVideos ?></div><div class="stat-label">视频总数</div></div><div class="stat-icon"><i class="fas fa-video"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-number"><?= $totalUsers ?></div><div class="stat-label">用户总数</div></div><div class="stat-icon"><i class="fas fa-users"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-number"><?= $totalComments ?></div><div class="stat-label">评论总数</div></div><div class="stat-icon"><i class="fas fa-comments"></i></div></div>
            <div class="stat-card"><div class="stat-info"><div class="stat-number"><?= $totalDanmu ?></div><div class="stat-label">弹幕总数</div></div><div class="stat-icon"><i class="fas fa-comment-dots"></i></div></div>
        </div>
        <div class="chart-card">
            <div class="chart-title"><i class="fas fa-chart-line"></i> 近7日动态趋势</div>
            <canvas id="trendChart" width="400" height="200"></canvas>
        </div>
        <div class="admin-tabs">
            <a href="?action=videos" class="tab-btn">📹 视频管理</a>
            <a href="?action=users" class="tab-btn">👥 用户管理</a>
            <a href="?action=comments" class="tab-btn">💬 评论管理</a>
            <a href="?action=danmu" class="tab-btn">🎈 弹幕管理</a>
        </div>
    <?php else: ?>
        <!-- 数据管理界面 -->
        <div class="admin-tabs">
            <a href="?action=dashboard" class="tab-btn">📊 仪表盘</a>
            <a href="?action=videos" class="tab-btn <?= $action === 'videos' ? 'active' : '' ?>">📹 视频管理</a>
            <a href="?action=users" class="tab-btn <?= $action === 'users' ? 'active' : '' ?>">👥 用户管理</a>
            <a href="?action=comments" class="tab-btn <?= $action === 'comments' ? 'active' : '' ?>">💬 评论管理</a>
            <a href="?action=danmu" class="tab-btn <?= $action === 'danmu' ? 'active' : '' ?>">🎈 弹幕管理</a>
        </div>

        <div class="toolbar">
            <form method="GET" class="search-box">
                <input type="hidden" name="action" value="<?= $action ?>">
                <input type="text" name="search" placeholder="搜索..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit"><i class="fas fa-search"></i> 搜索</button>
                <?php if ($search): ?><a href="?action=<?= $action ?>" style="background:#e2f0df; padding:8px 16px; border-radius:40px; text-decoration:none;">清除</a><?php endif; ?>
            </form>
            <div class="sort-select">
                <select onchange="window.location.href='?action=<?= $action ?>&sort='+this.value+'&search=<?= urlencode($search) ?>&page=<?= $page ?>'">
                    <?php if ($action === 'videos'): ?>
                        <option value="id_desc" <?= $sort === 'id_desc' ? 'selected' : '' ?>>最新优先</option>
                        <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>最早优先</option>
                        <option value="plays_desc" <?= $sort === 'plays_desc' ? 'selected' : '' ?>>播放量最高</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>标题正序</option>
                    <?php elseif ($action === 'users'): ?>
                        <option value="handle_asc" <?= $sort === 'handle_asc' ? 'selected' : '' ?>>Handle 正序</option>
                        <option value="handle_desc" <?= $sort === 'handle_desc' ? 'selected' : '' ?>>Handle 倒序</option>
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>最新注册</option>
                    <?php else: ?>
                        <option value="id_desc" <?= $sort === 'id_desc' ? 'selected' : '' ?>>最新优先</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="data-table">
            <?php if ($action === 'videos'): ?>
                <table>
                    <thead>
                        <tr><th>ID</th><th>封面</th><th>标题</th><th>UP主</th><th>播放量</th><th>弹幕数</th><th>操作</th>
                    </thead>
                    <tbody>
                    <?php foreach ($list as $v): ?>
                        <tr>
                            <td><?= $v['id'] ?></td>
                            <td><img class="video-thumb" src="<?= htmlspecialchars($v['thumbnail_path'] ?? $v['cover_url'] ?? 'https://picsum.photos/id/100/70/45') ?>" onerror="this.src='https://picsum.photos/id/100/70/45'"></td>
                            <td><?= htmlspecialchars($v['title']) ?></td>
                            <td><?= htmlspecialchars($v['up_name']) ?></td>
                            <td><?= htmlspecialchars($v['plays']) ?></td>
                            <td><?= htmlspecialchars($v['danmu']) ?></td>
                            <td>
                                <button class="btn-icon btn-edit" onclick="openEditVideo(<?= $v['id'] ?>, '<?= addslashes($v['title']) ?>', '<?= addslashes($v['description']) ?>', '<?= addslashes($v['category']) ?>', '<?= addslashes($v['plays']) ?>', '<?= addslashes($v['danmu']) ?>', '<?= addslashes($v['cover_url']) ?>')"><i class="fas fa-edit"></i></button>
                                <a href="?action=videos&del_video=<?= $v['id'] ?>&page=<?= $page ?>&search=<?= urlencode($search) ?>" onclick="return confirm('确定删除？')" class="btn-icon btn-delete"><i class="fas fa-trash-alt"></i></a>
                                <a href="watch.php?id=<?= $v['id'] ?>" target="_blank" class="btn-icon btn-view"><i class="fas fa-eye"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($list)): ?><tr><td colspan="7">暂无数据</td></tr><?php endif; ?>
                    </tbody>
                </table>
            <?php elseif ($action === 'users'): ?>
                <table>
                    <thead>
                        <tr><th>Handle</th><th>显示名称</th><th>邮箱</th><th>注册时间</th><th>角色</th><th>操作</th>
                    </thead>
                    <tbody>
                    <?php foreach ($list as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['handle']) ?></td>
                            <td><?= htmlspecialchars($u['display_name']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td><?= $u['created_at'] ?></td>
                            <td><?= $u['is_admin'] ? '<span class="badge-admin">管理员</span>' : '<span class="badge-user">普通用户</span>' ?></td>
                            <td>
                                <button class="btn-icon btn-edit" onclick="openEditUser('<?= htmlspecialchars($u['handle']) ?>', '<?= addslashes($u['display_name']) ?>', '<?= addslashes($u['email']) ?>', <?= $u['is_admin'] ?>)"><i class="fas fa-edit"></i></button>
                                <?php if ($u['handle'] !== $_SESSION['handle']): ?>
                                    <a href="?action=users&reset_pwd=<?= urlencode($u['handle']) ?>&page=<?= $page ?>" onclick="return confirm('重置密码为 123456？')" class="btn-icon btn-key"><i class="fas fa-key"></i></a>
                                    <a href="?action=users&del_user=<?= urlencode($u['handle']) ?>&page=<?= $page ?>&search=<?= urlencode($search) ?>" onclick="return confirm('确定删除用户？')" class="btn-icon btn-delete"><i class="fas fa-trash-alt"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($list)): ?><tr><td colspan="6">暂无数据</td></tr><?php endif; ?>
                    </tbody>
                </table>
            <?php elseif ($action === 'comments'): ?>
                <form method="POST" onsubmit="return confirm('确定删除选中的评论？')">
                    <table>
                        <thead>
                            <tr><th><input type="checkbox" id="selectAllComments"></th><th>ID</th><th>视频</th><th>用户</th><th>评论内容</th><th>时间</th><th>操作</th>
                        </thead>
                        <tbody>
                        <?php foreach ($list as $c): ?>
                            <tr>
                                <td><input type="checkbox" name="comment_ids[]" value="<?= $c['id'] ?>"></td>
                                <td><?= $c['id'] ?></td>
                                <td><a href="watch.php?id=<?= $c['video_id'] ?>" target="_blank"><?= htmlspecialchars($c['video_title'] ?? '视频已删除') ?></a></td>
                                <td><?= htmlspecialchars($c['user_display']) ?></td>
                                <td><?= htmlspecialchars(mb_substr($c['content'],0,60)) ?>...</td>
                                <td><?= $c['created_at'] ?></td>
                                <td><a href="?action=comments&del_comment=<?= $c['id'] ?>&page=<?= $page ?>&search=<?= urlencode($search) ?>" onclick="return confirm('删除评论？')" class="btn-icon btn-delete"><i class="fas fa-trash-alt"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($list)): ?><tr><td colspan="7">暂无数据</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 16px;"><button type="submit" name="batch_delete_comments" class="btn-delete" style="background:#ffe7e0; border:none; padding:6px 20px; border-radius:30px;">🗑️ 批量删除</button></div>
                </form>
            <?php elseif ($action === 'danmu'): ?>
                <form method="POST" onsubmit="return confirm('确定删除选中的弹幕？')">
                    <table>
                        <thead>
                            <tr><th><input type="checkbox" id="selectAllDanmu"></th><th>ID</th><th>视频</th><th>用户</th><th>弹幕内容</th><th>时间点</th><th>颜色</th><th>操作</th>
                        </thead>
                        <tbody>
                        <?php foreach ($list as $d): ?>
                            <tr>
                                <td><input type="checkbox" name="danmu_ids[]" value="<?= $d['id'] ?>"></td>
                                <td><?= $d['id'] ?></td>
                                <td><a href="watch.php?id=<?= $d['video_id'] ?>" target="_blank"><?= htmlspecialchars($d['video_title'] ?? '视频已删除') ?></a></td>
                                <td><?= htmlspecialchars($d['user_handle'] ?? '匿名') ?></td>
                                <td><?= htmlspecialchars(mb_substr($d['content'],0,40)) ?>...</td>
                                <td><?= $d['time'] ?>s</td>
                                <td><span style="background:<?= $d['color'] ?>; padding:2px 12px; border-radius:20px;"><?= $d['color'] ?></span></td>
                                <td><a href="?action=danmu&del_danmu=<?= $d['id'] ?>&page=<?= $page ?>&search=<?= urlencode($search) ?>" onclick="return confirm('删除弹幕？')" class="btn-icon btn-delete"><i class="fas fa-trash-alt"></i></a></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($list)): ?><tr><td colspan="8">暂无数据</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                    <div style="margin-top: 16px;"><button type="submit" name="batch_delete_danmu" class="btn-delete" style="background:#ffe7e0; border:none; padding:6px 20px; border-radius:30px;">🗑️ 批量删除</button></div>
                </form>
            <?php endif; ?>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php if ($i == $page): ?><span class="current"><?= $i ?></span>
                        <?php else: ?><a href="?action=<?= $action ?>&page=<?= $i ?>&search=<?= urlencode($search) ?>&sort=<?= urlencode($sort) ?>"><?= $i ?></a><?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 编辑视频模态框 -->
<div id="editVideoModal" class="modal">
    <div class="modal-content">
        <h3>✏️ 编辑视频</h3>
        <form method="POST">
            <input type="hidden" name="id" id="edit_video_id">
            <input type="text" name="title" id="edit_title" placeholder="标题" required>
            <textarea name="description" id="edit_description" placeholder="简介" rows="3"></textarea>
            <input type="text" name="category" id="edit_category" placeholder="分类">
            <input type="text" name="plays" id="edit_plays" placeholder="播放量">
            <input type="text" name="danmu" id="edit_danmu" placeholder="弹幕数">
            <input type="text" name="cover_url" id="edit_cover_url" placeholder="封面URL">
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeModal()">取消</button>
                <button type="submit" name="edit_video" class="btn-save">保存</button>
            </div>
        </form>
    </div>
</div>

<!-- 编辑用户模态框 -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <h3>👤 编辑用户</h3>
        <form method="POST">
            <input type="hidden" name="handle" id="edit_user_handle">
            <input type="text" name="display_name" id="edit_display_name" placeholder="显示名称" required>
            <input type="email" name="email" id="edit_email" placeholder="邮箱" required>
            <label><input type="checkbox" name="is_admin" id="edit_is_admin"> 管理员权限</label>
            <div class="modal-buttons">
                <button type="button" class="btn-cancel" onclick="closeModal()">取消</button>
                <button type="submit" name="edit_user" class="btn-save">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
    // 图表
    <?php if ($action === 'dashboard'): ?>
    const ctx = document.getElementById('trendChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['6天前', '5天前', '4天前', '3天前', '2天前', '昨天', '今天'],
            datasets: [
                { label: '视频新增', data: <?= json_encode($weekStats['videos']) ?>, borderColor: '#6fcf97', backgroundColor: 'rgba(111,207,151,0.1)', tension: 0.3, fill: true },
                { label: '评论新增', data: <?= json_encode($weekStats['comments']) ?>, borderColor: '#ffcc66', backgroundColor: 'rgba(255,204,102,0.1)', tension: 0.3, fill: true }
            ]
        },
        options: { responsive: true, maintainAspectRatio: true }
    });
    <?php endif; ?>

    // 视频编辑打开
    function openEditVideo(id, title, desc, cat, plays, danmu, cover) {
        document.getElementById('edit_video_id').value = id;
        document.getElementById('edit_title').value = title;
        document.getElementById('edit_description').value = desc;
        document.getElementById('edit_category').value = cat;
        document.getElementById('edit_plays').value = plays;
        document.getElementById('edit_danmu').value = danmu;
        document.getElementById('edit_cover_url').value = cover;
        document.getElementById('editVideoModal').style.display = 'flex';
    }
    // 用户编辑打开
    function openEditUser(handle, name, email, isAdmin) {
        document.getElementById('edit_user_handle').value = handle;
        document.getElementById('edit_display_name').value = name;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_is_admin').checked = !!isAdmin;
        document.getElementById('editUserModal').style.display = 'flex';
    }
    function closeModal() {
        document.querySelectorAll('.modal').forEach(m => m.style.display = 'none');
    }
    window.onclick = function(e) { if (e.target.classList.contains('modal')) closeModal(); }

    // 全选
    const selComments = document.getElementById('selectAllComments');
    if (selComments) selComments.onclick = () => document.querySelectorAll('input[name="comment_ids[]"]').forEach(cb => cb.checked = selComments.checked);
    const selDanmu = document.getElementById('selectAllDanmu');
    if (selDanmu) selDanmu.onclick = () => document.querySelectorAll('input[name="danmu_ids[]"]').forEach(cb => cb.checked = selDanmu.checked);
</script>
</body>
</html>